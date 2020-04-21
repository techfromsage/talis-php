<?php

namespace test\unit\Critic;

/**
 * Unit tests for CriticClient
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    private $criticBaseUrl;
    private $criticClient;
    private $personaConfig;
    private $postFields;
    private $cacheDriver;

    protected function setUp()
    {
        $this->criticBaseUrl = 'http://listreviews.talis.com/test/reviews';
        $this->criticClient = new \Talis\Critic\Client($this->criticBaseUrl);

        $this->cacheDriver = new \Doctrine\Common\Cache\ArrayCache();
        $this->personaConfig = [
            'userAgent' => 'userAgentVal',
            'persona_host' => 'persona_host_val',
            'persona_oauth_route' => 'persona_oauth_route_val',
            'persona_oauth_route' => 'persona_oauth_route_val',
            'cacheBackend' => $this->cacheDriver,
        ];

        $this->postFields = ['listUri' => 'http://somelist'];
    }

    public function testCreateReviewSuccess()
    {
        $id = '1234567890';
        $criticClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => $id])),
        ]);

        $criticClient->setPersonaConnectValues($this->personaConfig);
        $this->assertEquals($id, $criticClient->createReview($this->postFields, '', ''));
    }

    /**
     * Exception thrown when response code is 200
     *
     * @expectedException \Talis\Critic\Exceptions\ReviewException
     */
    public function testCreateReviewException()
    {
        $criticClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['id' => '1234'])),
        ]);

        $criticClient->setPersonaConnectValues($this->personaConfig);
        $criticClient->createReview($this->postFields, '', '');
    }

    /**
     * 401 response triggers UnauthorisedAccessException
     *
     * @expectedException \Talis\Critic\Exceptions\UnauthorisedAccessException
     */
    public function testCreateReviewGuzzleException()
    {
        $criticClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(401, [], json_encode([])),
        ]);

        $criticClient->setPersonaConnectValues($this->personaConfig);

        $criticClient->createReview(
            $this->postFields,
            'someClientId',
            'someClientSecret'
        );
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Did not retrieve successful response code from persona: -1
     */
    public function testCreateReviewWithInvalidPersonaConfigFails()
    {
        $this->criticClient->setPersonaConnectValues($this->personaConfig);

        $this->criticClient->createReview(
            $this->postFields,
            'someClientId',
            'someClientSecret'
        );
    }

    /**
     * Gets the client with mocked HTTP responses.
     *
     * @param \GuzzleHttp\Psr7\Response[] $responses The responses
     * @return \Talis\Critic\Client|\PHPUnit_Framework_MockObject_MockObject The client.
     */
    private function getClientWithMockResponses(array $responses)
    {
        $mockHandler = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $criticClient = $this->getMockBuilder(\Talis\Critic\Client::class)
            ->setMethods(['getHTTPClient', 'getHeaders'])
            ->setConstructorArgs([$this->criticBaseUrl])
            ->getMock();

        $criticClient->method('getHTTPClient')
            ->willReturn($httpClient);

        $criticClient->expects($this->once())
            ->method('getHeaders')
            ->willReturn([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer TOKEN',
            ]);

        return $criticClient;
    }
}
