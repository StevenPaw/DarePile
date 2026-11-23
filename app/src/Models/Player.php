<?php

namespace App\Models;

use App\Models\Game;
use SilverStripe\ORM\DataObject;

/**
 * Class \App\Models\Player
 *
 * @property ?string $Name
 * @property ?string $AdultType
 * @property int $CompletionCount
 * @property int $FailureCount
 * @property int $SortOrder
 * @property ?string $Token
 * @property ?string $HandCardIDs
 * @property int $DiceRoll
 * @property bool $RollEligible
 * @property int $GameID
 * @method \App\Models\Game Game()
 * @method \SilverStripe\ORM\ManyManyList|\App\Models\Card[] CardsCompleted()
 * @method \SilverStripe\ORM\ManyManyList|\App\Models\Card[] CardsFailed()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Player extends DataObject
{
    private static string $table_name = 'Player';

    private static array $has_one = [
        'Game' => Game::class,
    ];

    private static array $db = [
        'Name' => 'Varchar(255)',
        'AdultType' => 'Enum("No Adult Dares, Misc Dares, Adult Dares Only", "No Adult Dares")',
        'CompletionCount' => 'Int',
        'FailureCount' => 'Int',
        // Position in the turn order (0-based), as entered during setup
        'SortOrder' => 'Int',
        // "Hand" mode: identifies the player's own device
        'Token' => 'Varchar(64)',
        // "Hand" mode: card IDs on the player's hand (JSON array)
        'HandCardIDs' => 'Text',
        // "Hand" mode dice round: rolled value (0 = not rolled yet), taking part in the current round
        'DiceRoll' => 'Int',
        'RollEligible' => 'Boolean',
    ];

    private static array $indexes = [
        'Token' => true,
    ];

    private static array $many_many = [
        'CardsCompleted' => Card::class,
        'CardsFailed' => Card::class,
    ];

    private static array $summary_fields = [
        'Name',
        'AdultType',
        'CompletionCount',
        'FailureCount',
    ];

    private static string $default_sort = 'SortOrder ASC, ID ASC';

    /**
     * @return int[]
     */
    public function getHandIds(): array
    {
        $decoded = json_decode((string) $this->HandCardIDs, true);

        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    /**
     * @param int[] $ids
     */
    public function setHandIds(array $ids): void
    {
        $this->HandCardIDs = json_encode(array_values($ids));
    }
}
