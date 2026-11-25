<?php

namespace App\Models;

use App\Models\Card;
use App\Models\Player;
use Override;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\Models\Game
 *
 * @property ?string $Title
 * @property ?string $AdultType
 * @property ?string $GameType
 * @property ?string $Hash
 * @property ?string $UsedCardIDs
 * @property int $TurnIndex
 * @property int $HandSize
 * @property ?string $HandPhase
 * @property int $FailStreak
 * @property int $RollsCompletedAt
 * @property int $RefillMin
 * @property int $RefillMax
 * @property ?string $ActiveDare
 * @property int $GiverID
 * @property int $TargetID
 * @property int $ActiveCardID
 * @method \App\Models\Player Giver()
 * @method \App\Models\Player Target()
 * @method \App\Models\Card ActiveCard()
 * @method \SilverStripe\ORM\DataList|\App\Models\Player[] Players()
 * @method \SilverStripe\ORM\ManyManyList|\App\Models\Card[] Cards()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Game extends DataObject
{
    private static string $table_name = 'Game';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'AdultType' => 'Enum("No Adult Dares, Misc Dares, Adult Dares Only", "No Adult Dares")',
        'GameType' => 'Enum("Cardpile, Turns, Hand, TruthOrDare", "Cardpile")',
        'Hash' => 'Varchar(255)',
        'UsedCardIDs' => 'Text',
        // "Turns" mode: index (in player order) of the player whose turn it is
        'TurnIndex' => 'Int',
        // "Hand" mode, see App\Services\HandGame
        'HandSize' => 'Int',
        'HandPhase' => 'Enum("Lobby, Rolling, Choosing, Pending", "Lobby")',
        'FailStreak' => 'Int',
        // Unix time when the last player of a dice round rolled (0 = round still open)
        'RollsCompletedAt' => 'Int',
        'RefillMin' => 'Int',
        'RefillMax' => 'Int',
        // Dare text of the active card with placeholders already resolved (JSON segments)
        'ActiveDare' => 'Text',
    ];

    private static array $has_one = [
        // "Hand" mode: who assigns the next dare, who has to do the active one
        'Giver' => Player::class,
        'Target' => Player::class,
        'ActiveCard' => Card::class,
    ];

    private static array $defaults = [
        'HandSize' => 4,
        'RefillMin' => 1,
        'RefillMax' => 10,
    ];

    private static array $summary_fields = [
        'Title',
        'AdultType',
    ];

    private static array $has_many = [
        'Players' => Player::class,
    ];

    private static array $owns = [
        'Players',
    ];

    private static array $many_many = [
        'Cards' => Card::class,
    ];

    private static string $default_sort = 'Title ASC';

    /**
     * Modes with a fixed player order: "Turns" and "TruthOrDare".
     */
    public function hasTurns(): bool
    {
        return in_array($this->GameType, ['Turns', 'TruthOrDare'], true);
    }

    /**
     * "Truth" cards are only played in "TruthOrDare" mode.
     */
    public function allowsTruthCards(): bool
    {
        return $this->GameType === 'TruthOrDare';
    }

    /**
     * Player whose turn it is in modes with a fixed order, null in other modes.
     */
    public function getCurrentPlayer(): ?Player
    {
        if (!$this->hasTurns()) {
            return null;
        }
        $players = $this->Players()->toArray();
        if (!$players) {
            return null;
        }

        return $players[$this->TurnIndex % count($players)];
    }

    /**
     * Pass the turn to the next player in order.
     */
    public function advanceTurn(): void
    {
        $count = $this->Players()->count();
        $this->TurnIndex = $count ? ($this->TurnIndex + 1) % $count : 0;
    }

    #[Override]
    protected function onBeforeWrite()
    {
        if (!$this->Hash) {
            $this->Hash = bin2hex(random_bytes(16));
        }
        return parent::onBeforeWrite();
    }
}
