<?php

use \Firebase\JWT\JWT;
use \Talis\Persona\Client\Tokens;
use \Talis\Persona\Client\ValidationResults;
use \Talis\Persona\Client\ScopesNotDefinedException;
use \Talis\Persona\Client\TokenValidationException;
use \Talis\Persona\Client\InvalidTokenException;
use \Doctrine\Common\Cache\ArrayCache;

$appRoot = dirname(dirname(dirname(__DIR__)));
if (!defined('APPROOT')) {
    define('APPROOT', $appRoot);
}

require_once $appRoot . '/test/unit/TestBase.php';

class TokensTest extends TestBase
{
    private $privateKey;
    private $publicKey;

    public function setUp()
    {
        parent::setUp();

        global $appRoot;
        $this->_wrongPrivateKey = file_get_contents("{$appRoot}/test/keys/wrong_private_key.pem");
        $this->privateKey = file_get_contents("{$appRoot}/test/keys/private_key.pem");
        $this->publicKey = file_get_contents("{$appRoot}/test/keys/public_key.pem");
    }

    public function testEmptyConfigThrowsException()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'invalid configuration'
        );
        $personaClient = new Tokens([]);
    }

    public function testMissingRequiredConfigParamsThrowsException()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'invalid configuration'
        );
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest',
                'persona_host' => null,
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testValidConfigDoesNotThrowException()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }


    public function testUseCacheFalseOnObtainToken()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['personaObtainNewToken'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $mockClient->expects($this->once())
            ->method('personaObtainNewToken')
            ->will($this->returnValue([
                'access_token' => 'foo',
                'expires' => '100',
                'scopes' => 'su'
            ]));

        $mockClient->obtainNewToken(
            'client_id',
            'client_secret',
            ['useCache' => false]
        );
    }

    public function testObtainToken()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['personaObtainNewToken'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $expectedToken = [
            'access_token' => 'foo',
            'expires_in' => '100',
            'scopes' => 'su'
        ];

        $cacheKey = 'obtain_token:' . hash_hmac('sha256', 'client_id', 'client_secret');

        $mockClient->expects($this->once())
            ->method('personaObtainNewToken')
            ->will($this->returnValue($expectedToken));

        $token = $mockClient->obtainNewToken('client_id', 'client_secret');
        $this->assertEquals($token['access_token'], 'foo');
    }

    /**
     * If the JWT doesn't include the user's scopes, retrieve
     * them from Persona
     */
    public function testPersonaFallbackOnJWTEmptyScopes()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            [
                'getCacheClient',
                'personaObtainNewToken',
                'cacheToken',
                'retrieveJWTCertificate',
                'performRequest',
            ],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $jwt = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 60 * 60,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopeCount' => 30,
            ],
            $this->privateKey,
            'RS256'
        );

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue(true));

        $result = $mockClient->validateToken(
            [
                'access_token' => $jwt,
                'scope' => 'su',
            ]
        );

        $this->assertEquals(ValidationResults::SUCCESS, $result);
    }

    /**
     * A expired token should fail
     */
    public function testJWTExpiredToken()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['retrieveJWTCertificate'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $jwt = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() - 50,
                'nbf' => time() - 100,
                'audience' => 'standard_user',
                'scopes' => ['su'],
            ],
            $this->privateKey,
            'RS256'
        );

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $result = $mockClient->validateToken(
            [
                'access_token' => $jwt,
                'scope' => 'su',
            ]
        );

        $this->assertEquals(ValidationResults::INVALID_TOKEN, $result);
    }

    /**
     * Test that if the token uses a not before assertion
     * that we cannot use the token before a given time
     */
    public function testJWTNotBeforeToken()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['retrieveJWTCertificate'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $jwt = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 101,
                'nbf' => time() + 100,
                'audience' => 'standard_user',
                'scopes' => ['su'],
            ],
            $this->privateKey,
            'RS256'
        );

        $mockClient
            ->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $result = $mockClient->validateToken(
            [
                'access_token' => $jwt,
                'scope' => 'su',
            ]
        );

        $this->assertEquals(ValidationResults::INVALID_TOKEN, $result);
    }

    /**
     * Using the wrong certificate should fail the tokens
     */
    public function testJWTInvalidPublicCert()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['retrieveJWTCertificate'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $jwt = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 100,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopes' => ['su'],
            ],
            $this->_wrongPrivateKey,
            'RS256'
        );

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->privateKey));

        $this->assertEquals(
            ValidationResults::INVALID_PUBLIC_KEY,
            $mockClient->validateToken(['access_token' => $jwt, 'scope' => 'su'])
        );
    }

    /**
     * HTTP endpoint returns unexpected status code
     */
    public function testReturnUnexpectedStatusCode()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['getHTTPClient'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $plugin = new Guzzle\Plugin\Mock\MockPlugin();
        $plugin->addResponse(new Guzzle\Http\Message\Response(202));
        $httpClient = new Guzzle\Http\Client();
        $httpClient->addSubscriber($plugin);

        $jwt = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 100,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopeCount' => 10,
            ],
            $this->privateKey,
            'RS256'
        );

        $mockClient->expects($this->once())
            ->method('getHTTPClient')
            ->will($this->returnValue($httpClient));

        try {
            $mockClient->validateToken(
                [
                    'access_token' => $jwt,
                    'scope' => 'su',
                ]
            );

            $this->fail('Exception not thrown');
        } catch (\Exception $exception) {
            $this->assertEquals(202, $exception->getCode());
        }
    }

    /**
     * Retrieving a token with the same credentials should be cached
     * @return null
     */
    public function testObtainCachedToken()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['getHTTPClient'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $accessToken = json_encode(
            [
                'access_token' => JWT::encode(
                    [
                        'jwtid' => time(),
                        'exp' => time() + 100,
                        'nbf' => time() - 1,
                        'audience' => 'standard_user',
                        'scopeCount' => 10,
                    ],
                    $this->privateKey,
                    'RS256'
                ),
                'expires_in' => 100,
                'token_type' => 'bearer',
                'scope' => 'su see_my_strong id',
            ]
        );

        $plugin = new Guzzle\Plugin\Mock\MockPlugin();
        $plugin->addResponse(
            new Guzzle\Http\Message\Response(200, null, $accessToken)
        );
        $httpClient = new Guzzle\Http\Client();
        $httpClient->addSubscriber($plugin);

        $mockClient->expects($this->once())
            ->method('getHTTPClient')
            ->will($this->returnValue($httpClient));

        $tokenDetails = $mockClient->obtainNewToken('id', 'secret');
        $this->assertArrayHasKey('access_token', $tokenDetails, 'should contain access_token');
        $this->assertArrayHasKey('expires_in', $tokenDetails, 'should contain expires_in');
        $this->assertArrayHasKey('token_type', $tokenDetails, 'should contain token type');
        $this->assertArrayHasKey('scope', $tokenDetails, 'should contain scope');
        $this->assertGreaterThan(0, $tokenDetails['expires_in']);
        $this->assertEquals('bearer', strtolower($tokenDetails['token_type']));

        $scopes = explode(' ', $tokenDetails['scope']);
        $this->assertContains('su', $scopes);
        $this->assertContains('id', $scopes);

        $cachedTokenDetails = $mockClient->obtainNewToken('id', 'secret');
        $this->assertEquals($cachedTokenDetails, $tokenDetails);
    }

    public function testRemoteValidationCallsUseSuScopeCheckForSu()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['validateTokenUsingJWT', 'makePersonaHttpRequest'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $encodedToken = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 100,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopeCount' => 50,
            ],
            $this->privateKey,
            'RS256'
        );

        $expectedValidationUrl = $this->versionedPersonaHost()
            . '/oauth/tokens/'
            . $encodedToken
            . '?scope=su';

        $mockClient
            ->expects($this->once())
            ->method('validateTokenUsingJWT')
            ->will(
                $this->throwException(
                    new ScopesNotDefinedException('too many scopes')
                )
            );

        $mockClient
            ->expects($this->once())
            ->method('makePersonaHttpRequest')
            ->with($expectedValidationUrl)
            ->will(
                $this->throwException(
                    new TokenValidationException(
                        'nope',
                        ValidationResults::INVALID_TOKEN
                    )
                )
            );

        $this->assertEquals(
            ValidationResults::INVALID_TOKEN,
            $mockClient->validateToken(
                [
                    'access_token' => $encodedToken,
                    'scope' => 'su',
                ]
            )
        );
    }

    public function testRemoteValidationCallsMultipleScopes()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['validateTokenUsingJWT', 'makePersonaHttpRequest'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $encodedToken = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 100,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopeCount' => 50,
            ],
            $this->privateKey,
            'RS256'
        );

        $expectedValidationUrl = $this->versionedPersonaHost() . '/oauth/tokens/'
            . $encodedToken
            . '?scope=scope1%2Cscope2';

        $mockClient
            ->expects($this->once())
            ->method('validateTokenUsingJWT')
            ->will(
                $this->throwException(
                    new ScopesNotDefinedException('too many scopes')
                )
            );

        $mockClient
            ->expects($this->once())
            ->method('makePersonaHttpRequest')
            ->with($this->equalTo($expectedValidationUrl))
            ->will(
                $this->throwException(
                    new TokenValidationException(
                        'nope',
                        ValidationResults::INVALID_TOKEN
                    )
                )
            );

        $this->assertEquals(
            ValidationResults::INVALID_TOKEN,
            $mockClient->validateToken(
                [
                    'access_token' => $encodedToken,
                    'scope' => ['scope1', 'scope2'],
                ]
            )
        );
    }

    public function testRemoteValidationCallsMultipleScopesWithSu()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            ['validateTokenUsingJWT', 'makePersonaHttpRequest'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $encodedToken = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 100,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopeCount' => 50,
            ],
            $this->privateKey,
            'RS256'
        );

        $expectedValidationUrl = $this->versionedPersonaHost()
            . '/oauth/tokens/'
            . $encodedToken
            . '?scope=scope1%2Csu%2Cscope2';

        $mockClient
            ->expects($this->once())
            ->method('validateTokenUsingJWT')
            ->will(
                $this->throwException(
                    new ScopesNotDefinedException('too many scopes')
                )
            );

        $mockClient
            ->expects($this->once())
            ->method('makePersonaHttpRequest')
            ->with($this->equalTo($expectedValidationUrl))
            ->will(
                $this->throwException(
                    new TokenValidationException(
                        'blah',
                        ValidationResults::INVALID_TOKEN
                    )
                )
            );

        $this->assertEquals(
            ValidationResults::INVALID_TOKEN,
            $mockClient->validateToken(
                [
                    'access_token' => $encodedToken,
                    'scope' => ['scope1', 'su', 'scope2'],
                ]
            )
        );
    }

    public function testLocalValidationCallsMultipleScopes()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            [ 'retrieveJWTCertificate' ],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $jwt = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 100,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopes' => ['invalid1', 'scope2'],
            ],
            $this->privateKey,
            'RS256'
        );

        $result = $mockClient->validateToken(
            [
                'access_token' => $jwt,
                'scope' => ['scope1', 'scope2'],
            ]
        );

        $this->assertEquals(ValidationResults::SUCCESS, $result);
    }

    public function testLocalValidationCallsMultipleScopesWithSu()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            [ 'retrieveJWTCertificate' ],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $jwt = JWT::encode(
            [
                'jwtid' => time(),
                'exp' => time() + 100,
                'nbf' => time() - 1,
                'audience' => 'standard_user',
                'scopes' => ['invalid1', 'scope2'],
            ],
            $this->privateKey,
            'RS256'
        );

        $result = $mockClient->validateToken(
            [
                'access_token' => $jwt,
                'scope' => ['scope1', 'scope2', 'su'],
            ]
        );

        $this->assertEquals(ValidationResults::SUCCESS, $result);
    }

    public function testUserAgentAllowsAnyChars()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest//.1',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testUserAgentFailsWithDoubleSpace()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'user agent format is not valid'
        );

        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest//.1  (blah)',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }


    public function testBasicUserAgent()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testUserAgentWithVersionNumber()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest/1.09',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testUserAgentWithVersionHash()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest/1723-9095ba4',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testUserAgentWithVersionNumberWithComment()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest/3.02 (commenting; here)',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testUserAgentWithVersionHashWithComment()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest/13f3-00934fa4 (commenting; with; hash)',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testBasicUserAgentWithComment()
    {
        $personaClient = new Tokens(
            [
                'userAgent' => 'unittest (comment; with; basic; name)',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
    }

    public function testListScopes()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            [ 'retrieveJWTCertificate' ],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $accessToken = [
            'access_token' => $jwt = JWT::encode(
                [
                    'jwtid' => time(),
                    'exp' => time() + 100,
                    'nbf' => time() - 1,
                    'audience' => 'standard_user',
                    'scopes' => ['scope1', 'scope2'],
                ],
                $this->privateKey,
                'RS256'
            ),
        ];

        $scopes = $mockClient->listScopes($accessToken);
        $this->assertEquals(['scope1', 'scope2'], $scopes);
    }

    public function testListScopesInvalidDomain()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            [ 'retrieveJWTCertificate' ],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $accessToken = [
            'access_token' => JWT::encode(
                [
                    'jwtid' => time(),
                    'exp' => time() + 100,
                    'nbf' => time() - 1,
                    'audience' => 'standard_user',
                    // missing scopes attribute
                ],
                $this->privateKey,
                'RS256'
            ),
        ];

        $this->setExpectedException(InvalidTokenException::class);
        $mockClient->listScopes($accessToken);
    }

    public function testListScopesScopeCount()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            [
                'retrieveJWTCertificate',
                'makePersonaHttpRequest',
            ],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $accessToken = [
            'access_token' => JWT::encode(
                [
                    'jwtid' => time(),
                    'exp' => time() + 100,
                    'nbf' => time() - 1,
                    'audience' => 'standard_user',
                    'scopeCount' => 10,
                ],
                $this->privateKey,
                'RS256'
            ),
        ];

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $mockClient->expects($this->once())
            ->method('makePersonaHttpRequest')
            ->with("localhost/3/oauth/tokens/{$accessToken['access_token']}")
            ->willReturn([
                'expires' => time() + 1000,
                'access_token' => $accessToken['access_token'],
                'scopes' => 'scope1 scope2',
            ]);

        $scopes = $mockClient->listScopes($accessToken);
        $this->assertEquals(['scope1', 'scope2'], $scopes);
    }

    public function testListScopesScopeCountInvalidDomain()
    {
        $mockClient = $this->getMock(
            'Talis\Persona\Client\Tokens',
            [
                'retrieveJWTCertificate',
                'makePersonaHttpRequest',
            ],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $accessToken = [
            'access_token' => JWT::encode(
                [
                    'jwtid' => time(),
                    'exp' => time() + 100,
                    'nbf' => time() - 1,
                    'audience' => 'standard_user',
                    'scopeCount' => 10,
                ],
                $this->privateKey,
                'RS256'
            ),
        ];

        $mockClient->expects($this->once())
            ->method('retrieveJWTCertificate')
            ->will($this->returnValue($this->publicKey));

        $mockClient->expects($this->once())
            ->method('makePersonaHttpRequest')
            ->with("localhost/3/oauth/tokens/{$accessToken['access_token']}")
            ->willReturn([
                'expires' => time() + 1000,
                'access_token' => $accessToken['access_token'],
                // missing scopes
            ]);

        $this->setExpectedException(InvalidTokenException::class);
        $mockClient->listScopes($accessToken);
    }
}
