<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;

/**
 * Class \App\Models\Card
 *
 * @property ?string $Dare
 * @property int $Level
 * @property bool $AdultsOnly
 * @property bool $Official
 * @method \SilverStripe\ORM\ManyManyList|\App\Models\Player[] PlayersCompleted()
 * @method \SilverStripe\ORM\ManyManyList|\App\Models\Player[] PlayersFailed()
 * @method \SilverStripe\ORM\ManyManyList|\App\Models\Game[] Games()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Card extends DataObject
{
    private static string $table_name = 'Card';

    private static array $db = [
        'Dare' => 'Text',
        'Level' => 'Int',
        'AdultsOnly' => 'Boolean',
        'Official' => 'Boolean',
    ];

    private static array $summary_fields = [
        'Title',
        'Level',
        'RenderAdultsOnly' => 'Adults Only',
        'RenderOfficialStatus' => 'Official',
    ];

    private static array $belongs_many_many = [
        'PlayersCompleted' => Player::class,
        'PlayersFailed' => Player::class,
        'Games' => Game::class,
    ];

    private static array $searchable_fields = [
        'Dare',
        'Level',
        'AdultsOnly',
        'Official',
    ];

    private static string $default_sort = 'Official DESC, Level ASC, Dare ASC';

    public function getTitle(): string
    {
        // Limit the title to 70 characters for display purposes
        $title = $this->Dare;
        if (strlen($title) > 70) {
            $title = substr($title, 0, 70) . '...';
        }
        return $title;
    }

    public function RenderAdultsOnly(): string
    {
        return $this->AdultsOnly ? 'Yes' : 'No';
    }

    public function RenderOfficialStatus(): string
    {
        return $this->Official ? 'Yes' : 'No';
    }
}
