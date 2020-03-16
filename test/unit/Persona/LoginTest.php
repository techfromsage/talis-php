<?php

use Talis\Persona\Client\Login;

$appRoot = dirname(dirname(dirname(__DIR__)));
if (!defined('APPROOT')) {
    define('APPROOT', $appRoot);
}

require_once $appRoot . '/test/unit/TestBase.php';


class LoginTest extends TestBase
{
    public function testRequireAuthInvalidNonce()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid nonce');

        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->requireAuth(['trapdoor'], 'appid', 'appsecret', null, '', null);
    }

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
        $personaClient->requireAuth(['test'], 'appid', 'appsecret', 'nonce', '', null);
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
        $personaClient->requireAuth('trapdoor', ['appid'], 'appsecret', 'nonce');
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
        $personaClient->requireAuth('trapdoor', 'appid', ['appsecret'], 'nonce');
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

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret', 'nonce');
/* $this->cacheBackend->save('debug',['bob'=>'bob']); */
/* $debug = $this->cacheBackend->fetch('debug'); */
/* echo "\n\n== debug fetch =========\n\n"; */
/* var_dump($debug); */
/* echo "\n\n===========\n\n"; */

        $data = $this->cacheBackend->fetch('nonce');
/* echo "\n\n== data fetch =========\n\n"; */
/* var_dump($data); */
/* echo "\n\n===========\n\n"; */
        $this->assertEquals('appid', $data[Login::LOGIN_PREFIX . ':loginAppId']);
        $this->assertEquals('appsecret', $data[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertEquals('trapdoor', $data[Login::LOGIN_PREFIX . ':loginProvider']);
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
        $personaClient->requireAuth('trapdoor', 'appid', 'appsecret', 'nonce', ['redirectUri']);
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

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret', 'nonce', 'redirecturi');

        $data = $this->cacheBackend->fetch('nonce');
        $this->assertEquals('appid', $data[Login::LOGIN_PREFIX . ':loginAppId']);
        $this->assertEquals('appsecret', $data[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertEquals('trapdoor', $data[Login::LOGIN_PREFIX . ':loginProvider']);
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

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret', 'nonce');
        $data = $this->cacheBackend->fetch('nonce');
        $this->assertFalse($data);
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

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret', 'nonce', 'redirect');

        $data = $this->cacheBackend->fetch('nonce');
        $this->assertEquals('appid', $data[Login::LOGIN_PREFIX . ':loginAppId']);
        $this->assertEquals('appsecret', $data[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertEquals('trapdoor', $data[Login::LOGIN_PREFIX . ':loginProvider']);
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
        $personaClient->validateAuth('nonce');
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
        $personaClient->validateAuth('nonce');
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
        $personaClient->validateAuth('nonce');
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
        $personaClient->validateAuth('nonce');
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
        $personaClient->validateAuth('nonce');
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

        $simpleNonce = new \SoftSmart\Utilities\SimpleNonce();
        $nonceValues = $simpleNonce->generateNonce(Login::LOGIN_STATE_ACTION, [Login::NONCE_SALT]);

        $data = [
            Login::NONCE_TIMESTAMP => $nonceValues['timeStamp'],
            Login::LOGIN_PREFIX . ':loginAppSecret' => 'appsecret'
            // TODO login state?
            /* Login::LOGIN_PREFIX . ':loginState' = 'Tennessee'; */
        ];
        $this->cacheBackend->save($nonceValues['nonce'], $data);
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee'; */
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret'; */
        $payload = [
            'state' => 'Tennessee'
        ];

        $encodedPayload = base64_encode(json_encode($payload));
        $_POST['persona:payload'] = $encodedPayload;

        $signature = hash_hmac('sha256', $encodedPayload, 'appsecret');
        $_POST['persona:signature'] = $signature;

        $this->assertTrue($personaClient->validateAuth($nonceValues['nonce']));

        $data = $this->cacheBackend->fetch('nonce');
        $this->assertEquals('appsecret', $data[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertArrayHasKey(Login::LOGIN_PREFIX . ':loginSSO', $data);
        $this->assertArrayHasKey('token', $data[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('guid', $data[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('gupid', $data[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('profile', $data[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertArrayHasKey('redirect', $data[Login::LOGIN_PREFIX . ':loginSSO']);
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

        $simpleNonce = new \SoftSmart\Utilities\SimpleNonce();
        $nonceValues = $simpleNonce->generateNonce(Login::LOGIN_STATE_ACTION, [Login::NONCE_SALT]);

        $data = [
            Login::NONCE_TIMESTAMP => $nonceValues['timeStamp'],
            Login::LOGIN_PREFIX . ':loginAppSecret' => 'appsecret'
            // TODO login state?
            /* Login::LOGIN_PREFIX . ':loginState' = 'Tennessee'; */
        ];
        $this->cacheBackend->save($nonceValues['nonce'], $data);
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee'; */
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret'; */
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

        $this->assertTrue($personaClient->validateAuth('nonce'));

        $data = $this->cacheBackend->fetch('nonce');
        $this->assertEquals('appsecret', $data[Login::LOGIN_PREFIX . ':loginAppSecret']);
        $this->assertArrayHasKey(Login::LOGIN_PREFIX . ':loginSSO', $data);
        $this->assertArrayHasKey('token', $data[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertEquals('987', $data[Login::LOGIN_PREFIX . ':loginSSO']['token']['access_token']);
        $this->assertEquals(1800, $data[Login::LOGIN_PREFIX . ':loginSSO']['token']['expires_in']);
        $this->assertEquals('bearer', $data[Login::LOGIN_PREFIX . ':loginSSO']['token']['token_type']);
        $this->assertEquals('919191', $data[Login::LOGIN_PREFIX . ':loginSSO']['token']['scope'][0]);
        $this->assertEquals('123', $data[Login::LOGIN_PREFIX . ':loginSSO']['guid']);
        $this->assertEquals('trapdoor:123', $data[Login::LOGIN_PREFIX . ':loginSSO']['gupid'][0]);
        $this->assertArrayHasKey('profile', $data[Login::LOGIN_PREFIX . ':loginSSO']);
        $this->assertEquals('Alex Murphy', $data[Login::LOGIN_PREFIX . ':loginSSO']['profile']['name']);
        $this->assertEquals('alexmurphy@detroit.pd', $data[Login::LOGIN_PREFIX . ':loginSSO']['profile']['email']);
        $this->assertEquals('http://example.com/wherever', $data[Login::LOGIN_PREFIX . ':loginSSO']['redirect']);
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

        $data = [
            Login::LOGIN_PREFIX . ':loginAppSecret' => 'appsecret'
            // TODO login state?
            /* Login::LOGIN_PREFIX . ':loginState' = 'Tennessee'; */
        ];
        $this->cacheBackend->save('nonce', $data);
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee'; */
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret'; */
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

        $mockClient->validateAuth('nonce');
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

        $data = [
            Login::LOGIN_PREFIX . ':loginAppSecret' => 'appsecret'
            // TODO login state?
            /* Login::LOGIN_PREFIX . ':loginState' = 'Tennessee'; */
        ];
        $this->cacheBackend->save('nonce', $data);
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginState'] = 'Tennessee'; */
        /* $_SESSION[Login::LOGIN_PREFIX . ':loginAppSecret'] = 'appsecret'; */

        $mockClient->requireAuth('trapdoor', 'appid', 'appsecret', 'nonce');

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

        $this->assertTrue($mockClient->validateAuth('nonce'));

        $this->assertEquals('123', $mockClient->getPersistentId('nonce'));
        $this->assertEquals(['919191'], $mockClient->getScopes('nonce'));
        $this->assertEquals('http://example.com/wherever', $mockClient->getRedirectUrl('nonce'));
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
        $this->assertFalse($personaClient->getPersistentId('nonce'));
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
        $this->assertFalse($personaClient->getPersistentId('nonce'));
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
        $this->assertFalse($personaClient->getPersistentId('nonce'));
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

        $this->assertFalse($personaClient->getPersistentId('nonce'));
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
        $this->assertFalse($personaClient->getPersistentId('nonce'));
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
        $this->assertEquals('456', $personaClient->getPersistentId('nonce'));
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
        $this->assertFalse($personaClient->getRedirectUrl('nonce'));
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
        $data = [
            Login::LOGIN_PREFIX . ':loginSSO' => []
        ];
        $this->cacheBackend->save('nonce', $data);
        $this->assertFalse($personaClient->getRedirectUrl('nonce'));
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
        $data = [
            Login::LOGIN_PREFIX . ':loginSSO' => ['redirect' => 'http://example.com/path/to/redirect']
        ];
        $this->cacheBackend->save('nonce', $data);
        $this->assertEquals('http://example.com/path/to/redirect', $personaClient->getRedirectUrl('nonce'));
    }

    // getScopes tests
    public function testGetScopesUserNoDataInCache()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $this->assertFalse($personaClient->getScopes('nonce'));
    }

    public function testGetScopesNoProfileInCache()
    {
        $personaClient = new Login(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $data = [
            Login::LOGIN_PREFIX . ':loginSSO' => []
        ];
        $this->cacheBackend->save('nonce',$data);
        $this->assertFalse($personaClient->getScopes('nonce'));
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
        $data = [
            Login::LOGIN_PREFIX . ':loginSSO' => ['token' => ['scope' => ['919191']]]
        ];
        $this->cacheBackend->save('nonce',$data);
        $this->assertEquals(['919191'], $personaClient->getScopes('nonce'));
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
        $this->assertEquals([], $personaClient->getProfile('nonce'));
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
        $data = [
            Login::LOGIN_PREFIX . ':loginSSO' => []
        ];
        $this->cacheBackend->save('nonce',$data);
        $this->assertEquals([], $personaClient->getProfile('nonce'));
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
        $data = [
            Login::LOGIN_PREFIX . ':loginSSO' => ['profile' => $profile]
        ];
        $this->cacheBackend->save('nonce',$data);
        $this->assertEquals($profile, $personaClient->getProfile('nonce'));
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
            ['redirect', 'getLoginState', 'generateNonce'],
            $arguments
        );

        $client->expects($this->once())
            ->method('generateNonce')
            ->will($this->returnValue(['nonce' => 'returnedNonce', 'timeStamp' => 'bob']));

        $client->expects($this->once())
            ->method('redirect')
            ->with(
                'http://' . $this->versionedPersonaHost() . '/auth/providers/trapdoor/login' .
                '?require=profile' .
                '&redirectUri=http%3A%2F%2Fexample.com' .
                '&app=test_client' .
                '&nonce=returnedNonce'
            )
            ->will($this->returnValue(null));

        $client->requireAuth(
            'trapdoor',
            'test_client',
            'secret',
            'nonce',
            'http://example.com',
            ['require' => 'profile']
        );
    }
}
