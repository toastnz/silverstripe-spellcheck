<?php

namespace SilverStripe\SpellCheck\Handling;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestFilter;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\SecurityToken;

class SpellRequestFilter implements RequestFilter
{
    /**
     * HTMLEditorConfig name to use
     *
     * @var string
     * @config
     */
    private static $editor = 'cms';

    public function preRequest(HTTPRequest $request)
    {
        // Check languages to set
        $languages = [];
        foreach (SpellController::get_locales() as $locale) {
            $languages[] = i18n::get_locale_name($locale) . '=' . $locale;
        }


        // Set settings
        $editor = Config::inst()->get(__CLASS__, 'editor');
        HTMLEditorConfig::get($editor)->enablePlugins('spellchecker');
        HTMLEditorConfig::get($editor)->addButtonsToLine(2, 'spellchecker');
        $token = SecurityToken::inst();
        HTMLEditorConfig::get($editor)->setOption('spellchecker_rpc_url', $token->addToUrl('spellcheck/'));
        HTMLEditorConfig::get($editor)->setOption('browser_spellcheck', false);
        HTMLEditorConfig::get($editor)->setOption('spellchecker_languages', '+'.implode(', ', $languages));
        return true;
    }

    public function postRequest(HTTPRequest $request, HTTPResponse $response)
    {
        return true;
    }
}
