<?php

namespace App\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;

/**
 * Class \App\Controllers\SpaController
 *
 */
class SpaController extends Controller
{
    private static array $url_handlers = [
        '' => 'index',
    ];

    private static array $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request)
    {
        return $this->renderWith('Page');
    }
}
