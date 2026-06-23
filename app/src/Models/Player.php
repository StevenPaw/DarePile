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

    private static string $default_sort = 'Name ASC';
}
