<?php

namespace test\unit\EchoClient;

use test\TestBase;

/**
 * Unit tests for Echo Client.
 * @runTestsInSeparateProcesses
 */
class ClientTest extends TestBase
{
    private $arrMandatoryDefines = [
        'OAUTH_USER',
        'OAUTH_SECRET',
        'PERSONA_HOST',
        'PERSONA_OAUTH_ROUTE',
        'PERSONA_TOKENCACHE_HOST',
        'PERSONA_TOKENCACHE_PORT',
        'PERSONA_TOKENCACHE_DB',
    ];

    /**
     * Ensure that all the required define()s are set before Echo Client can be used.
     *
     * @dataProvider mandatoryDefinesProvider
     * @param string $requiredDefineToTest Name of missing constant to test
     */
    public function testRequiredDefines($requiredDefineToTest)
    {
        $this->setRequiredDefines($requiredDefineToTest);

        $this->setExpectedException('\Exception', 'Missing define: ' . $requiredDefineToTest);
        new \Talis\EchoClient\Client();
    }

    public function mandatoryDefinesProvider()
    {
        $data = [];

        foreach ($this->arrMandatoryDefines as $defineKey) {
            $data[] = [$defineKey];
        }

        return $data;
    }

    public function testNoEventWrittenWhenNoEchoHostDefined()
    {
        $this->setRequiredDefines();
        $echoClient = $this->getClientWithMockResponses();
        $bSent = $echoClient->createEvent('someClass', 'someSource');
        $this->assertFalse($bSent);
    }

    public function testEventWritten()
    {
        $this->setRequiredDefines();

        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(202),
        ], $history);

        $expectedEventJson = json_encode([
            [
                'class' => 'test.some.class',
                'source' => 'some-source',
                'props' => ['foo' => 'bar'],
                'user' => 'some-user',
                'timestamp' => '1531816712'
            ]
        ]);

        $wasSent = $echoClient->createEvent(
            'some.class',
            'some-source',
            ['foo' => 'bar'],
            'some-user',
            '1531816712'
        );

        $this->assertTrue($wasSent);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(['application/json'], $request->getHeader('Content-Type'));
        $this->assertEquals(['Bearer some-token'], $request->getHeader('Authorization'));
        $this->assertEquals($expectedEventJson, (string) $request->getBody());
    }

    public function testSendBatchEvents()
    {
        $this->setRequiredDefines();

        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(202),
        ], $history);

        $expectedEventJson = json_encode([
            [
                'class' => 'test.foo',
                'source' => 'bar',
                'props' => ['baz' => 'box'],
                'user' => 'joe',
                'timestamp' => '1531816712'
            ],
            [
                'class' => 'test.foob',
                'source' => 'barb',
                'props' => ['bazb' => 'boxb'],
                'user' => 'joeb',
                'timestamp' => '1531816712'
            ],
            [
                'class' => 'test.fooc',
                'source' => 'barc',
                'props' => ['bazc' => 'boxc'],
                'user' => 'joec',
                'timestamp' => '1531816712'
            ]
        ]);

        $events = [
            new \Talis\EchoClient\Event('foo', 'bar', ['baz' => 'box'], 'joe', '1531816712'),
            new \Talis\EchoClient\Event('foob', 'barb', ['bazb' => 'boxb'], 'joeb', '1531816712'),
            new \Talis\EchoClient\Event('fooc', 'barc', ['bazc' => 'boxc'], 'joec', '1531816712'),
        ];
        $wasSent = $echoClient->sendBatchEvents($events);

        $this->assertTrue($wasSent);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(['application/json'], $request->getHeader('Content-Type'));
        $this->assertEquals(['Bearer some-token'], $request->getHeader('Authorization'));
        $this->assertEquals($expectedEventJson, (string) $request->getBody());
    }

    /**
     * @expectedException \Talis\EchoClient\TooManyEventsInBatchException
     * @expectedExceptionMessage Batch of events exceeds the maximum allowed size
     */
    public function testSendBatchEventsThrowsExceptionIfBatchContainsTooManyEvents()
    {
        $this->setRequiredDefines();

        $events = [];
        for ($i = 0; $i < 101; $i++) {
            $events[] = new \Talis\EchoClient\Event('foo', 'bar', ['baz' => 'box'], 'joe', '1531816712');
        }

        $echoClient = new \Talis\EchoClient\Client();
        $echoClient->sendBatchEvents($events);
    }

    /**
     * @expectedException \Talis\EchoClient\BadEventDataException
     * @expectedExceptionMessage Batch must only contain Echo Event objects
     */
    public function testSendBatchEventsThrowsExceptionIfBatchContainsNonEchoEvents()
    {
        $this->setRequiredDefines();

        $events = [];
        $events[] = (object) ['a' => 'b'];

        $echoClient = new \Talis\EchoClient\Client();
        $echoClient->sendBatchEvents($events);
    }

    /**
     * @expectedException \Talis\EchoClient\PayloadTooLargeException
     * @expectedExceptionMessage Batch must be less than 1mb in size
     */
    public function testSendBatchEventsThrowsExceptionIfBatchIsGreaterThanMaxBytesAllowed()
    {
        $this->setRequiredDefines();

        $echoClient = new \Talis\EchoClient\Client();

        $events = [new \Talis\EchoClient\Event(
            'foo',
            'bar',
            ['long' => str_repeat('a', 1000000)],
            'joe',
            '1531816712'
        )];
        $echoClient->sendBatchEvents($events);
    }

    public function testSendBatchEventsReturnsTrueIfBatchIsEmpty()
    {
        $this->setRequiredDefines();

        $events = [];
        $echoClient = new \Talis\EchoClient\Client();
        $this->assertTrue($echoClient->sendBatchEvents($events));
    }

    public function testRecentEvents()
    {
        $this->setRequiredDefines();

        $expectedEvent = ['class' => 'test.expected.event'];
        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['events' => [$expectedEvent]])),
        ], $history);

        $result = $echoClient->getRecentEvents('expected.event', 'foo', 'bar');

        $this->assertEquals([$expectedEvent], $result);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(
            'http://example.com:3002/1/events?limit=25&class=test.expected.event&key=foo&value=bar',
            (string) $request->getUri()
        );
    }

    /**
     * @dataProvider getEventsCasesProvider
     * @param array $arguments Client::getEvents method arguments
     * @param string $expectedUri Expected URI to be called
     */
    public function testGetEvents(array $arguments, $expectedUri)
    {
        $this->setRequiredDefines();

        $expectedEvent = ['class' => 'test.expected.event'];
        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['events' => [$expectedEvent]])),
        ], $history);

        $result = call_user_func_array([$echoClient, 'getEvents'], $arguments);

        $this->assertEquals([$expectedEvent], $result);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals($expectedUri, (string) $request->getUri());
    }

    public function getEventsCasesProvider()
    {
        return [
            'defaults' => [
                ['expected.event', 'foo', 'bar'],
                'http://example.com:3002/1/events?limit=25&class=test.expected.event&key=foo&value=bar',
            ],
            'offset' => [
                ['expected.event', 'foo', 'bar', 25, 30],
                'http://example.com:3002/1/events?limit=25&offset=30&class=test.expected.event&key=foo&value=bar',
            ],
            'to certain time' => [
                ['expected.event', 'foo', 'bar', 25, 0, null, 1516634248],
                'http://example.com:3002/1/events?limit=25'
                . '&class=test.expected.event&key=foo&value=bar'
                . '&from=2018-01-22T15%3A17%3A28%2B00%3A00'
            ],
            'from certain time' => [
                ['expected.event', 'foo', 'bar', 25, 0, null, null, 1516634248],
                'http://example.com:3002/1/events?limit=25&'
                . 'class=test.expected.event&key=foo&value=bar&'
                . 'to=2018-01-22T15%3A17%3A28%2B00%3A00'
            ],
            'from certain time to certain time' => [
                ['expected.event', 'foo', 'bar', 25, 0, null, 1516634248, 1516634417],
                'http://example.com:3002/1/events?limit=25'
                . '&class=test.expected.event&key=foo&value=bar'
                . '&to=2018-01-22T15%3A20%3A17%2B00%3A00'
                . '&from=2018-01-22T15%3A17%3A28%2B00%3A00'
            ],
        ];
    }

    public function testGetEventsCsvFormat()
    {
        $this->setRequiredDefines();

        $expectedResult = '"a","csv","file"' . "\n" . '"with","some,"data"';
        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], $expectedResult),
        ], $history);

        $result = $echoClient->getEvents('expected.event', 'foo', 'bar', 25, 1000, 'csv');

        $this->assertEquals($expectedResult, $result);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(
            'http://example.com:3002/1/events?limit=25&offset=1000&'
                . 'class=test.expected.event&key=foo&value=bar&format=csv',
            (string) $request->getUri()
        );
    }

    public function testHitsReturnsExpectedJSON()
    {
        $this->setRequiredDefines();

        $expectedResponse = [
            'head' => [
                'type' => 'hits',
                'class' => 'test.player.view',
                'group_by' => 'source',
                'count' => 3,
            ],
            'results' => [
                ['source' => 'web.talis-com.b50367b.2014-05-15', 'hits' => 45],
                ['source' => 'web.talis-com.b692220.2014-05-15', 'hits' => 9],
                ['source' => 'mobile.android-v1.9', 'hits' => 16],
            ],
        ];
        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($expectedResponse)),
        ], $history);

        $result = $echoClient->getHits('player.view');

        $this->assertEquals($expectedResponse, $result);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(
            'http://example.com:3002/1/analytics/hits?class=test.player.view',
            (string) $request->getUri()
        );
    }

    public function testHitsReturnsExpectedCsv()
    {
        $this->setRequiredDefines();

        $expectedBody = "here,are,some,headers\n,and,here,is,some,data";
        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], $expectedBody),
        ], $history);

        $result = $echoClient->getHits('player.view', ['format' => 'csv']);

        $this->assertSame($expectedBody, $result);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(
            'http://example.com:3002/1/analytics/hits?class=test.player.view&format=csv',
            (string) $request->getUri()
        );
    }

    public function testHitsReturnsExpectedJSONNoCache()
    {
        $this->setRequiredDefines();

        $expectedResponse = [
            'head' => [
                'type' => 'hits',
                'class' => 'test.player.view',
                'group_by' => 'source',
                'count' => 3,
            ],
            'results' => [
                ['source' => 'web.talis-com.b50367b.2014-05-15', 'hits' => 45],
                ['source' => 'web.talis-com.b692220.2014-05-15', 'hits' => 9],
                ['source' => 'mobile.android-v1.9', 'hits' => 16],
            ],
        ];
        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($expectedResponse)),
        ], $history);

        $result = $echoClient->getHits('player.view', [], true);

        $this->assertEquals($expectedResponse, $result);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(['application/json'], $request->getHeader('Content-Type'));
        $this->assertEquals(['Bearer some-token'], $request->getHeader('Authorization'));
        $this->assertEquals(['none'], $request->getHeader('Cache-Control'));
        $this->assertEquals(
            'http://example.com:3002/1/analytics/hits?class=test.player.view',
            (string) $request->getUri()
        );
    }

    public function testHitsWithOptsReturnsExpectedJSON()
    {
        $this->setRequiredDefines();

        $expectedResponse = [
            'head' => [
                'type' => 'hits',
                'class' => 'test.player.view',
                'group_by' => 'source',
                'count' => 3,
            ],
            'results' => [
                ['source' => 'web.talis-com.b50367b.2014-05-15', 'hits' => 45],
                ['source' => 'web.talis-com.b692220.2014-05-15', 'hits' => 9],
                ['source' => 'mobile.android-v1.9', 'hits' => 16],
            ],
        ];
        $history = [];
        $echoClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($expectedResponse)),
        ], $history);

        $result = $echoClient->getHits('player.view', ['key' => 'some_key', 'value' => 'some_value']);

        $this->assertEquals($expectedResponse, $result);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals(
            'http://example.com:3002/1/analytics/hits?class=test.player.view&key=some_key&value=some_value',
            (string) $request->getUri()
        );
    }

    /**
     * @dataProvider analyticsAggregationsProvider
     * @param string $method Aggregation method to test
     * @param string $expectedType Expected analytics type
     */
    public function testAggregationMethodsCallAnalytics($method, $expectedType)
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock(\Talis\EchoClient\Client::class, ['getAnalytics']);
        $echoClient->expects($this->once())->method('getAnalytics')->with('some.class', $expectedType);

        call_user_func([$echoClient, $method], 'some.class');
    }

    public function analyticsAggregationsProvider()
    {
        return [
            ['getHits', 'hits'],
            ['getAverage', 'average'],
            ['getSum', 'sum'],
            ['getMax', 'max'],
        ];
    }

    /**
     * Set up the mandatory defines, omitting an optional exclusion.
     */
    protected function setRequiredDefines($defineToExclude = null)
    {
        foreach ($this->arrMandatoryDefines as $defineKey) {
            if ($defineToExclude === null || $defineKey !== $defineToExclude) {
                define($defineKey, $defineKey . '-VALUE');
            }
        }

        define('ECHO_CLASS_PREFIX', 'test.');
        define('ECHO_HOST', 'http://example.com:3002');
    }

    /**
     * Gets the client with mocked HTTP responses.
     *
     * @param \GuzzleHttp\Psr7\Response[] $responses The responses
     * @param array $history History middleware container
     * @return \Talis\EchoClient\Client|\PHPUnit_Framework_MockObject_MockObject The client.
     */
    private function getClientWithMockResponses(array $responses = [], array &$history = null)
    {
        $mockHandler = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);

        if (isset($history)) {
            $handlerStack->push(\GuzzleHttp\Middleware::history($history));
        }

        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $stubPersonaClient = $this->getMock(\Talis\Persona\Client\Tokens::class, [], [], '', false);
        $stubPersonaClient->method('obtainNewToken')
            ->willReturn(['access_token' => 'some-token']);

        $echoClient = $this->getMockBuilder(\Talis\EchoClient\Client::class)
            ->setMethods(['getHTTPClient', 'getPersonaClient'])
            ->getMock();

        $echoClient->method('getHTTPClient')
            ->willReturn($httpClient);

        $echoClient->method('getPersonaClient')
            ->willReturn($stubPersonaClient);

        return $echoClient;
    }
}
