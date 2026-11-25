<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use InvalidArgumentException;

/**
 * Rules of the "Hand" game mode ("Handkarten").
 *
 * Flow:
 *  Lobby    → players join on their own devices, host picks the level range and deals
 *  Rolling  → everybody rolls a die, the highest roll becomes the giver (ties roll again)
 *  Choosing → the giver picks a card from their hand and a target player
 *  Pending  → the target does the dare, the giver confirms the outcome
 *
 * After a completed dare the giver's hand is refilled and the target becomes the next giver.
 * After a failed dare the giver picks again (no refill in between). After
 * MAX_FAILED_ASSIGNMENTS failed dares in a row, or with an empty hand, the giver's hand
 * is refilled and a new dice round decides who gives next.
 *
 * Every card exists only once per game: a card is either in the pool, on exactly one
 * hand, active, or used.
 */
class HandGame
{
    public const MAX_FAILED_ASSIGNMENTS = 4;

    public const MIN_HAND_SIZE = 2;

    public const MAX_HAND_SIZE = 6;

    /**
     * Seconds the dice results stay visible after the last roll, so the last
     * person sees their die land before the game moves on.
     */
    public const ROLL_RESULT_SECONDS = 4;

    /**
     * Join the game by name. Joining with a name that already exists re-attaches
     * to that player, so someone can come back after losing their device session.
     */
    public static function join(Game $game, string $name): Player
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required');
        }

        $player = null;
        foreach ($game->Players() as $existing) {
            if (mb_strtolower((string) $existing->Name) === mb_strtolower($name)) {
                $player = $existing;
                break;
            }
        }

        if (!$player) {
            $player = Player::create();
            $player->Name = mb_substr($name, 0, 40);
            $player->GameID = $game->ID;
            $player->SortOrder = $game->Players()->count();
        }

        if (!$player->Token) {
            $player->Token = bin2hex(random_bytes(16));
        }
        $player->write();

        // Joining a running game: deal a hand right away
        if ($game->HandPhase !== 'Lobby' && !$player->getHandIds()) {
            self::fillHand($game, $player);
        }

        return $player;
    }

    public static function removePlayer(Game $game, Player $player): void
    {
        if ($game->HandPhase !== 'Lobby') {
            throw new InvalidArgumentException('Players can only be removed before the game starts');
        }
        $player->delete();
    }

    /**
     * Deal the first hands from the given level range and start the first dice round.
     */
    public static function deal(Game $game, int $min, int $max): void
    {
        if ($game->HandPhase !== 'Lobby') {
            throw new InvalidArgumentException('Cards have already been dealt');
        }
        if ($game->Players()->count() < 2) {
            throw new InvalidArgumentException('At least two players are required');
        }

        self::applyRange($game, $min, $max);
        foreach ($game->Players() as $player) {
            $player->setHandIds([]);
            self::fillHand($game, $player);
        }

        self::startRolling($game);
    }

    /**
     * Change the level range cards are refilled from. Hands that are short
     * (because the pool was empty) are topped up from the new range.
     */
    public static function setRange(Game $game, int $min, int $max): void
    {
        self::applyRange($game, $min, $max);
        $game->write();

        if ($game->HandPhase !== 'Lobby') {
            self::topUpHands($game);
        }
    }

    /**
     * Put all played cards back into the pool and top up short hands.
     */
    public static function reshuffle(Game $game): void
    {
        $game->UsedCardIDs = json_encode([]);
        $game->write();
        self::topUpHands($game);
    }

    public static function roll(Game $game, Player $player): void
    {
        if ($game->HandPhase !== 'Rolling') {
            throw new InvalidArgumentException('No dice round in progress');
        }
        if (!$player->RollEligible || (int) $player->DiceRoll > 0) {
            return;
        }

        $player->DiceRoll = random_int(1, 6);
        $player->write();

        self::markRollsComplete($game);
    }

    /**
     * Roll for everyone who has not rolled yet (e.g. a phone went to sleep).
     */
    public static function rollMissing(Game $game): void
    {
        if ($game->HandPhase !== 'Rolling') {
            return;
        }
        foreach ($game->Players() as $player) {
            if ($player->RollEligible && (int) $player->DiceRoll === 0) {
                $player->DiceRoll = random_int(1, 6);
                $player->write();
            }
        }

        self::markRollsComplete($game);
    }

    /**
     * Advance time-based state. Called on every request for the game (polling
     * runs every 2 s): resolves a finished dice round once its results have
     * been visible for ROLL_RESULT_SECONDS.
     */
    public static function tick(Game $game): void
    {
        if ($game->GameType !== 'Hand' || $game->HandPhase !== 'Rolling' || !$game->RollsCompletedAt) {
            return;
        }
        if (time() - (int) $game->RollsCompletedAt < self::ROLL_RESULT_SECONDS) {
            return;
        }

        $game->RollsCompletedAt = 0;
        $game->write();
        self::resolveRolls($game);
    }

    /**
     * @param array<int, array{text: string, highlighted: bool}> $segments
     *        Dare text with placeholders resolved by the giver's device, so all
     *        screens show the same names and times.
     */
    public static function assign(Game $game, Player $giver, int $cardId, Player $target, array $segments): void
    {
        if ($game->HandPhase !== 'Choosing' || (int) $game->GiverID !== (int) $giver->ID) {
            throw new InvalidArgumentException('It is not your turn to assign a dare');
        }
        if ((int) $target->ID === (int) $giver->ID || (int) $target->GameID !== (int) $game->ID) {
            throw new InvalidArgumentException('Invalid target player');
        }

        $hand = $giver->getHandIds();
        if (!in_array($cardId, $hand, true)) {
            throw new InvalidArgumentException('Card is not on your hand');
        }

        $giver->setHandIds(array_diff($hand, [$cardId]));
        $giver->write();

        $used = self::usedIds($game);
        $used[] = $cardId;
        $game->UsedCardIDs = json_encode(array_values(array_unique($used)));
        $game->ActiveCardID = $cardId;
        $game->TargetID = $target->ID;
        $game->ActiveDare = json_encode(self::sanitiseSegments($segments));
        $game->HandPhase = 'Pending';
        $game->write();
    }

    public static function resolve(Game $game, Player $giver, bool $completed): void
    {
        if ($game->HandPhase !== 'Pending' || (int) $game->GiverID !== (int) $giver->ID) {
            throw new InvalidArgumentException('Only the player who assigned the dare can confirm it');
        }

        $target = $game->Target();
        $card = $game->ActiveCard();

        if ($target->exists()) {
            if ($completed) {
                $target->CompletionCount++;
                if ($card->exists()) {
                    $target->CardsCompleted()->add($card);
                }
            } else {
                $target->FailureCount++;
                if ($card->exists()) {
                    $target->CardsFailed()->add($card);
                }
            }
            $target->write();
        }

        $game->ActiveCardID = 0;
        $game->TargetID = 0;
        $game->ActiveDare = null;

        if ($completed) {
            // The giver's turn ends: refill, and whoever did the dare gives next
            self::fillHand($game, $giver);
            $game->GiverID = $target->ID;
            $game->FailStreak = 0;
            $game->HandPhase = 'Choosing';
            $game->write();

            return;
        }

        $game->FailStreak++;
        if ($game->FailStreak >= self::MAX_FAILED_ASSIGNMENTS || !$giver->getHandIds()) {
            self::fillHand($game, $giver);
            self::startRolling($game);

            return;
        }

        // Same giver picks again, without refilling in between
        $game->HandPhase = 'Choosing';
        $game->write();
    }

    /**
     * Number of cards that can still be drawn from the current refill range.
     */
    public static function poolCount(Game $game): int
    {
        return count(self::poolIds($game));
    }

    /**
     * @return Card[] Cards on the player's hand, in hand order
     */
    public static function handCards(Player $player): array
    {
        $ids = $player->getHandIds();
        if (!$ids) {
            return [];
        }
        $byId = [];
        foreach (Card::get()->byIDs($ids) as $card) {
            $byId[$card->ID] = $card;
        }

        return array_values(array_filter(array_map(fn($id) => $byId[$id] ?? null, $ids)));
    }

    private static function startRolling(Game $game): void
    {
        foreach ($game->Players() as $player) {
            $player->DiceRoll = 0;
            $player->RollEligible = true;
            $player->write();
        }

        $game->GiverID = 0;
        $game->TargetID = 0;
        $game->ActiveCardID = 0;
        $game->ActiveDare = null;
        $game->FailStreak = 0;
        $game->RollsCompletedAt = 0;
        $game->HandPhase = 'Rolling';
        $game->write();
    }

    /**
     * Remember when the last person in the round rolled; tick() resolves later.
     */
    private static function markRollsComplete(Game $game): void
    {
        if ($game->RollsCompletedAt) {
            return;
        }
        foreach ($game->Players()->filter('RollEligible', true) as $player) {
            if ((int) $player->DiceRoll === 0) {
                return;
            }
        }
        $game->RollsCompletedAt = time();
        $game->write();
    }

    /**
     * Once everybody in the round has rolled: highest roll gives next.
     * On a tie only the tied players roll again.
     */
    private static function resolveRolls(Game $game): void
    {
        $eligible = $game->Players()->filter('RollEligible', true)->toArray();
        if (!$eligible) {
            return;
        }
        foreach ($eligible as $player) {
            if ((int) $player->DiceRoll === 0) {
                return;
            }
        }

        $max = max(array_map(fn(Player $p) => (int) $p->DiceRoll, $eligible));
        $winners = array_values(array_filter($eligible, fn(Player $p) => (int) $p->DiceRoll === $max));

        if (count($winners) === 1) {
            $game->GiverID = $winners[0]->ID;
            $game->FailStreak = 0;
            $game->HandPhase = 'Choosing';
            $game->write();

            return;
        }

        // Tie: losers keep their (shown) roll but are out, winners roll again
        foreach ($eligible as $player) {
            if ((int) $player->DiceRoll === $max) {
                $player->DiceRoll = 0;
            } else {
                $player->RollEligible = false;
            }
            $player->write();
        }
    }

    /**
     * Fill every hand up to the hand size, except the current giver's while
     * they are in the middle of their turn. Used when cards become available
     * again (reshuffle, new range, newly added dare).
     */
    public static function topUpHands(Game $game): void
    {
        if ($game->GameType !== 'Hand' || $game->HandPhase === 'Lobby') {
            return;
        }

        $skipGiver = in_array($game->HandPhase, ['Choosing', 'Pending'], true);
        foreach ($game->Players() as $player) {
            if ($skipGiver && (int) $player->ID === (int) $game->GiverID) {
                continue;
            }
            self::fillHand($game, $player);
        }
    }

    private static function fillHand(Game $game, Player $player): void
    {
        $hand = $player->getHandIds();
        $missing = self::handSize($game) - count($hand);
        if ($missing <= 0) {
            return;
        }

        $pool = self::poolIds($game);
        shuffle($pool);
        $player->setHandIds(array_merge($hand, array_slice($pool, 0, $missing)));
        $player->write();
    }

    /**
     * @return int[] IDs of cards that can be drawn: in the game, in the refill
     *               range, and not used, active or on any hand
     */
    private static function poolIds(Game $game): array
    {
        $blocked = self::usedIds($game);
        if ($game->ActiveCardID) {
            $blocked[] = $game->ActiveCardID;
        }
        foreach ($game->Players() as $player) {
            $blocked = array_merge($blocked, $player->getHandIds());
        }

        $cards = $game->Cards()->filter([
            // Truth questions do not work as dares given to someone else
            'Type' => Card::TYPE_DARE,
            'Level:GreaterThanOrEqual' => $game->RefillMin ?: 1,
            'Level:LessThanOrEqual' => $game->RefillMax ?: 10,
        ]);
        if ($blocked) {
            $cards = $cards->exclude('ID', $blocked);
        }

        return array_map('intval', $cards->column('ID'));
    }

    /**
     * @return int[]
     */
    private static function usedIds(Game $game): array
    {
        $decoded = json_decode((string) $game->UsedCardIDs, true);

        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    private static function handSize(Game $game): int
    {
        return max(self::MIN_HAND_SIZE, min(self::MAX_HAND_SIZE, $game->HandSize ?: 4));
    }

    private static function applyRange(Game $game, int $min, int $max): void
    {
        $min = max(1, min(10, $min));
        $max = max($min, min(10, $max));
        $game->RefillMin = $min;
        $game->RefillMax = $max;
    }

    /**
     * @param array<mixed> $segments
     * @return array<int, array{text: string, highlighted: bool}>
     */
    private static function sanitiseSegments(array $segments): array
    {
        $clean = [];
        foreach (array_slice($segments, 0, 50) as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $clean[] = [
                'text' => mb_substr((string) ($segment['text'] ?? ''), 0, 500),
                'highlighted' => !empty($segment['highlighted']),
            ];
        }

        return $clean;
    }
}
