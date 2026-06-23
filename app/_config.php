<?php

use SilverStripe\Security\Validation\RulesPasswordValidator;
use SilverStripe\ORM\Search\FulltextSearchable;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\TinyMCE\TinyMCEConfig;

// remove PasswordValidator for SilverStripe 5.0
$validator = RulesPasswordValidator::create();
// Settings are registered via Injector configuration - see passwords.yml in framework
Member::set_password_validator($validator);
i18n::set_locale('de_DE');
FulltextSearchable::enable();

$editorConfig = TinyMCEConfig::get('cms');
$editorConfig->disablePlugins(['ssembed', 'ssmedia']);