<?php

namespace SilverStripe\SpellCheck\Handling;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\SecurityToken;

class SpellCheckMiddleware implements HTTPMiddleware
{
    use Configurable;

    /**
     * HTMLEditorConfig name to use
     *
     * @var string
     * @config
     */
    private static $editor = 'cms';

    public function process(HTTPRequest $request, callable $delegate)
    {
        // Set settings
        $editor = static::config()->get('editor');
        HTMLEditorConfig::get($editor)->enablePlugins('spellchecker');
        HTMLEditorConfig::get($editor)->addButtonsToLine(2, 'spellchecker');
        $token = SecurityToken::inst();
        HTMLEditorConfig::get($editor)->setOption('spellchecker_rpc_url', $token->addToUrl('spellcheck/'));
        HTMLEditorConfig::get($editor)->setOption('browser_spellcheck', false);
        HTMLEditorConfig::get($editor)->setOption(
            'spellchecker_languages',
            '+'.implode(', ', $this->getLanguages())
        );

        return $delegate($request);
    }

    /**
     * Check languages to set
     *
     * @return array
     */
    public function getLanguages()
    {
        $languages = [];
        foreach (SpellController::get_locales() as $locale) {
            $languages[] = i18n::getData()->localeName($locale) . '=' . $locale;
        }
        return $languages;
    }
}
