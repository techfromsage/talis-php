<?php

namespace test\unit\Persona;

use Talis\Persona\Client\Login;
use test\TestBase;

class LoginTest extends TestBase
{
    public function testRequireAuthInvalidProvider()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid provider');

        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->requireAuth(['test'], 'appid', 'appsecret');
    }

    public function testRequireAuthInvalidAppId()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid appId');

        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->requireAuth('trapdoor', ['appid'], 'appsecret');
    }

    public function testRequireAuthInvalidAppSecret()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid appSecret');

        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->requireAuth('trapdoor', 'appid', ['appsecret']);
    }

    public function testRequireAuthNoRedirectUri()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Login', ['login'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('login')
            ->will($this->returnValue(null));

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret');
        $this->assertEquals('appid', $_SESSION[Login::LOGIN_PREFIX . ':loginAppId']);
        $this->assertEquals('appsecret', $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertEquals('trapdoor', $_SESSION[Login::LOGIN_PREFIX . ':loginProvider']);
    }

    public function testRequireAuthInvalidRedirectUri()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid redirectUri');

        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->requireAuth('trapdoor', 'appid', 'appsecret', ['redirectUri']);
    }

    public function testRequireAuthWithRedirectUri()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Login', ['login'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('login')
            ->will($this->returnValue(null));

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret', 'redirecturi');

        $this->assertEquals('appid', $_SESSION[Login::LOGIN_PREFIX . ':loginAppId']);
        $this->assertEquals('appsecret', $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertEquals('trapdoor', $_SESSION[Login::LOGIN_PREFIX . ':loginProvider']);
    }

    public function testRequireAuthAlreadyLoggedIn()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Login', ['isLoggedIn', 'login'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('isLoggedIn')
            ->will($this->returnValue(true));
        $mockClient->expects($this->never())
            ->method('login');

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret');
        $this->assertFalse(isset($_SESSION));
    }

    public function testRequireAuthNotAlreadyLoggedIn()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Login', ['isLoggedIn', 'login'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('isLoggedIn')
            ->will($this->returnValue(false));
        $mockClient->expects($this->once())
            ->method('login')
            ->will($this->returnValue(true));

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret', 'redirect');

        $this->assertEquals('appid', $_SESSION[Login::LOGIN_PREFIX . ':loginAppId']);
        $this->assertEquals('appsecret', $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertEquals('trapdoor', $_SESSION[Login::LOGIN_PREFIX . ':loginProvider']);
    }

    // validateAuth tests
    public function testValidateAuthThrowsExceptionWhenPayloadDoesNotContainSignature()
    {
        $this->setExpectedException('Exception', 'Signature not set');
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_POST['persona:payload'] = '';
        $personaClient->validateAuth();
    }

    public function testValidateAuthThrowsExceptionWhenPayloadDoesNotExist()
    {
        $this->setExpectedException('Exception', 'Payload not set');
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_POST['persona:signature'] = 'DummySignature';
        $personaClient->validateAuth();
    }

    public function testValidateAuthThrowsExceptionWhenPayloadIsAString()
    {
        $this->setExpectedException('Exception', 'Payload not json');
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_POST['persona:signature'] = 'DummySignature';
        $_POST['persona:payload'] = 'YouShallNotPass';
        $personaClient->validateAuth();
    }

    public function testValidateAuthThrowsExceptionWhenPayloadIsMissingState()
    {
        $this->setExpectedException('Exception', 'Login state does not match');
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee';
        $_POST['persona:signature'] = 'DummySignature';
        $_POST['persona:payload'] = base64_encode(json_encode(['test' => 'YouShallNotPass']));
        $personaClient->validateAuth();
    }

    public function testValidateAuthPayloadMismatchingSignature()
    {
        $this->setExpectedException('Exception', 'Signature does not match');
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee';
        $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret';
        $payload = [
            'state' => 'Tennessee'
        ];

        $encodedPayload = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $encodedPayload, 'notmyappsecret');
        $_POST['persona:signature'] = $signature;

        $_POST['persona:payload'] = base64_encode(json_encode($payload));
        $personaClient->validateAuth();
    }

    public function testValidateAuthPayloadContainsStateAndSignatureNoOtherPayload()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee';
        $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret';
        $payload = [
            'state' => 'Tennessee'
        ];

        $encodedPayload = base64_encode(json_encode($payload));
        $_POST['persona:payload'] = $encodedPayload;

        $signature = hash_hmac('sha256', $encodedPayload, 'appsecret');
        $_POST['persona:signature'] = $signature;

        $this->assertTrue($personaClient->validateAuth());

        $this->assertEquals('appsecret', $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertArrayHasKey(Login::LOGIN_PREFIX . ':loginSSO', $_SESSION);
        $this->assertArrayHasKey('token', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('guid', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('gupid', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('profile', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('redirect', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']);
    }

    public function testValidateAuthPayloadContainsStateAndSignatureFullPayload()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee';
        $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret';
        $payload = [
            'token' => [
                'access_token' => '987',
                'expires_in' => 1800,
                'token_type' => 'bearer',
                'scope' => [
                    '919191'
                ]
            ],
            'guid' => '123',
            'gupid' => ['trapdoor:123'],
            'profile' => [
                'name' => 'Alex Murphy',
                'email' => 'alexmurphy@detroit.pd'
            ],
            'redirect' => 'http://example.com/wherever',
            'state' => 'Tennessee'
        ];

        $encodedPayload = base64_encode(json_encode($payload));
        $_POST['persona:payload'] = $encodedPayload;

        $signature = hash_hmac('sha256', $encodedPayload, 'appsecret');
        $_POST['persona:signature'] = $signature;

        $this->assertTrue($personaClient->validateAuth());

        $this->assertEquals('appsecret', $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertArrayHasKey(Login::LOGIN_PREFIX . ':loginSSO', $_SESSION);
        $this->assertArrayHasKey('token', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertEquals('987', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['token']['access_token']);
        $this->assertEquals(1800, $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['token']['expires_in']);
        $this->assertEquals('bearer', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['token']['token_type']);
        $this->assertEquals('919191', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['token']['scope'][0]);
        $this->assertEquals('123', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['guid']);
        $this->assertEquals('trapdoor:123', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['gupid'][0]);
        $this->assertArrayHasKey('profile', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertEquals('Alex Murphy', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['profile']['name']);
        $this->assertEquals('alexmurphy@detroit.pd', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['profile']['email']);
        $this->assertEquals('http://example.com/wherever', $_SESSION[Login::LOGIN_PREFIX . ':loginSSO']['redirect']);
    }

    public function testValidateAuthPayloadContainsStateAndSignatureFullPayloadCheckLoginIsCalled()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Login', ['isLoggedIn'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('isLoggedIn')
            ->will($this->returnValue(true));

        $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee';
        $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret';
        $payload = [
            'token' => [
                'access_token' => '987',
                'expires_in' => 1800,
                'token_type' => 'bearer',
                'scope' => [
                    '919191'
                ]
            ],
            'guid' => '123',
            'gupid' => ['trapdoor:123'],
            'profile' => [
                'name' => 'Alex Murphy',
                'email' => 'alexmurphy@detroit.pd'
            ],
            'redirect' => 'http://example.com/wherever',
            'state' => 'Tennessee'
        ];

        $encodedPayload = base64_encode(json_encode($payload));
        $_POST['persona:payload'] = $encodedPayload;

        $signature = hash_hmac('sha256', $encodedPayload, 'appsecret');
        $_POST['persona:signature'] = $signature;

        $mockClient->validateAuth();
    }

    public function testValidateAuthAfterRequireAuth()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Login', ['isLoggedIn', 'login'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->exactly(2))
            ->method('isLoggedIn')
            ->will($this->onConsecutiveCalls(false, true));

        $mockClient->expects($this->once())
            ->method('login')
            ->will($this->returnValue(['guid' => '123']));

        $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee';
        $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret';

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret');

        $payload = [
            'token' => [
                'access_token' => '987',
                'expires_in' => 1800,
                'token_type' => 'bearer',
                'scope' => [
                    '919191'
                ]
            ],
            'guid' => '123',
            'gupid' => ['trapdoor:123'],
            'profile' => [
                'name' => 'Alex Murphy',
                'email' => 'alexmurphy@detroit.pd'
            ],
            'redirect' => 'http://example.com/wherever',
            'state' => 'Tennessee'
        ];

        $encodedPayload = base64_encode(json_encode($payload));
        $_POST['persona:payload'] = $encodedPayload;

        $signature = hash_hmac('sha256', $encodedPayload, 'appsecret');
        $_POST['persona:signature'] = $signature;

        $this->assertTrue($mockClient->validateAuth());

        $this->assertEquals('123', $mockClient->getPersistentId());
        $this->assertEquals(['919191'], $mockClient->getScopes());
        $this->assertEquals('http://example.com/wherever', $mockClient->getRedirectUrl());
    }

    // getPersistentId tests
    public function testGetPersistentIdNoSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->assertFalse($personaClient->getPersistentId());
    }

    public function testGetPersistentIdNoGupidInSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = [];
        $this->assertFalse($personaClient->getPersistentId());
    }

    public function testGetPersistentIdNoLoginProviderInSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = [];
        $this->assertFalse($personaClient->getPersistentId());
    }

    public function testGetPersistentIdEmptyGupids()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginProvider'] = 'trapdoor';
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = ['gupid' => []];

        $this->assertFalse($personaClient->getPersistentId());
    }

    public function testGetPersistentIdNoMatchingGupid()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginProvider'] = 'trapdoor';
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = [
            'gupid' => [
                'google:123',
                'twitter:456'
            ]
        ];
        $this->assertFalse($personaClient->getPersistentId());
    }

    public function testGetPersistentIdFoundMatchingGupid()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginProvider'] = 'trapdoor';
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = [
            'gupid' => [
                'google:123',
                'trapdoor:456'
            ]
        ];
        $this->assertEquals('456', $personaClient->getPersistentId());
    }

    // getRedirectUrl tests
    public function testGetRedirectUrlNoSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->assertFalse($personaClient->getRedirectUrl());
    }

    public function testGetRedirectUrlNoRedirectInSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = [];
        $this->assertFalse($personaClient->getRedirectUrl());
    }

    public function testGetRedirectUrlFoundRedirectInSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = ['redirect' => 'http://example.com/path/to/redirect'];
        $this->assertEquals('http://example.com/path/to/redirect', $personaClient->getRedirectUrl());
    }

    // getScopes tests
    public function testGetScopesUserNoSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->assertFalse($personaClient->getScopes());
    }

    public function testGetScopesNoProfileInSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = [];
        $this->assertFalse($personaClient->getScopes());
    }

    public function testGetScopes()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = ['token' => ['scope' => ['919191']]];
        $this->assertEquals(['919191'], $personaClient->getScopes());
    }

    public function testGetProfileNoSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->assertEquals([], $personaClient->getProfile());
    }

    public function testGetProfileNoProfileInSession()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = [];
        $this->assertEquals([], $personaClient->getProfile());
    }

    public function testGetProfile()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $profile = ['name' => '', 'email' => ''];
        $_SESSION[Login::LOGIN_PREFIX . ':loginSSO'] = ['profile' => $profile];
        $this->assertEquals($profile, $personaClient->getProfile());
    }

    public function testRequireAuthRequireProfile()
    {
        $arguments = [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'http://localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ];

        $client = $this->getMock(
            'Talis\Persona\Client\Login',
            ['redirect', 'getLoginState'],
            $arguments
        );

        $client->expects($this->once())
            ->method('getLoginState')
            ->will($this->returnValue('loginState'));

        $client->expects($this->once())
            ->method('redirect')
            ->with(
                'http://' . $this->versionedPersonaHost() . '/auth/providers/trapdoor/login' .
                '?require=profile' .
                '&redirectUri=http%3A%2F%2Fexample.com' .
                '&state=loginState' .
                '&app=test_client'
            )
            ->will($this->returnValue(null));

        $client->requireAuth(
            'trapdoor',
            'test_client',
            'secret',
            'http://example.com',
            ['require' => 'profile']
        );
    }
}
