<?php

namespace test\unit\Persona;

use Firebase\JWT\JWT;
use Talis\Persona\Client\Tokens;
use Talis\Persona\Client\ValidationResults;
use Talis\Persona\Client\ScopesNotDefinedException;
use Talis\Persona\Client\TokenValidationException;
use Talis\Persona\Client\InvalidTokenException;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use test\TestBase;

class TokensTest extends TestBase
{
    private $wrongPrivateKey;
    private $privateKey;
    private $publicKey;
    private $cacheBackend;

    /**
     * @before
     */
    public function initializeCache()
    {
        $this->cacheBackend = new ArrayCache();
    }

    /**
     * @before
     */
    public function initializeKeys()
    {
        $this->wrongPrivateKey = file_get_contents(APPROOT . '/test/keys/wrong_private_key.pem');
        $this->privateKey = file_get_contents(APPROOT . '/test/keys/private_key.pem');
        $this->publicKey = file_get_contents(APPROOT . '/test/keys/public_key.pem');
    }

    public function testEmptyConfigThrowsException()
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
            'invalid configuration'
        );
        $personaClient = new Tokens([]);
    }

    public function testMissingRequiredConfigParamsThrowsException()
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
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

    public function testPersonaFallbackWhenUnableToGetPublicCert()
    {
        $mockClient = $this->getMockTokensClient(
            [
                'getCacheClient',
                'personaObtainNewToken',
                'retrieveJWTCertificate',
                'cacheToken',
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
            ->will($this->returnValue(null));

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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
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
            $this->wrongPrivateKey,
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
        $mockClient = $this->getMockTokensClient(
            ['getHTTPClient', 'validateTokenUsingJWT'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ]
        );

        $httpClient = $this->getMockHttpClient([
            new \GuzzleHttp\Psr7\Response(202),
        ]);

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

        $mockClient->expects($this->once())
            ->method('validateTokenUsingJWT')
            ->will(
                $this->throwException(
                    new ScopesNotDefinedException('too many scopes')
                )
            );

        $result = $mockClient->validateToken(
            [
                'access_token' => $jwt,
                'scope' => 'su',
            ]
        );

        $this->assertEquals(ValidationResults::UNKNOWN, $result);
    }

    /**
     * Retrieving a token with the same credentials should be cached
     * @return null
     */
    public function testObtainCachedToken()
    {
        $mockClient = $this->getMockTokensClient(
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

        $httpClient = $this->getMockHttpClient([
            new \GuzzleHttp\Psr7\Response(200, [], $accessToken),
        ]);

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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
            ['retrieveJWTCertificate'],
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
        $mockClient = $this->getMockTokensClient(
            ['retrieveJWTCertificate'],
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
            InvalidArgumentException::class,
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
        $mockClient = $this->getMockTokensClient(
            ['retrieveJWTCertificate'],
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
        $mockClient = $this->getMockTokensClient(
            ['retrieveJWTCertificate'],
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
        $mockClient = $this->getMockTokensClient(
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
        $mockClient = $this->getMockTokensClient(
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

    /**
     * Simulates issue with Redis
     */
    public function testCachedTokenFailure()
    {
        /** @var MockObject&FilesystemCache */
        $cacheBackend = $this->getMockBuilder(FilesystemCache::class)
            ->disableOriginalConstructor()
            ->setMethods(['doFetch'])
            ->getMock();

        $cacheBackend->expects($this->atLeastOnce())
            ->method('doFetch')
            ->will($this->throwException(new \Exception('I failed')));

        $tokens = $this->getMockTokensClient(
            ['personaObtainNewToken'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $cacheBackend,
                ]
            ]
        );

        $tokens->expects($this->once())
            ->method('personaObtainNewToken')
            ->willReturn(
                [
                    'access_token' => 'foo',
                    'expires_in' => '100',
                    'scopes' => 'su'
                ]
            );

        $tokens->obtainNewToken('id', 'secret');
    }

    public function testCachingTokenFailure()
    {
        /** @var MockObject&FilesystemCache */
        $cacheBackend = $this->getMockBuilder(FilesystemCache::class)
            ->disableOriginalConstructor()
            ->setMethods(['doFetch', 'doSave'])
            ->getMock();

        $cacheBackend->expects($this->atLeastOnce())
            ->method('doFetch')
            ->willReturn(null);

        $cacheBackend->expects($this->once())
            ->method('doSave')
            ->will($this->throwException(new \Exception('cannot save')));

        $tokens = $this->getMockTokensClient(
            ['personaObtainNewToken'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $cacheBackend,
                ]
            ]
        );

        $tokens->expects($this->once())
            ->method('personaObtainNewToken')
            ->willReturn(
                [
                    'access_token' => 'foo',
                    'expires_in' => '100',
                    'scopes' => 'su'
                ]
            );

        $tokens->obtainNewToken('id', 'secret');
    }

    public function testCachedCertificateFailure()
    {
        /** @var MockObject&FilesystemCache */
        $cacheBackend = $this->getMockBuilder(FilesystemCache::class)
            ->disableOriginalConstructor()
            ->setMethods(['doFetch'])
            ->getMock();

        $cacheBackend->expects($this->atLeastOnce())
            ->method('doFetch')
            ->will($this->throwException(new \Exception('I failed')));

        $tokens = $this->getMockTokensClient(
            ['retrievePublicKeyFromPersona'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $cacheBackend,
                ]
            ]
        );

        $tokens->expects($this->once())
            ->method('retrievePublicKeyFromPersona')
            ->willReturn('cert');

        $tokens->retrieveJWTCertificate();
    }

    public function testCachingCertificateFailure()
    {
        /** @var MockObject&FilesystemCache */
        $cacheBackend = $this->getMockBuilder(FilesystemCache::class)
            ->disableOriginalConstructor()
            ->setMethods(['doSave', 'doFetch'])
            ->getMock();

        $cacheBackend->expects($this->atLeastOnce())
            ->method('doSave')
            ->will($this->throwException(new \Exception('I failed')));

        $cacheBackend->expects($this->atLeastOnce())
            ->method('doFetch')
            ->willReturn(null);

        $tokens = $this->getMockTokensClient(
            ['retrievePublicKeyFromPersona'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $cacheBackend,
                ]
            ]
        );

        $tokens->expects($this->once())
            ->method('retrievePublicKeyFromPersona')
            ->willReturn('cert');

        $tokens->retrieveJWTCertificate();
    }

    public function testRetrieveJWTCertificateCaching()
    {
        /** @var MockObject&FilesystemCache */
        $cacheBackend = $this->getMockBuilder(FilesystemCache::class)
            ->disableOriginalConstructor()
            ->setMethods(['doFetch', 'doSave'])
            ->getMock();

        $cacheBackend->expects($this->atLeastOnce())
            ->method('doFetch')
            ->willReturn(null);

        $cacheBackend->expects($this->exactly(2))
            ->method('doSave')
            ->withConsecutive(
                ['[composer_version][1]', '0.0.1', 3600],
                ['[cert_pub][1]', 'cert', 300]
            );

        $httpClient = $this->getMockHttpClient([
            new \GuzzleHttp\Psr7\Response(200, [], 'cert'),
        ]);

        $tokens = $this->getMockTokensClient(
            ['getHTTPClient', 'getVersionFromComposeFile'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $cacheBackend,
                ]
            ]
        );

        $tokens->expects($this->once())
            ->method('getHTTPClient')
            ->willReturn($httpClient);

        $tokens->expects($this->once())
            ->method('getVersionFromComposeFile')
            ->willReturn('0.0.1');

        $tokens->retrieveJWTCertificate();
    }

    public function testRetrieveJWTCertificateCachingFetchingFailure()
    {
        /** @var MockObject&FilesystemCache */
        $cacheBackend = $this->getMockBuilder(FilesystemCache::class)
            ->disableOriginalConstructor()
            ->setMethods(['doFetch', 'doSave'])
            ->getMock();

        $cacheBackend->expects($this->exactly(3))
            ->method('doFetch')
            ->will(
                $this->onConsecutiveCalls(
                    $this->throwException(new \Exception('blah')),
                    null,
                    $this->throwException(new \Exception('blah'))
                )
            );

        $cacheBackend->expects($this->exactly(2))
            ->method('doSave')
            ->withConsecutive(
                ['[composer_version][1]', '0.0.1', 3600],
                ['[cert_pub][1]', 'cert', 300]
            );

        $httpClient = $this->getMockHttpClient([
            new \GuzzleHttp\Psr7\Response(200, [], 'cert'),
        ]);

        $tokens = $this->getMockTokensClient(
            ['getHTTPClient', 'getVersionFromComposeFile'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $cacheBackend,
                ]
            ]
        );

        $tokens->expects($this->once())
            ->method('getHTTPClient')
            ->willReturn($httpClient);

        $tokens->expects($this->once())
            ->method('getVersionFromComposeFile')
            ->willReturn('0.0.1');

        $tokens->retrieveJWTCertificate();
    }

    public function testRetrieveJWTCertificateCachingSaveFailure()
    {
        /** @var MockObject&FilesystemCache */
        $cacheBackend = $this->getMockBuilder(FilesystemCache::class)
            ->disableOriginalConstructor()
            ->setMethods(['doFetch', 'doSave'])
            ->getMock();

        $cacheBackend->expects($this->atLeastOnce())
            ->method('doFetch');

        $cacheBackend->expects($this->exactly(2))
            ->method('doSave')
            ->withConsecutive(
                ['[composer_version][1]', '0.0.1', 3600],
                ['[cert_pub][1]', 'cert', 300]
            )
            ->will($this->throwException(new \Exception('oh no')));

        $httpClient = $this->getMockHttpClient([
            new \GuzzleHttp\Psr7\Response(200, [], 'cert'),
        ]);

        $tokens = $this->getMockTokensClient(
            ['getHTTPClient', 'getVersionFromComposeFile'],
            [
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $cacheBackend,
                ]
            ]
        );

        $tokens->expects($this->once())
            ->method('getHTTPClient')
            ->willReturn($httpClient);

        $tokens->expects($this->once())
            ->method('getVersionFromComposeFile')
            ->willReturn('0.0.1');

        $tokens->retrieveJWTCertificate();
    }

    /**
     * @covers \Talis\Persona\Client\Tokens::getSubjectIdFromToken
     */
    public function testGetSubjectIdFromTokenReturnsClientIdFromToken()
    {
        $mockClient = $this->getMockTokensClientWithFakeCertificate();

        $fakeClientId = 'this-is-a-fake-client-id';
        $accessToken = $this->getFakeJWT([
            'sub' => $fakeClientId,
            'scopes' => [$fakeClientId]
        ]);

        $clientIdFromToken = $mockClient->getSubjectIdFromToken($accessToken);
        $this->assertEquals($fakeClientId, $clientIdFromToken);
    }

    /**
     * @covers \Talis\Persona\Client\Tokens::getSubjectIdFromToken
     */
    public function testGetSubjectIdFromTokenThrowsExceptionIfTokenContainsNoSubClaim()
    {
        $mockClient = $this->getMockTokensClientWithFakeCertificate();

        $accessToken = $this->getFakeJWT([
            'sub' => null
        ]);

        $this->setExpectedException(InvalidTokenException::class);
        $mockClient->getSubjectIdFromToken($accessToken);
    }

    /**
     * Gets the client with mocked HTTP responses.
     *
     * @param \GuzzleHttp\Psr7\Response[] $responses The responses
     * @return \GuzzleHttp\Client The client.
     */
    private function getMockHttpClient(array $responses = [])
    {
        $mockHandler = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        return $httpClient;
    }

    /**
     * Create a mock `Tokens` client, with the cache backend and certificate defined in instance variables
     * for this class.
     * @return \Talis\Persona\Client\Tokens
     */
    private function getMockTokensClientWithFakeCertificate()
    {
        $mockClient = $this->getMockTokensClient(
            ['retrieveJWTCertificate'],
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
            ->willReturn($this->publicKey);

        return $mockClient;
    }

    /**
     * @param string[] $methods
     * @param array $arguments
     * @return MockObject&\Talis\Persona\Client\Tokens
     */
    private function getMockTokensClient(array $methods, array $arguments)
    {
        /** @var MockObject&\Talis\Persona\Client\Tokens */
        $mockClient = $this->getMockBuilder(\Talis\Persona\Client\Tokens::class)
            ->setMethods($methods)
            ->setConstructorArgs($arguments)
            ->getMock();

        $mockClient->setLogger(new \Psr\Log\NullLogger());

        return $mockClient;
    }

    /**
     * Creates a fake JWT with realistic-looking claim data.
     *
     * @param array $claims An array of JWT claims.
     * @return string An encoded JWT encapsulating the specified claims
     */
    private function getFakeJWT(array $claims = [])
    {
        $now = time();
        $fakeSubjectClientId = "fake-sub-client-id-{$now}";
        $fakeAudienceClientId = "fake-aud-client-id-{$now}";
        $defaultClaims = [
            'aud' => $fakeAudienceClientId,
            'exp' => $now + 100,
            'iat' => $now,
            'jti' => $now,
            'scopes' => [$fakeSubjectClientId],
            'sub' => $fakeSubjectClientId
        ];
        $claimsWithDefaults = array_merge($defaultClaims, $claims);
        return JWT::encode($claimsWithDefaults, $this->privateKey, 'RS256');
    }
}
