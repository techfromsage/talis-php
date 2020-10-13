<?php

namespace test\unit\Critic;

use test\TestBase;

/**
 * Unit tests for CriticClient
 */
class ClientTest extends TestBase
{
    private $criticBaseUrl;
    private $postFields;

    protected function setUp()
    {
        $this->criticBaseUrl = 'http://listreviews.talis.com/test/reviews';
        $this->postFields = ['listUri' => 'http://somelist'];
    }

    public function testCreateReviewSuccess()
    {
        $id = '1234567890';
        $criticClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => $id])),
        ]);

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
        $criticClient = new \Talis\Critic\Client($this->criticBaseUrl);
        $criticClient->setPersonaConnectValues([
            'userAgent' => 'userAgentVal',
            'persona_host' => 'persona_host_val',
            'persona_oauth_route' => 'persona_oauth_route_val',
            'persona_oauth_route' => 'persona_oauth_route_val',
            'cacheBackend' => new \Doctrine\Common\Cache\ArrayCache(),
        ]);
        $criticClient->createReview(
            $this->postFields,
            'someClientId',
            'someClientSecret'
        );
    }

    public function testCreateReviewRequestIsSentOutCorrectly()
    {
        $history = [];
        $criticClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => '1234567890'])),
        ], $history);
        $criticClient->createReview($this->postFields, '', '');

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];

        $this->assertTrue($request->hasHeader('Content-Type'), 'Content-Type header is missing');
        $this->assertStringStartsWith('application/x-www-form-urlencoded', $request->getHeader('Content-Type')[0]);
        parse_str((string) $request->getBody(), $body);
        $this->assertInternalType('array', $body);
        $this->assertEquals($this->postFields, $body);
    }

    /**
     * Gets the client with mocked HTTP responses.
     *
     * @param \GuzzleHttp\Psr7\Response[] $responses The responses
     * @param array $history History middleware container
     * @return \Talis\Critic\Client|\PHPUnit_Framework_MockObject_MockObject The client.
     */
    private function getClientWithMockResponses(array $responses, array &$history = null)
    {
        $mockHandler = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);

        if (isset($history)) {
            $handlerStack->push(\GuzzleHttp\Middleware::history($history));
        }

        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $tokenClient = $this->getMockBuilder(\Talis\Persona\Client\Tokens::class)
            ->disableOriginalConstructor()
            ->setMethods(['obtainNewToken'])
            ->getMock();
        $tokenClient->method('obtainNewToken')
            ->willReturn(['access_token' => 'TOKEN']);

        /** @var \Talis\Critic\Client|\PHPUnit_Framework_MockObject_MockObject */
        $criticClient = $this->getMockBuilder(\Talis\Critic\Client::class)
            ->setMethods(['getHTTPClient', 'getTokenClient'])
            ->setConstructorArgs([$this->criticBaseUrl])
            ->getMock();

        $criticClient->method('getHTTPClient')
            ->willReturn($httpClient);

        $criticClient->method('getTokenClient')
            ->willReturn($tokenClient);

        return $criticClient;
    }
}
