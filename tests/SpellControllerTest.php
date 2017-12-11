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

    protected function setUp()
    {
        parent::setUp();

        $this->securityWasEnabled = SecurityToken::is_enabled();

        // Reset config
        Config::modify()->set(SpellController::class, 'required_permission', 'CMS_ACCESS_CMSMain');
        Config::inst()->remove(SpellController::class, 'locales');
        Config::modify()->set(SpellController::class, 'locales', array('en_US', 'en_NZ', 'fr_FR'));
        Config::modify()->set(SpellController::class, 'enable_security_token', true);
        SecurityToken::enable();

        // Setup mock for testing provider
        $spellChecker = new SpellProviderStub;
        Injector::inst()->registerService($spellChecker, SpellProvider::class);
    }

    protected function tearDown()
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
        $this->assertEquals($tokenError, $jsonBody->error->errstr);

        // Test request with correct token (will fail with an unrelated error)
        $response = $this->get(
            'spellcheck/?SecurityID='.urlencode($token),
            Injector::inst()->create(Session::class, $session)
        );
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEquals($tokenError, $jsonBody->error->errstr);

        // Test request with check disabled
        Config::modify()->set(SpellController::class, 'enable_security_token', false);
        $response = $this->get('spellcheck', Injector::inst()->create(Session::class, $session));
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEquals($tokenError, $jsonBody->error->errstr);
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
        $this->assertNotEquals($securityError, $jsonBody->error->errstr);

        // Test insufficient permissions
        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $response = $this->get('spellcheck');
        $this->assertEquals(403, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals($securityError, $jsonBody->error->errstr);

        // Test disabled permissions
        Config::modify()->set(SpellController::class, 'required_permission', false);
        $response = $this->get('spellcheck');
        $jsonBody = json_decode($response->getBody());
        $this->assertNotEquals($securityError, $jsonBody->error->errstr);
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

        // Test checkWords acceptance
        $dataCheckWords = array(
            'id' => 'c0',
            'method' => 'checkWords',
            'params' => array(
                'en_NZ',
                array('collor', 'colour', 'color', 'onee', 'correct')
            )
        );
        $response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataCheckWords)));
        $this->assertEquals(200, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals('c0', $jsonBody->id);
        $this->assertEquals(array("collor", "color", "onee"), $jsonBody->result);

        // Test getSuggestions acceptance
        $dataGetSuggestions = array(
            'id' => '//c1//', // Should be reduced to only alphanumeric characters
            'method' => 'getSuggestions',
            'params' => array(
                'en_NZ',
                'collor'

            )
        );
        $response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataGetSuggestions)));
        $this->assertEquals(200, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals('c1', $jsonBody->id);
        $this->assertEquals(array('collar', 'colour'), $jsonBody->result);

        // Test non-ajax rejection
        $response = $this->post('spellcheck', array('json_data' => json_encode($dataCheckWords)));
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals($invalidRequest, $jsonBody->error->errstr);

        // Test incorrect method
        $dataInvalidMethod = $dataCheckWords;
        $dataInvalidMethod['method'] = 'validate';
        $response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataInvalidMethod)));
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals(
            _t(
                'SilverStripe\\SpellCheck\\Handling\\.UnsupportedMethod',
                "Unsupported method '{method}'",
                array('method' => 'validate')
            ),
            $jsonBody->error->errstr
        );

        // Test missing method
        $dataNoMethod = $dataCheckWords;
        unset($dataNoMethod['method']);
        $response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataNoMethod)));
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals($invalidRequest, $jsonBody->error->errstr);

        // Test unsupported locale
        $dataWrongLocale = $dataCheckWords;
        $dataWrongLocale['params'] = array(
            'de_DE',
            array('collor', 'colour', 'color', 'onee', 'correct')
        );
        $response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataWrongLocale)));
        $this->assertEquals(400, $response->getStatusCode());
        $jsonBody = json_decode($response->getBody());
        $this->assertEquals(_t(
            'SilverStripe\\SpellCheck\\Handling\\.InvalidLocale',
            'Not supported locale'
        ), $jsonBody->error->errstr);
    }
}
