<?php

namespace test\unit\Manifesto;

use test\TestBase;

class ClientTest extends TestBase
{
    public function testSetGetManifestoBaseUrl()
    {
        $baseUrl = 'http://example.com/manifesto';
        $client = new \Talis\Manifesto\Client($baseUrl);
        $this->assertEquals($baseUrl, $client->getManifestoBaseUrl());
        $client->setManifestoBaseUrl('https://example.org/foobar');
        $this->assertEquals('https://example.org/foobar', $client->getManifestoBaseUrl());
    }

    public function testSetGetPersonaConnectValues()
    {
        $client = new TestManifestoClient('http://example.com/');
        $this->assertEmpty($client->getPersonaConnectValues());

        $cacheDriver = new \Doctrine\Common\Cache\ArrayCache();
        $personaOpts = [
            'persona_host' => 'http://persona',
            'persona_oauth_route' => '/oauth/tokens/',
            'userAgent' => 'manifesto-client/1.0',
            'cacheBackend' => $cacheDriver,
        ];

        $client->setPersonaConnectValues($personaOpts);
        $this->assertEquals($personaOpts, $client->getPersonaConnectValues());

        // Test that passing opts in constructor also sets property
        $client = new TestManifestoClient('https://example.org/', $personaOpts);
        // Make sure that we're actually looking at the right thing, since assertions are cheap
        $this->assertEquals('https://example.org/', $client->getManifestoBaseUrl());
        $this->assertEquals($personaOpts, $client->getPersonaConnectValues());
    }

    public function testSetPersonaClient()
    {
        $client = new TestManifestoClient('http://example.com/');
        $cacheDriver = new \Doctrine\Common\Cache\ArrayCache();
        $persona = new \Talis\Persona\Client\Tokens(
            [
                'persona_host' => 'http://persona',
                'persona_oauth_route' => '/oauth/tokens/',
                'userAgent' => 'manifesto-client/1.0',
                'cacheBackend' => $cacheDriver,
            ]
        );

        $client->setPersonaClient($persona);
        $this->assertEquals($persona, $client->getPersonaClient());
    }

    public function testGenerateUrlNotAuthorisedResponse()
    {
        $mockClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(
                401,
                [],
                json_encode(['code' => 'Unauthorised request', 'message' => 'Client is not authorised for request'])
            ),
        ]);

        $this->setExpectedException(
            \Talis\Manifesto\Exceptions\UnauthorisedAccessException::class,
            'Client is not authorised for request'
        );
        $response = $mockClient->generateUrl('123', 'token', 'secret');
    }

    public function testGenerateUrlReturns404()
    {
        $mockClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(404, [], 'File not found'),
        ]);

        $this->setExpectedException(
            \Talis\Manifesto\Exceptions\GenerateUrlException::class,
            'Missing archive'
        );
        $response = $mockClient->generateUrl('1234', 'token', 'secret');
    }

    public function testGenerateUrlJobReadyForDownload()
    {
        $mockClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['url' => 'https://path.to.s3/export.zip'])),
        ]);

        $this->assertEquals('https://path.to.s3/export.zip', $mockClient->generateUrl('1234', 'token', 'secret'));
    }

    /**
     * @dataProvider requestArchiveErrorResponsesProvider
     * @param \Psr\Http\Message\ResponseInterface $response The response
     * @param string $exceptionClass The exception class
     * @param string $exceptionMessage The exception message
     */
    public function testRequestArchiveErrorResponse($response, $exceptionClass, $exceptionMessage = '')
    {
        $mockClient = $this->getClientWithMockResponses([$response]);

        $m = new \Talis\Manifesto\Manifest();
        $m->setFormat(FORMAT_ZIP);
        $files = [];
        $file1 = ['file' => '/path/to/file1.txt'];
        $files[] = $file1;
        $m->addFile($file1);

        $file2 = ['type' => FILE_TYPE_S3, 'container' => 'myBucket', 'file' => '/path/to/file2.txt', 'destinationPath' => 'foobar.txt'];
        $files[] = $file2;
        $m->addFile($file2);

        $file3 = ['type' => FILE_TYPE_CF, 'file' => '/path/to/file3.txt', 'destinationPath' => '/another/path/foobar.txt'];
        $files[] = $file3;
        $m->addFile($file3);

        $this->setExpectedException($exceptionClass, $exceptionMessage);
        $response = $mockClient->requestArchive($m, 'token', 'secret');
    }

    public function requestArchiveErrorResponsesProvider()
    {
        return [
            '400 Validation' => [
                new \GuzzleHttp\Psr7\Response(
                    400,
                    [],
                    json_encode(['code' => 'Invalid Manifest', 'message' => 'The Manifest is incomplete or contains invalid properties'])
                ),
                \Talis\Manifesto\Exceptions\ManifestValidationException::class,
                'The Manifest is incomplete or contains invalid properties',
            ],
            '401 Persona' => [
                new \GuzzleHttp\Psr7\Response(
                    401,
                    [],
                    json_encode(['code' => 'Unauthorised request', 'message' => 'Client is not authorised for request'])
                ),
                \Talis\Manifesto\Exceptions\UnauthorisedAccessException::class,
                'Client is not authorised for request',
            ],
            '404 File not found' => [
                new \GuzzleHttp\Psr7\Response(404, [], 'File not found'),
                \Talis\Manifesto\Exceptions\ArchiveException::class,
                'Misconfigured Manifesto base url',
            ],
            '420 Client error' => [
                new \GuzzleHttp\Psr7\Response(420, [], 'Enhance Your Calm'),
                \GuzzleHttp\Exception\ClientException::class,
            ],
            '500 Server error' => [
                new \GuzzleHttp\Psr7\Response(500, [], 'Server error'),
                \GuzzleHttp\Exception\ServerException::class,
            ],
        ];
    }

    public function testSuccessfulRequestArchive()
    {
        $mockClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(202, [], json_encode(['id' => '12345', 'status' => 'Accepted'])),
        ]);

        $m = new \Talis\Manifesto\Manifest();
        $m->setFormat(FORMAT_ZIP);
        $files = [];
        $file1 = ['file' => '/path/to/file1.txt'];
        $files[] = $file1;
        $m->addFile($file1);

        $file2 = ['type' => FILE_TYPE_S3, 'container' => 'myBucket', 'file' => '/path/to/file2.txt', 'destinationPath' => 'foobar.txt'];
        $files[] = $file2;
        $m->addFile($file2);

        $file3 = ['type' => FILE_TYPE_CF, 'file' => '/path/to/file3.txt', 'destinationPath' => '/another/path/foobar.txt'];
        $files[] = $file3;
        $m->addFile($file3);

        /** @var \Talis\Manifesto\Archive $response */
        $response = $mockClient->requestArchive($m, 'token', 'secret');

        $this->assertInstanceOf(\Talis\Manifesto\Archive::class, $response);
        $this->assertEquals('12345', $response->getId());
        $this->assertEquals('Accepted', $response->getStatus());
        $this->assertEmpty($response->getLocation());
    }

    /**
     * Gets the client with mocked HTTP responses.
     *
     * @param \GuzzleHttp\Psr7\Response[] $responses The responses
     * @return \Talis\Manifesto\Client|\PHPUnit_Framework_MockObject_MockObject The client.
     */
    private function getClientWithMockResponses(array $responses = [])
    {
        $mockHandler = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);

        $manifestoBaseUrl = 'https://example.com/manifesto';
        $personaConnectValues = [
            'persona_host' => 'http://persona',
            'persona_oauth_route' => '/oauth/tokens/',
            'userAgent' => 'manifesto-client/1.0',
            'cacheBackend' => new \Doctrine\Common\Cache\ArrayCache(),
        ];

        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $stubPersonaClient = $this->getMock(\Talis\Persona\Client\Tokens::class, [], [], '', false);
        $stubPersonaClient->method('obtainNewToken')
            ->willReturn(['access_token' => 'some-token']);

        $manifestoClient = $this->getMockBuilder(\Talis\Manifesto\Client::class)
            ->setConstructorArgs([$manifestoBaseUrl, $personaConnectValues])
            ->setMethods(['getHTTPClient', 'getPersonaClient'])
            ->getMock();

        $manifestoClient->method('getHTTPClient')
            ->willReturn($httpClient);

        $manifestoClient->method('getPersonaClient')
            ->willReturn($stubPersonaClient);

        return $manifestoClient;
    }
}

class TestManifestoClient extends \Talis\Manifesto\Client
{
    public function getPersonaConnectValues()
    {
        return $this->personaConnectValues;
    }

    public function getPersonaClient()
    {
        return parent::getPersonaClient();
    }
}
