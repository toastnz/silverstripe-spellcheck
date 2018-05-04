<?php

namespace SilverStripe\Spellcheck\Tests\Handling;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SpellCheck\Handling\SpellCheckMiddleware;
use SilverStripe\SpellCheck\Handling\SpellController;

class SpellCheckMiddlewareTest extends SapphireTest
{
    public function testGetDefaultLocale()
    {
        $middleware = new SpellCheckMiddleware();

        Config::modify()->set(SpellController::class, 'default_locale', 'foo');
        $this->assertSame('foo', $middleware->getDefaultLocale(), 'Returns configured default');

        Config::modify()
            ->set(SpellController::class, 'default_locale', false)
            ->set(SpellController::class, 'locales', ['foo_BAR', 'bar_BAZ']);
        $this->assertSame('foo_BAR', $middleware->getDefaultLocale(), 'Returns first in `locales`');
    }
}
