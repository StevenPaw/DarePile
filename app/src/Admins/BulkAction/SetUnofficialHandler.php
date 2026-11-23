<?php

namespace App\Admin\BulkAction;

/**
 * Bulk action: mark the selected cards as unofficial.
 */
class SetUnofficialHandler extends SetOfficialHandler
{
    private static $url_segment = 'make-unofficial';

    protected $label = 'Make unofficial';

    protected $buttonClasses = 'font-icon-cancel-circled';

    protected bool $official = false;
}
