<?php

namespace test\unit\Persona;

use Doctrine\Common\Cache\ArrayCache;
use Exception;
use InvalidArgumentException;
use Talis\Persona\Client\OAuthClients;
use PHPUnit\Framework\MockObject\MockObject;
use Talis\Persona\Client\InvalidPayloadException;
use test\TestBase;

class OAuthClientsTest extends TestBase
{
    private $cacheBackend;

    /**
     * @before
     */
    public function initializeCache()
    {
        $this->cacheBackend = new ArrayCache();
    }

    // Get oauth client tests
    public function testGetOAuthClientEmptyClientIdThrowsException()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid clientId');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->getOAuthClient('', '');
    }

    public function testGetOAuthClientEmptyTokenThrowsException()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid token');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->getOAuthClient('123', '');
    }

    public function testGetOAuthClientThrowsExceptionWhenClientNotFound()
    {
        $this->setExpectedException(Exception::class, 'Did not retrieve successful response code');
        /** @var MockObject&OAuthClients */
        $mockClient = $this->getMockBuilder(OAuthClients::class)
            ->setMethods(['personaGetOAuthClient'])
            ->setConstructorArgs([
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('personaGetOAuthClient')
            ->will($this->throwException(new \Exception('Did not retrieve successful response code')));

        $mockClient->getOAuthClient('123', '456');
    }

    public function testGetOAuthClientReturnsClientWhenGupidFound()
    {
        /** @var MockObject&OAuthClients */
        $mockClient = $this->getMockBuilder(OAuthClients::class)
            ->setMethods(['personaGetOAuthClient'])
            ->setConstructorArgs([
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ])
            ->getMock();
        $expectedResponse = [
            'rate_limit' => 1000,
            'rate_duration' => 1800,
            'rate_expires' => 1433516934,
            'call_count' => 0,
            'scope' => [
                'su'
            ]
        ];
        $mockClient->expects($this->once())
            ->method('personaGetOAuthClient')
            ->will($this->returnValue($expectedResponse));

        $client = $mockClient->getOAuthClient('123', '456');
        $this->assertEquals($expectedResponse['rate_limit'], $client['rate_limit']);
        $this->assertEquals($expectedResponse['rate_duration'], $client['rate_duration']);
        $this->assertEquals($expectedResponse['rate_expires'], $client['rate_expires']);
        $this->assertEquals($expectedResponse['call_count'], $client['call_count']);
        $this->assertEquals($expectedResponse['scope'], $client['scope']);
    }

    public function testUpdateOAuthClientEmptyGuid()
    {
        $this->setExpectedException(Exception::class, 'Invalid guid');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('', [], '987');
    }

    public function testUpdateOAuthClientInvalidGuid()
    {
        $this->setExpectedException(Exception::class, 'Invalid guid');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient([], [], '987');
    }

    public function testUpdateOAuthClientEmptyProperties()
    {
        $this->setExpectedException(Exception::class, 'Invalid properties');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', [], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesKeys()
    {
        $this->setExpectedException(Exception::class, 'Invalid properties');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['INVALID' => []], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys1()
    {
        $this->setExpectedException(Exception::class, 'Invalid properties');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => []], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys2()
    {
        $this->setExpectedException(Exception::class, 'Invalid properties');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['blah' => '']], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys3()
    {
        $this->setExpectedException(Exception::class, 'Invalid properties');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['blah' => '', '$add' => 'test']], '987');
    }

    public function testUpdateOAuthClientInvalidPropertiesScopeKeys4()
    {
        $this->setExpectedException(Exception::class, 'Invalid properties');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient(
            '123',
            [
                'scope' => [
                    'blah' => '',
                    '$remove' => 'remove-scope',
                    '$add' => 'add-scope'
                ]
            ],
            '987'
        );
    }

    public function testUpdateOAuthClientsEmptyToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['$add' => 'additional-scope']], '');
    }

    public function testUpdateOAuthClientsInvalidToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newOAuthClients(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateOAuthClient('123', ['scope' => ['$add' => 'additional-scope']], ['']);
    }

    public function testUpdateOAuthClientPutFails()
    {
        $this->setExpectedException(Exception::class, 'Could not retrieve OAuth response code');
        /** @var MockObject&OAuthClients */
        $mockClient = $this->getMockBuilder(OAuthClients::class)
            ->setMethods(['personaPatchOAuthClient'])
            ->setConstructorArgs([
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('personaPatchOAuthClient')
            ->will($this->throwException(new \Exception('Could not retrieve OAuth response code')));

        $mockClient->updateOAuthClient('guid', ['scope' => ['$add' => 'additional-scope']], '123');
    }

    public function testUpdateOAuthClientPutSucceeds()
    {
        /** @var MockObject&OAuthClients */
        $mockClient = $this->getMockBuilder(OAuthClients::class)
            ->setMethods(['personaPatchOAuthClient'])
            ->setConstructorArgs([
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ])
            ->getMock();

        $expectedResponse = []; // 204 has no content
        $mockClient->expects($this->once())
            ->method('personaPatchOAuthClient')
            ->will($this->returnValue($expectedResponse));

        $this->assertEquals(
            $expectedResponse,
            $mockClient->updateOAuthClient(
                '123',
                ['scope' => ['$add' => 'additional-scope']],
                '123'
            )
        );
    }

    public function testRegenerateSecretNon200Exception()
    {
        /** @var MockObject&OAuthClients */
        $oauthClient = $this->getMockBuilder(OAuthClients::class)
            ->setMethods(['performRequest'])
            ->setConstructorArgs([
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'persona_admin_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ])
            ->getMock();

        $oauthClient->expects($this->once())
            ->method('performRequest')
            ->with(
                'localhost/3/clients/clientId/secret',
                [
                    'method' => 'POST',
                    'bearerToken' => 'token',
                    'expectResponse' => true,
                ]
            )
            ->will(
                $this->throwException(
                    new \Exception('Did not retrieve successful response code')
                )
            );

        $this->setExpectedException(
            Exception::class,
            'Did not retrieve successful response code'
        );
        $oauthClient->regenerateSecret('clientId', 'token');
    }

    public function testRegenerateSecretInvalidResponsePayload()
    {
        /** @var MockObject&OAuthClients */
        $oauthClient = $this->getMockBuilder(OAuthClients::class)
            ->setMethods(['performRequest'])
            ->setConstructorArgs([
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'persona_admin_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ])
            ->getMock();

        $oauthClient->setLogger(new \Psr\Log\NullLogger());

        $oauthClient->expects($this->once())
            ->method('performRequest')
            ->with(
                'localhost/3/clients/clientId/secret',
                [
                    'method' => 'POST',
                    'bearerToken' => 'token',
                    'expectResponse' => true,
                ]
            )
            ->willReturn(['invalid' => 'body']);

        $this->setExpectedException(
            \Talis\Persona\Client\InvalidPayloadException::class,
            'invalid payload format from persona'
        );
        $oauthClient->regenerateSecret('clientId', 'token');
    }

    public function testRegenerateSecretHappyPath()
    {
        /** @var MockObject&OAuthClients */
        $oauthClient = $this->getMockBuilder(OAuthClients::class)
            ->setMethods(['performRequest'])
            ->setConstructorArgs([
                [
                    'userAgent' => 'unittest',
                    'persona_host' => 'localhost',
                    'persona_admin_host' => 'localhost',
                    'cacheBackend' => $this->cacheBackend,
                ]
            ])
            ->getMock();

        $oauthClient->setLogger(new \Psr\Log\NullLogger());

        $oauthClient->expects($this->once())
            ->method('performRequest')
            ->with(
                'localhost/3/clients/clientId/secret',
                [
                    'method' => 'POST',
                    'bearerToken' => 'token',
                    'expectResponse' => true,
                ]
            )
            ->willReturn(['secret' => 'new secret']);

        $secret = $oauthClient->regenerateSecret('clientId', 'token');
        $this->assertEquals('new secret', $secret);
    }

    private function newOAuthClients(array $config)
    {
        $oauthClient = new OAuthClients($config);
        $oauthClient->setLogger(new \Psr\Log\NullLogger());
        return $oauthClient;
    }
}
