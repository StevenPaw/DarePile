<?php

namespace App\Admin;

use App\Models\Card;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \App\Admin\CardAdmin
 *
 */
class CardAdmin extends ModelAdmin
{
    private static $url_segment = 'cards';

    private static $menu_title = 'Cards';

    private static $menu_icon_class = 'sp-icon-cards-admin-currentColor';

    private static $managed_models = [
        Card::class,
    ];

    private static $page_length = 50;

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
