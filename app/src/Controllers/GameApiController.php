<?php

namespace App\Controllers;

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use App\Services\HandGame;
use InvalidArgumentException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;

class GameApiController extends RequestHandler
{
    private static array $url_handlers = [
        '$Hash/$Action' => 'index',
        '$Hash'         => 'index',
        ''              => 'index',
    ];

    private static array $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() === 'OPTIONS') {
            return $this->jsonResponse([]);
        }

        $hash = $request->param('Hash');
        $action = $request->param('Action');
        $method = $request->httpMethod();

        try {
            if (!$hash && $method === 'POST') {
                return $this->createGame();
            }

            if ($hash) {
                /** @var Game|null $game */
                $game = Game::get()->find('Hash', $hash);
                if (!$game) {
                    return $this->jsonError(404, 'Game not found');
                }

                HandGame::tick($game);

                if (!$action) {
                    return $method === 'GET'
                        ? $this->getGame($request, $game)
                        : $this->jsonError(405, 'Method not allowed');
                }

                if (str_starts_with((string) $action, 'hand-')) {
                    return $this->handAction($request, $game, substr((string) $action, 5));
                }

                switch ($action) {
                    case 'setup':
                        return $this->setupGame($request, $game);
                    case 'cards':
                        return $this->setCards($request, $game);
                    case 'card':
                        return $this->addCustomCard($request, $game);
                    case 'draw':
                        return $this->drawCard($request, $game);
                    case 'action':
                        return $this->playerAction($request, $game);
                    case 'reset':
                        return $this->resetDeck($game);
                    default:
                        return $this->jsonError(404, 'Unknown action');
                }
            }

            return $this->jsonError(400, 'Bad request');
        } catch (InvalidArgumentException $e) {
            return $this->jsonError(409, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->jsonError(500, $e->getMessage());
        }
    }

    private function createGame(): HTTPResponse
    {
        $game = Game::create();
        $game->write();
        return $this->jsonResponse(['hash' => $game->Hash, 'id' => $game->ID], 201);
    }

    private function getGame(HTTPRequest $request, Game $game): HTTPResponse
    {
        $me = $this->playerByToken($game, (string) $request->getVar('token'));

        return $this->jsonResponse($this->serializeGame($game, $me));
    }

    /**
     * "Hand" mode actions: /api/v1/game/{hash}/hand-{action}
     * Player actions need the player's device token.
     */
    private function handAction(HTTPRequest $request, Game $game, string $action): HTTPResponse
    {
        if ($request->httpMethod() !== 'POST') {
            return $this->jsonError(405, 'Method not allowed');
        }
        if ($game->GameType !== 'Hand') {
            return $this->jsonError(409, 'Game is not in "Hand" mode');
        }

        $body = $this->parseBody($request);
        $me = $this->playerByToken($game, (string) ($body['token'] ?? ''));

        switch ($action) {
            case 'join':
                $player = HandGame::join($game, (string) ($body['name'] ?? ''));
                return $this->jsonResponse([
                    'playerId' => $player->ID,
                    'token' => $player->Token,
                    'game' => $this->serializeGame($game, $player),
                ]);

            case 'remove':
                $player = $game->Players()->byID((int) ($body['playerId'] ?? 0));
                if (!$player) {
                    return $this->jsonError(404, 'Player not found');
                }
                HandGame::removePlayer($game, $player);
                break;

            case 'deal':
                HandGame::deal($game, (int) ($body['min'] ?? 1), (int) ($body['max'] ?? 10));
                break;

            case 'range':
                HandGame::setRange($game, (int) ($body['min'] ?? 1), (int) ($body['max'] ?? 10));
                break;

            case 'reshuffle':
                HandGame::reshuffle($game);
                break;

            case 'roll-missing':
                HandGame::rollMissing($game);
                break;

            case 'roll':
            case 'assign':
            case 'resolve':
                if (!$me) {
                    return $this->jsonError(403, 'Unknown player');
                }
                if ($action === 'roll') {
                    HandGame::roll($game, $me);
                } elseif ($action === 'assign') {
                    $target = $game->Players()->byID((int) ($body['targetId'] ?? 0));
                    if (!$target) {
                        return $this->jsonError(404, 'Player not found');
                    }
                    $segments = is_array($body['segments'] ?? null) ? $body['segments'] : [];
                    HandGame::assign($game, $me, (int) ($body['cardId'] ?? 0), $target, $segments);
                } else {
                    HandGame::resolve($game, $me, ($body['type'] ?? '') === 'complete');
                }
                break;

            default:
                return $this->jsonError(404, 'Unknown action');
        }

        // Reload: the service may have written newer values than this instance holds
        $game = Game::get()->byID($game->ID);
        $me = $me ? Player::get()->byID($me->ID) : null;

        return $this->jsonResponse($this->serializeGame($game, $me));
    }

    private function playerByToken(Game $game, string $token): ?Player
    {
        if ($token === '') {
            return null;
        }

        /** @var Player|null $player */
        $player = $game->Players()->find('Token', $token);

        return $player;
    }

    private function setupGame(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);

        if (isset($body['adultType'])) {
            $game->AdultType = $body['adultType'];
        }
        if (isset($body['gameType'])) {
            // Switching to "Hand": names typed in for another mode are not on any
            // phone, so only players who joined with their own device stay
            if ($body['gameType'] === 'Hand' && $game->GameType !== 'Hand') {
                foreach ($game->Players()->filter('Token', [null, '']) as $player) {
                    $player->delete();
                }
            }
            $game->GameType = $body['gameType'];
        }
        if (isset($body['handSize'])) {
            $game->HandSize = max(HandGame::MIN_HAND_SIZE, min(HandGame::MAX_HAND_SIZE, (int) $body['handSize']));
        }
        $game->write();

        if (isset($body['players']) && is_array($body['players'])) {
            $game->Players()->removeAll();
            $sortOrder = 0;
            foreach ($body['players'] as $playerName) {
                $name = trim((string) $playerName);
                if ($name === '') {
                    continue;
                }
                $player = Player::create();
                $player->Name = $name;
                $player->GameID = $game->ID;
                $player->SortOrder = $sortOrder++;
                $player->write();
            }
            // New player list: start again with the first player
            $game->TurnIndex = 0;
            $game->write();
        }

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function setCards(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);

        $game->Cards()->removeAll();

        if (!empty($body['cardIds']) && is_array($body['cardIds'])) {
            foreach ($body['cardIds'] as $cardId) {
                $card = Card::get()->byID((int) $cardId);
                if ($card) {
                    $game->Cards()->add($card);
                }
            }
        }

        if (!empty($body['customCards']) && is_array($body['customCards'])) {
            foreach ($body['customCards'] as $item) {
                if (is_string($item)) {
                    $dare = trim($item);
                    $level = 1;
                    $adultsOnly = false;
                    $type = Card::TYPE_DARE;
                } else {
                    $dare = trim((string) ($item['dare'] ?? ''));
                    $level = isset($item['level']) ? max(1, min(10, (int) $item['level'])) : 1;
                    $adultsOnly = !empty($item['adultsOnly']);
                    $type = Card::normaliseType($item['type'] ?? null);
                }
                if ($dare === '') {
                    continue;
                }
                $card = Card::create();
                $card->Dare = $dare;
                $card->Type = $type;
                $card->Level = $level;
                $card->Official = false;
                $card->AdultsOnly = $adultsOnly;
                $card->write();
                $game->Cards()->add($card);
            }
        }

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function addCustomCard(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);
        $dare = trim((string) ($body['dare'] ?? ''));

        if ($dare === '') {
            return $this->jsonError(400, 'Dare text is required');
        }

        $card = Card::create();
        $card->Dare = $dare;
        $card->Type = Card::normaliseType($body['type'] ?? null);
        $card->Level = isset($body['level']) ? max(1, min(10, (int) $body['level'])) : 1;
        $card->Official = false;
        $card->AdultsOnly = !empty($body['adultsOnly']);
        $card->write();

        $game->Cards()->add($card);

        // "Hand" mode: a new dare can fill a hand that ran short on an empty pool
        HandGame::topUpHands($game);

        return $this->jsonResponse($this->serializeCard($card), 201);
    }

    private function drawCard(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);
        $cardId = (int) ($body['cardId'] ?? 0);

        if (!$cardId) {
            return $this->jsonError(400, 'cardId is required');
        }

        $used = $this->getUsedCardIds($game);
        if (!in_array($cardId, $used, true)) {
            $used[] = $cardId;
            $game->UsedCardIDs = json_encode($used);
            $game->write();
        }

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function playerAction(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);
        $cardId = (int) ($body['cardId'] ?? 0);
        $type = (string) ($body['type'] ?? '');

        if (!$cardId) {
            return $this->jsonError(400, 'cardId is required');
        }

        if ($type === 'return') {
            $used = array_values(array_filter(
                $this->getUsedCardIds($game),
                fn($id) => $id !== $cardId
            ));
            $game->UsedCardIDs = json_encode($used);
            $game->write();
            return $this->jsonResponse($this->serializeGame($game));
        }

        $playerId = (int) ($body['playerId'] ?? 0);
        if (!$playerId) {
            return $this->jsonError(400, 'playerId is required');
        }

        /** @var Player|null $player */
        $player = $game->Players()->byID($playerId);
        if (!$player) {
            return $this->jsonError(404, 'Player not found');
        }

        $card = Card::get()->byID($cardId);
        if (!$card) {
            return $this->jsonError(404, 'Card not found');
        }

        if ($type === 'complete') {
            $player->CompletionCount++;
            $player->CardsCompleted()->add($card);
        } elseif ($type === 'fail') {
            $player->FailureCount++;
            $player->CardsFailed()->add($card);
        } else {
            return $this->jsonError(400, 'Invalid action type');
        }

        $player->write();

        $used = $this->getUsedCardIds($game);
        if (!in_array($cardId, $used, true)) {
            $used[] = $cardId;
            $game->UsedCardIDs = json_encode($used);
        }

        // With a fixed player order a completed or failed card ends the turn
        if ($game->hasTurns()) {
            $game->advanceTurn();
        }
        $game->write();

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function resetDeck(Game $game): HTTPResponse
    {
        if ($game->GameType === 'Hand') {
            HandGame::reshuffle($game);
            return $this->jsonResponse($this->serializeGame($game));
        }

        $game->UsedCardIDs = json_encode([]);
        $game->write();
        return $this->jsonResponse($this->serializeGame($game));
    }

    /**
     * @param Player|null $me Player whose device asks (Hand mode); gets their own hand,
     *                        and the full card list is left out to keep polling light
     */
    private function serializeGame(Game $game, ?Player $me = null): array
    {
        $isHand = $game->GameType === 'Hand';
        $players = [];
        foreach ($game->Players() as $player) {
            $data = [
                'id' => $player->ID,
                'name' => $player->Name,
                'completionCount' => (int) $player->CompletionCount,
                'failureCount' => (int) $player->FailureCount,
            ];
            if ($isHand) {
                $data['joined'] = (bool) $player->Token;
                $data['diceRoll'] = (int) $player->DiceRoll;
                $data['rollEligible'] = (bool) $player->RollEligible;
                $data['handCount'] = count($player->getHandIds());
            }
            $players[] = $data;
        }

        $cards = [];
        if (!$me) {
            foreach ($game->Cards() as $card) {
                $cards[] = $this->serializeCard($card);
            }
        }

        $result = [
            'id' => $game->ID,
            'hash' => $game->Hash,
            'adultType' => $game->AdultType,
            'gameType' => $game->GameType,
            'players' => $players,
            'cards' => $cards,
            'usedCardIds' => $this->getUsedCardIds($game),
            'currentPlayerId' => $game->getCurrentPlayer()?->ID,
            'handSize' => (int) ($game->HandSize ?: 4),
        ];

        if ($isHand) {
            $active = null;
            if ($game->HandPhase === 'Pending' && $game->ActiveCardID) {
                $card = $game->ActiveCard();
                $segments = json_decode((string) $game->ActiveDare, true);
                $active = [
                    'id' => $card->ID,
                    'level' => (int) $card->Level,
                    'adultsOnly' => (bool) $card->AdultsOnly,
                    'segments' => is_array($segments) && $segments
                        ? $segments
                        : [['text' => (string) $card->Dare, 'highlighted' => false]],
                ];
            }

            $result['hand'] = [
                'phase' => $game->HandPhase,
                'giverId' => $game->GiverID ?: null,
                'targetId' => $game->TargetID ?: null,
                'failStreak' => (int) $game->FailStreak,
                // Dice round finished, results are shown briefly before it moves on
                'rollsComplete' => $game->HandPhase === 'Rolling' && (bool) $game->RollsCompletedAt,
                'maxFails' => HandGame::MAX_FAILED_ASSIGNMENTS,
                'refillMin' => (int) ($game->RefillMin ?: 1),
                'refillMax' => (int) ($game->RefillMax ?: 10),
                'poolCount' => $game->HandPhase === 'Lobby' ? null : HandGame::poolCount($game),
                'activeCard' => $active,
            ];
        }

        if ($me) {
            $result['me'] = [
                'id' => $me->ID,
                'name' => $me->Name,
                'hand' => array_map(fn(Card $c) => $this->serializeCard($c), HandGame::handCards($me)),
            ];
        }

        return $result;
    }

    private function serializeCard(Card $card): array
    {
        return [
            'id' => $card->ID,
            'dare' => $card->Dare,
            'type' => $card->Type ?: Card::TYPE_DARE,
            'level' => (int) $card->Level,
            'adultsOnly' => (bool) $card->AdultsOnly,
            'official' => (bool) $card->Official,
        ];
    }

    private function getUsedCardIds(Game $game): array
    {
        if (empty($game->UsedCardIDs)) {
            return [];
        }
        $decoded = json_decode($game->UsedCardIDs, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    private function parseBody(HTTPRequest $request): array
    {
        $body = json_decode($request->getBody(), true);
        return is_array($body) ? $body : [];
    }

    private function jsonResponse(array $data, int $status = 200): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->setStatusCode($status);
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($data));
        return $response;
    }

    private function jsonError(int $status, string $message): HTTPResponse
    {
        return $this->jsonResponse(['error' => $message], $status);
    }
}
