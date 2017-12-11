<?php

namespace SilverStripe\SpellCheck\Tests\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\SpellCheck\Data\SpellProvider;

class SpellProviderStub implements SpellProvider, TestOnly
{
    public function checkWords($locale, $words)
    {
        if ($locale === 'en_NZ') {
            return ['collor', 'color', 'onee'];
        }

        return ['collor', 'colour', 'onee'];
    }

    public function getSuggestions($locale, $word)
    {
        if ($locale === 'en_NZ') {
            return ['collar', 'colour'];
        }

        return ['collar', 'color'];
    }
}
