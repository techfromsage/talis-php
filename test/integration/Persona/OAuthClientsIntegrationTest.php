<?php

use Talis\Persona\Client\OAuthClients;
use Talis\Persona\Client\Tokens;
use Talis\Persona\Client\Users;

$appRoot = dirname(dirname(dirname(__DIR__)));
if (!defined('APPROOT')) {
    define('APPROOT', $appRoot);
}

require_once $appRoot . '/test/unit/TestBase.php';

class OAuthClientsIntegrationTest extends TestBase
{

    /**
     * @var Talis\Persona\Client\OAuthClients
     */
    private $personaClientOAuthClient;

    /**
     * @var Talis\Persona\Client\Users
     */
    private $personaClientUser;
    /**
     * @var Talis\Persona\Client\Tokens
     */
    private $personaClientTokens;
    private $clientId;
    private $clientSecret;

    public function setUp()
    {
        parent::setUp();
        $personaConf = $this->getPersonaConfig();
        $this->clientId = $personaConf['oauthClient'];
        $this->clientSecret = $personaConf['oauthSecret'];

        $this->personaClientOAuthClient = new OAuthClients(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => $personaConf['host'],
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->personaClientUser = new Users(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => $personaConf['host'],
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->personaClientTokens = new Tokens(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => $personaConf['host'],
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testCreateUserThenPatchOAuthClientAddScope()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';
        $userCreate = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $token
        );

        $user = $this->personaClientUser->getUserByGupid($userCreate['gupids'][0], $token);
        $client = $this->personaClientOAuthClient->getOAuthClient($user['guid'], $token);
        $this->assertNotContains('additional-scope', $client['scope']);

        // Update the client
        $this->personaClientOAuthClient->updateOAuthClient(
            $user['guid'],
            ['scope' => ['$add' => 'additional-scope']],
            $token
        );

        // Get the oauth client again to see if scope has been updated
        $client = $this->personaClientOAuthClient->getOAuthClient($user['guid'], $token);
        $this->assertContains('additional-scope', $client['scope']);
    }

    public function testCreateUserThenPatchOAuthClientRemoveScope()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';
        $userCreate = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $token
        );

        $user = $this->personaClientUser->getUserByGupid($userCreate['gupids'][0], $token);
        $client = $this->personaClientOAuthClient->getOAuthClient($user['guid'], $token);
        $this->assertNotContains('additional-scope', $client['scope']);

        // Add the scope to the client
        $this->personaClientOAuthClient->updateOAuthClient(
            $user['guid'],
            ['scope' => ['$add' => 'additional-scope']],
            $token
        );

        // Get the oauth client again to see if scope has been updated
        $client = $this->personaClientOAuthClient->getOAuthClient($user['guid'], $token);
        $this->assertContains('additional-scope', $client['scope']);

        // Remove the scope from the client
        $this->personaClientOAuthClient->updateOAuthClient(
            $user['guid'],
            ['scope' => ['$remove' => 'additional-scope']],
            $token
        );

        $client = $this->personaClientOAuthClient->getOAuthClient($user['guid'], $token);
        $this->assertNotContains('additional-scope', $client['scope']);
    }

    public function testCreateUserThenGetClient()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';
        $userCreate = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $token
        );

        $user = $this->personaClientUser->getUserByGupid($userCreate['gupids'][0], $token);
        $client = $this->personaClientOAuthClient->getOAuthClient($user['guid'], $token);
        $this->assertArrayHasKey('scope', $client);
    }

    public function testGetOAuthClientInvalidTokenThrowsException()
    {
        $this->setExpectedException(
            'Exception',
            'Did not retrieve successful response code'
        );

        $personaClient = new OAuthClients(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => 'persona',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $personaClient->getOAuthClient('123', '456');
    }

    public function testRegenerateSecret()
    {
        $tokenDetails = $this->personaClientTokens->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $suToken = $tokenDetails['access_token'];

        $gupid = uniqid('trapdoor:');
        $email = uniqid() . '@example.com';

        $user = $this->personaClientUser->createUser(
            $gupid,
            ['name' => 'Sarah Connor', 'email' => $email],
            $suToken
        );

        $guid = $user['guid'];

        $secret = $this->personaClientOAuthClient->regenerateSecret(
            $guid,
            $suToken
        );

        $token = $this->personaClientTokens->obtainNewToken(
            $guid,
            $secret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $token);
    }
}
