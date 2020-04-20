<?php

namespace test\integration\Persona;

use Doctrine\Common\Cache\ArrayCache;
use Talis\Persona\Client\Tokens;
use Talis\Persona\Client\ValidationResults;
use test\TestBase;

class TokensIntegrationTest extends TestBase
{

    /**
     * @var Talis\Persona\Client\Tokens
     */
    private $personaClient;
    private $clientId;
    private $clientSecret;

    public function setUp()
    {
        parent::setUp();
        $personaConf = $this->getPersonaConfig();
        $this->clientId = $personaConf['oauthClient'];
        $this->clientSecret = $personaConf['oauthSecret'];

        $this->personaCache = new ArrayCache();
        $this->personaClient = new Tokens(
            [
                'userAgent' => 'integrationtest',
                'persona_host' => $personaConf['host'],
                'cacheBackend' => $this->personaCache,
            ]
        );
    }

    public function testObtainNewToken()
    {
        $tokenDetails = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails, 'should contain access_token');
        $this->assertArrayHasKey('expires_in', $tokenDetails, 'should contain expires_in');
        $this->assertArrayHasKey('token_type', $tokenDetails, 'should contain token type');
        $this->assertArrayHasKey('scope', $tokenDetails, 'should contain scope');
        $this->assertGreaterThan(0, $tokenDetails['expires_in']);
        $this->assertEquals('bearer', strtolower($tokenDetails['token_type']));

        $scopes = explode(' ', $tokenDetails['scope']);
        $this->assertContains('su', $scopes);
        $this->assertContains($this->clientId, $scopes);
    }

    public function testObtainNewTokenWithValidScope()
    {
        $tokenDetails = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['scope' => $this->clientId, 'useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails, 'should contain access_token');
        $this->assertArrayHasKey('expires_in', $tokenDetails, 'should contain expires_in');
        $this->assertArrayHasKey('token_type', $tokenDetails, 'should contain token type');
        $this->assertArrayHasKey('scope', $tokenDetails, 'should contain scope');
        $this->assertGreaterThan(0, $tokenDetails['expires_in']);
        $this->assertEquals('bearer', strtolower($tokenDetails['token_type']));
        $this->assertEquals($this->clientId, $tokenDetails['scope']);
    }

    public function testObtainNewTokenThrowsExceptionIfNoCredentials()
    {
        $this->setExpectedException(
            'Exception',
            'You must specify clientId, and clientSecret to obtain a new token'
        );

        $tokenDetails = $this->personaClient->obtainNewToken(
            null,
            null,
            ['scope' => 'wibble', 'useCache' => false]
        );
    }

    public function testValidateTokenThrowsExceptionNoTokenToValidate()
    {
        // Should throw exception if you dont pass in a token to validate
        // AND it cant find a token on $_SERVER, $_GET or $_POST
        $this->setExpectedException('Exception', 'No OAuth token supplied');
        $this->personaClient->validateToken();
    }

    public function testValidateTokenReturnsFalseIfTokenIsNotValid()
    {
        $this->assertEquals(
            ValidationResults::INVALID_TOKEN,
            $this->personaClient->validateToken(['access_token' => 'my token'])
        );
    }

    public function testValidateTokenWithPersonaAndCache()
    {
        // here we obtain a new token and then immediately validate it
        $tokenDetails = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        // first validation call is validated by persona
        $this->assertEquals(
            ValidationResults::SUCCESS,
            $this->personaClient->validateToken(['access_token' => $token])
        );
    }

    public function testValidateTokenInGET()
    {
        // here we obtain a new token we want to validate
        $tokenDetails = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $_GET = ['access_token' => $token];

        // first validation call is validated by persona
        $this->assertEquals(
            ValidationResults::SUCCESS,
            $this->personaClient->validateToken()
        );
    }

    public function testValidateTokenInPOST()
    {
        // here we obtain a new token we want to validate
        $tokenDetails = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $_POST = ['access_token' => $token];
        // first validation call is validated by persona
        $this->assertEquals(
            ValidationResults::SUCCESS,
            $this->personaClient->validateToken()
        );
    }


    public function testValidateTokenInSERVER()
    {
        // here we obtain a new token we want to validate
        $tokenDetails = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        $_SERVER = ['HTTP_BEARER' => 'Bearer ' . $token];

        // first validation call is validated by persona
        $this->assertEquals(
            ValidationResults::SUCCESS,
            $this->personaClient->validateToken()
        );
    }

    public function testValidateScopedToken()
    {
        // here we obtain a new token and then immediately validate it
        $tokenDetails = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            [
                'scope' => $this->clientId,
                'useCache' => false,
            ]
        );

        $this->assertArrayHasKey('access_token', $tokenDetails);
        $token = $tokenDetails['access_token'];

        // first validation call is validated by persona
        $this->assertEquals(
            ValidationResults::SUCCESS,
            $this->personaClient->validateToken(
                [
                    'access_token' => $token,
                    'scope' => $this->clientId,
                ]
            )
        );
    }

    public function testListScopes()
    {
        $token = $this->personaClient->obtainNewToken(
            $this->clientId,
            $this->clientSecret,
            ['useCache' => false]
        );

        $scopes = $this->personaClient->listScopes($token);
        $this->assertNotEmpty($scopes);
        $this->assertContains($this->clientId, $scopes);
    }
}
