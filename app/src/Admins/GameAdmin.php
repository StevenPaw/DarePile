<?php

namespace App\Admin;

use SilverStripe\Admin\ModelAdmin;
use App\Models\Game;

/**
 * Class \App\Admin\GameAdmin
 *
 */
class GameAdmin extends ModelAdmin
{
    private static $url_segment = 'games';

    private static $menu_title = 'Games';

    private static $menu_icon_class = 'sp-icon-crown-admin-currentColor';

    private static $managed_models = [
        Game::class,
    ];

    private static $page_length = 50;
}
