<?php

/**
 * Update html editor to enable spellcheck
 */
class SpellCheckAdminExtension extends Extension
{
    /**
     * HTMLEditorConfig name to use
     *
     * @var string
     * @config
     */
    private static $editor = 'cms';

    public function init()
    {
        // Set settings (respect deprecated middleware)
        $editor = Config::inst()->get('SpellRequestFilter', 'editor')
            ?: Config::inst()->get(__CLASS__, 'editor');

        $editorConfig = HtmlEditorConfig::get($editor);

        $editorConfig->enablePlugins('spellchecker');
        $editorConfig->addButtonsToLine(2, 'spellchecker');

        $token = SecurityToken::inst();

        $editorConfig
            ->setOption('spellchecker_rpc_url', Director::absoluteURL($token->addToUrl('spellcheck/')))
            ->setOption('browser_spellcheck', false)
            ->setOption('spellchecker_languages', implode(',', $this->getLanguages()));
    }

    /**
     * Check languages to set
     *
     * @return string[]
     */
    public function getLanguages()
    {
        $languages = [];

        $defaultLocale = $this->getDefaultLocale();

        foreach (SpellController::get_locales() as $locale) {
            $localeName = i18n::get_locale_name($locale);

            // Fix incorrectly spelled Māori language
            $localeName = str_replace('Maori', 'Māori', $localeName);

            // Indicate default locale
            if ($locale === $defaultLocale) {
                $localeName = '+' . $localeName;
            }

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
