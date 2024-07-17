<?php

namespace test\unit\Critic;

use PHPUnit\Framework\MockObject\MockObject;
use test\CompatAssert;
use test\TestBase;

/**
 * Unit tests for CriticClient
 */
class ClientTest extends TestBase
{
    private $criticBaseUrl;
    private $postFields;

    /**
     * @before
     */
    protected function initializeFields()
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

    public function testCreateReviewException()
    {
        $this->setExpectedException(\Talis\Critic\Exceptions\ReviewException::class);

        $criticClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['id' => '1234'])),
        ]);

        $criticClient->createReview($this->postFields, '', '');
    }

    public function testCreateReviewGuzzleException()
    {
        $this->setExpectedException(\Talis\Critic\Exceptions\UnauthorisedAccessException::class);

        $criticClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(401, [], json_encode([])),
        ]);

        $criticClient->createReview(
            $this->postFields,
            'someClientId',
            'someClientSecret'
        );
    }

    public function testCreateReviewWithInvalidPersonaConfigFails()
    {
        $this->setExpectedException(
            \Exception::class,
            'Did not retrieve successful response code from persona'
        );

        $personaConf = $this->getPersonaConfig();

        $criticClient = new \Talis\Critic\Client($this->criticBaseUrl);
        $criticClient->setPersonaConnectValues([
            'userAgent' => 'userAgentVal',
            'persona_host' => $personaConf['host'],
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
        CompatAssert::assertIsArray($body);
        $this->assertEquals($this->postFields, $body);
    }

    /**
     * Gets the client with mocked HTTP responses.
     *
     * @param \GuzzleHttp\Psr7\Response[] $responses The responses
     * @param array $history History middleware container
     * @return \Talis\Critic\Client|MockObject The client.
     */
    private function getClientWithMockResponses(array $responses, array &$history = null)
    {
        $mockHandler = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);

        if (isset($history)) {
            $handlerStack->push(\GuzzleHttp\Middleware::history($history));
        }

        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        /** @var MockObject&\Talis\Persona\Client\Tokens */
        $tokenClient = $this->getMockBuilder(\Talis\Persona\Client\Tokens::class)
            ->disableOriginalConstructor()
            ->setMethods(['obtainNewToken'])
            ->getMock();
        $tokenClient->method('obtainNewToken')
            ->willReturn(['access_token' => 'TOKEN']);

        /** @var MockObject&\Talis\Critic\Client */
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
