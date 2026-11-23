<?php

namespace App\Admin;

use App\Models\Card;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\ORM\DataList;

/**
 * Class \App\Admin\CardAdmin
 *
 * Two tabs on the same model: official cards and cards added by players.
 *
 */
class CardAdmin extends ModelAdmin
{
    public const TAB_OFFICIAL = 'official';

    public const TAB_UNOFFICIAL = 'unofficial';

    private static $url_segment = 'cards';

    private static $menu_title = 'Cards';

    private static $menu_icon_class = 'sp-icon-cards-admin-currentColor';

    private static $managed_models = [
        self::TAB_OFFICIAL => [
            'title' => 'Official',
            'dataClass' => Card::class,
        ],
        self::TAB_UNOFFICIAL => [
            'title' => 'Unofficial',
            'dataClass' => Card::class,
        ],
    ];

    private static $page_length = 50;

    public function getList(): DataList
    {
        return parent::getList()->filter('Official', $this->modelTab === self::TAB_OFFICIAL);
    }

    /**
     * Link to edit a card in the tab it belongs to.
     */
    public static function editLinkFor(Card $card): string
    {
        $tab = $card->Official ? self::TAB_OFFICIAL : self::TAB_UNOFFICIAL;

        return static::singleton()->Link("{$tab}/EditForm/field/{$tab}/item/{$card->ID}/edit");
    }

    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);
        $gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelTab));

        if ($gridField instanceof GridField) {
            $config = $gridField->getConfig();

            if ($this->modelTab === self::TAB_UNOFFICIAL) {
                // Show the closest official card, so promotable cards are easy to spot
                $columns = $config->getComponentByType(GridFieldDataColumns::class);
                if ($columns) {
                    $columns->setDisplayFields([
                        'Title' => 'Dare',
                        'Level' => 'Level',
                        'RenderAdultsOnly' => 'Adults Only',
                        'SimilarOfficialSummary' => 'Most similar official card',
                    ]);
                }
            }

            // New cards created in the "Official" tab are official by default
            $detailForm = $config->getComponentByType(GridFieldDetailForm::class);
            if ($detailForm && $this->modelTab === self::TAB_OFFICIAL) {
                $detailForm->setItemEditFormCallback(function (Form $form): void {
                    $record = $form->getRecord();
                    if ($record && !$record->isInDB()) {
                        $form->Fields()->dataFieldByName('Official')?->setValue(true);
                    }
                });
            }
        }

        return $form;
    }

    /**
     * Export raw DB field names so the CSV can be re-imported without a custom column map.
     * The default (summaryFields) exports Title (truncated) and RenderAdultsOnly ('Yes'/'No')
     * which CsvBulkLoader cannot map back to the database.
     */
    public function getExportFields(): array
    {
        return [
            'Dare'       => 'Dare',
            'Level'      => 'Level',
            'AdultsOnly' => 'AdultsOnly',
            'Official'   => 'Official',
        ];
    }
}
