<?php

namespace SilverStripe\SpellCheck\Handling;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;
use SilverStripe\Forms\HTMLEditor\TinyMCEConfig;
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
        $editor = $this->config()->get('editor');

        /** @var TinyMCEConfig $editorConfig */
        $editorConfig = TinyMCEConfig::get($editor);

        $editorConfig->enablePlugins('spellchecker');
        $editorConfig->addButtonsToLine(2, 'spellchecker');

        $token = SecurityToken::inst();

        $editorConfig
            ->setOption('spellchecker_rpc_url', Director::absoluteURL($token->addToUrl('spellcheck/')))
            ->setOption('browser_spellcheck', false)
            ->setOption('spellchecker_languages', implode(',', $this->getLanguages()));

        $defaultLocale = $this->getDefaultLocale();
        if ($defaultLocale) {
            $editorConfig->setOption('spellchecker_language', $defaultLocale);
        }

        return $delegate($request);
    }

    /**
     * Check languages to set
     *
     * @return string[]
     */
    public function getLanguages()
    {
        $languages = [];
        foreach (SpellController::get_locales() as $locale) {
            $localeName = i18n::getData()->localeName($locale);
            // Fix incorrectly spelled Māori language
            $localeName = str_replace('Maori', 'Māori', $localeName);
            $languages[] = $localeName . '=' . $locale;
        }
        return $languages;
    }

    /**
     * Returns the default locale for TinyMCE. Either via configuration or the first in the list of locales.
     *
     * @return string|false
     */
    public function getDefaultLocale()
    {
        // Check configuration first
        $defaultLocale = SpellController::config()->get('default_locale');
        if ($defaultLocale) {
            return $defaultLocale;
        }

        // Grab the first one in the list
        $locales = SpellController::get_locales();
        if (empty($locales)) {
            return false;
        }
        return reset($locales);
    }
}
