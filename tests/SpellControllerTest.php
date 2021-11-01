<?php

namespace SilverStripe\SpellCheck\Tests;

use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SpellCheck\Data\SpellProvider;
use SilverStripe\SpellCheck\Handling\SpellController;
use SilverStripe\SpellCheck\Tests\Stub\SpellProviderStub;

/**
 * Tests the {@see SpellController} class
 */
class SpellControllerTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected $securityWasEnabled = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityWasEnabled = SecurityToken::is_enabled();

        // Reset config
        Config::modify()->set(SpellController::class, 'required_permission', 'CMS_ACCESS_CMSMain');
        Config::inst()->remove(SpellController::class, 'locales');
        Config::modify()
            ->set(SpellController::class, 'locales', array('en_US', 'en_NZ', 'fr_FR'))
            ->set(SpellController::class, 'enable_security_token', true)
            ->set(SpellController::class, 'return_errors_as_ok', false);

        SecurityToken::enable();

        // Setup mock for testing provider
        $spellChecker = new SpellProviderStub;
        Injector::inst()->registerService($spellChecker, SpellProvider::class);
    }

    protected function tearDown(): void
    {
        if ($this->securityWasEnabled) {
            SecurityToken::enable();
        } else {
            SecurityToken::disable();
        }

        parent::tearDown();
    }

    /**
     * Tests security ID check
     */
    public function testSecurityID()
    {
        // Mock token
        $securityToken = SecurityToken::inst();
        $generator = new RandomGenerator();
        $token = $generator->randomToken('sha1');
        $session = array(
            $securityToken->getName() => $token
        );
        $tokenError = _t(
            'SilverStripe\\SpellCheck\\Handling\\SpellController.SecurityMissing',
            'Your session has expired. Please refresh your browser to continue.'
        );

        // Test request sans token
        $response = $this->get('spellcheck', Injector::inst()->create(Session::class, $session));
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals($tokenError, $jsonBody->error);

        // Test request with correct token (will fail with an unrelated error)
        $response = $this->get(
            'spellcheck/?SecurityID='.urlencode($token),
            Injector::inst()->create(Session::class, $session)
        );
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEquals($tokenError, $jsonBody->error);

        // Test request with check disabled
        Config::modify()->set(SpellController::class, 'enable_security_token', false);
        $response = $this->get('spellcheck', Injector::inst()->create(Session::class, $session));
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEquals($tokenError, $jsonBody->error);
    }

    /**
     * Tests permission check
     */
    public function testPermissions()
    {
        // Disable security ID for this test
        Config::modify()->set(SpellController::class, 'enable_security_token', false);
        $securityError = _t('SilverStripe\\SpellCheck\\Handling\\SpellController.SecurityDenied', 'Permission Denied');

        // Test admin permissions
        Config::modify()->set(SpellController::class, 'required_permission', 'ADMIN');
        $this->logInWithPermission('ADMIN');
        $response = $this->get('spellcheck');
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEquals($securityError, $jsonBody->error);

        // Test insufficient permissions
        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $response = $this->get('spellcheck');
        $this->assertEquals(403, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals($securityError, $jsonBody->error);

        // Test disabled permissions
        Config::modify()->set(SpellController::class, 'required_permission', false);
        $response = $this->get('spellcheck');
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEquals($securityError, $jsonBody->error);
    }

    /**
     * @param string $lang
     * @param int $expectedStatusCode
     * @dataProvider langProvider
     */
    public function testBothLangAndLocaleInputResolveToLocale($lang, $expectedStatusCode, $errorsAreOk = false)
    {
        $this->logInWithPermission('ADMIN');
        Config::modify()
            ->set(SpellController::class, 'enable_security_token', false)
            ->set(SpellController::class, 'return_errors_as_ok', $errorsAreOk);

        $mockData = [
            'ajax' => true,
            'method' => 'spellcheck',
            'lang' => $lang,
            'text' => 'Collor is everywhere',
        ];
        $response = $this->post('spellcheck', $mockData);
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
    }

    /**
     * @return array[]
     */
    public function langProvider()
    {
        return [
            'english_language' => [
                'en', // assumes en_US is the default locale for "en" language
                200,
            ],
            'english_locale' => [
                'en_NZ',
                200,
            ],
            'invalid_language' => [
                'ru',
                400,
            ],
            'invalid_language_returned_as_ok' => [
                'ru',
                200,
                true
            ],
            'other_valid_language' => [
                'fr', // assumes fr_FR is the default locale for "en" language
                200,
            ],
            'other_valid_locale' => [
                'fr_FR',
                200,
            ],
        ];
    }

    /**
     * Ensure that invalid input is correctly rejected
     */
    public function testInputRejection()
    {
        // Disable security ID and permissions for this test
        Config::modify()->set(SpellController::class, 'enable_security_token', false);
        Config::modify()->set(SpellController::class, 'required_permission', false);
        $invalidRequest = _t('SilverStripe\\SpellCheck\\Handling\\SpellController.InvalidRequest', 'Invalid request');

        // Test spellcheck acceptance
        $mockData = [
            'method' => 'spellcheck',
            'lang' => 'en_NZ',
            'text' => 'Collor is everywhere',
        ];
        $response = $this->post('spellcheck', ['ajax' => true] + $mockData);
        $this->assertEquals(200, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEmpty($jsonBody->words);
        $this->assertNotEmpty($jsonBody->words->collor);
        $this->assertEquals(['collar', 'colour'], $jsonBody->words->collor);

        // Test non-ajax rejection
        $response = $this->post('spellcheck', $mockData);
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals($invalidRequest, $jsonBody->error);

        // Test incorrect method
        $dataInvalidMethod = $mockData;
        $dataInvalidMethod['method'] = 'validate';
        $response = $this->post('spellcheck', ['ajax' => true] + $dataInvalidMethod);
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals(
            _t(
                'SilverStripe\\SpellCheck\\Handling\\SpellController.UnsupportedMethod',
                "Unsupported method '{method}'",
                array('method' => 'validate')
            ),
            $jsonBody->error
        );

        // Test missing method
        $dataNoMethod = $mockData;
        unset($dataNoMethod['method']);
        $response = $this->post('spellcheck', ['ajax' => true] + $dataNoMethod);
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals($invalidRequest, $jsonBody->error);

        // Test unsupported locale
        $dataWrongLocale = $mockData;
        $dataWrongLocale['lang'] = 'de_DE';

        $response = $this->post('spellcheck', ['ajax' => true] + $dataWrongLocale);
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals(_t(
            'SilverStripe\\SpellCheck\\Handling\\SpellController.InvalidLocale',
            'Not a supported locale'
        ), $jsonBody->error);
    }
}
