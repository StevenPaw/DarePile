<?php

namespace App\Models;

use App\Admin\CardAdmin;
use App\Services\DareSimilarity;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
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

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        if ($this->isInDB() && trim((string) $this->Dare) !== '') {
            $fields->insertAfter('Dare', LiteralField::create('SimilarOfficial', $this->renderSimilarOfficial()));
        }

        return $fields;
    }

    /**
     * Best matching official card as "87 % – text", for the grid in the "Unofficial" tab.
     */
    public function getSimilarOfficialSummary(): string
    {
        $match = DareSimilarity::findSimilarOfficial((string) $this->Dare, 1, DareSimilarity::THRESHOLD, $this->ID)[0] ?? null;
        if (!$match) {
            return '–';
        }

        return sprintf('%d %% – %s', $match['score'], $match['card']->getTitle());
    }

    /**
     * Hint box listing official cards with a similar text, so duplicates are
     * noticed before a card is made official.
     */
    private function renderSimilarOfficial(): string
    {
        $matches = DareSimilarity::findSimilarOfficial((string) $this->Dare, 5, DareSimilarity::THRESHOLD, $this->ID);

        if (!$matches) {
            return '<div class="alert alert-success">No similar official cards found.</div>';
        }

        $items = '';
        foreach ($matches as $match) {
            /** @var Card $card */
            $card = $match['card'];
            $items .= sprintf(
                '<li><strong>%d %%</strong> – <a href="%s">%s</a> (Level %d)</li>',
                $match['score'],
                Convert::raw2att(CardAdmin::editLinkFor($card)),
                Convert::raw2xml((string) $card->Dare),
                $card->Level
            );
        }

        $alertClass = $matches[0]['score'] >= 75 ? 'alert-warning' : 'alert-info';

        return sprintf(
            '<div class="alert %s"><p><strong>Similar official cards</strong></p><ul class="mb-0">%s</ul></div>',
            $alertClass,
            $items
        );
    }
}
