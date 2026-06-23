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
        'GameType' => 'Enum("Cardpile, Managed", "Cardpile")',
        'Hash' => 'Varchar(255)',
        'UsedCardIDs' => 'Text',
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

    #[Override]
    protected function onBeforeWrite()
    {
        if (!$this->Hash) {
            $this->Hash = bin2hex(random_bytes(16));
        }
        return parent::onBeforeWrite();
    }
}
