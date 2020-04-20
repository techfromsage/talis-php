<?php

namespace test\unit\EchoClient;

/**
 * Unit tests for Echo Client.
 * @runTestsInSeparateProcesses
 */
class ClientTest extends \PHPUnit_Framework_TestCase
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
     */
    public function testRequiredDefines($requiredDefineToTest)
    {
        $this->setRequiredDefines($requiredDefineToTest);

        $this->setExpectedException('\Exception', 'Missing define: '.$requiredDefineToTest);
        new \Talis\EchoClient\Client();
    }

    public function testNoEventWrittenWhenNoEchoHostDefined()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $echoClient = $this->getMock(
            '\Talis\EchoClient\Client',
            ['getPersonaClient']
        );

        $echoClient->expects($this->once())
            ->method('getPersonaClient')
            ->will($this->returnValue($stubPersonaClient));

        $bSent = $echoClient->createEvent('someClass', 'someSource');
        $this->assertFalse($bSent);
    }

    public function testEventWritten()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $response = new \Guzzle\Http\Message\Response('202');

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['post', '']);
        $mockRequest->expects($this->once())
            ->method('send')
            ->will($this->returnValue($response));

        $expectedHeaders = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer some-token',
        ];

        $expectedEventJson = json_encode([
            [
                'class' => 'test.some.class',
                'source' => 'some-source',
                'props' => [
                    'foo' => 'bar'
                ],
                'user' => 'some-user',
                'timestamp' => '1531816712'
            ]
        ]);

        $expectedConnectTimeout = ['connect_timeout' => 2];

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['post']);
        $stubHttpClient->expects($this->once())
            ->method('post')
            ->with(
                'http://example.com:3002/1/events',
                $expectedHeaders,
                $expectedEventJson,
                $expectedConnectTimeout
            )
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock(
            '\Talis\EchoClient\Client',
            ['getPersonaClient', 'getHttpClient']
        );

        $echoClient->expects($this->once())
            ->method('getPersonaClient')
            ->will($this->returnValue($stubPersonaClient));

        $echoClient->expects($this->once())
            ->method('getHttpClient')
            ->will($this->returnValue($stubHttpClient));

        $bSent = $echoClient->createEvent(
            'some.class',
            'some-source',
            ['foo' => 'bar'],
            'some-user',
            '1531816712'
        );

        $this->assertTrue($bSent);
    }

    public function testSendBatchEvents()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $response = new \Guzzle\Http\Message\Response('202');

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['post','']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $expectedHeaders = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer some-token'
        ];
        $expectedEventJson = json_encode([
            [
                'class' => 'test.foo',
                'source' => 'bar',
                'props' => [
                    'baz' => 'box'
                ],
                'user' => 'joe',
                'timestamp' => '1531816712'
            ],
            [
                'class' => 'test.foob',
                'source' => 'barb',
                'props' => [
                    'bazb' => 'boxb'
                ],
                'user' => 'joeb',
                'timestamp' => '1531816712'
            ],
            [
                'class' => 'test.fooc',
                'source' => 'barc',
                'props' => [
                    'bazc' => 'boxc'
                ],
                'user' => 'joec',
                'timestamp' => '1531816712'
            ]
        ]);

        $expectedConnectTimeout = ['connect_timeout' => 2];

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['post']);
        $stubHttpClient->expects($this->once())->method('post')->with(
            'http://example.com:3002/1/events',
            $expectedHeaders,
            $expectedEventJson,
            $expectedConnectTimeout
        )->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $events = [];
        $events[] = new \Talis\EchoClient\Event('foo', 'bar', ['baz' => 'box'], 'joe', '1531816712');
        $events[] = new \Talis\EchoClient\Event('foob', 'barb', ['bazb' => 'boxb'], 'joeb', '1531816712');
        $events[] = new \Talis\EchoClient\Event('fooc', 'barc', ['bazc' => 'boxc'], 'joec', '1531816712');
        $wasSent = $echoClient->sendBatchEvents($events);

        $this->assertTrue($wasSent);
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

        $expectedEventJson = json_encode([
            [
                'class' => 'test.foo',
                'source' => 'bar',
                'props' => [
                    'baz' => 'box'
                ],
                'user' => 'joe',
                'timestamp' => '1531816712'
            ]
        ]);

        $expectedConnectTimeout = ['connect_timeout' => 2];

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getStringSizeInBytes']);
        $echoClient->expects($this->once())
            ->method('getStringSizeInBytes')
            ->with($expectedEventJson)
            ->will($this->returnValue(1000001));

        $events = [];
        $events[] = new \Talis\EchoClient\Event('foo', 'bar', ['baz' => 'box'], 'joe', '1531816712');
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

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedEvent = ['class' => 'test.expected.event'];
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(json_encode(['events' => [$expectedEvent]]));

        $mockRequest = $this->getMock(
            '\Guzzle\Http\Message\Request',
            ['send'],
            ['get', '']
        );

        $mockRequest->expects($this->once())
            ->method('send')
            ->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with('http://example.com:3002/1/events?limit=25&class=test.expected.event&key=foo&value=bar')
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock(
            '\Talis\EchoClient\Client',
            ['getPersonaClient', 'getHttpClient']
        );

        $echoClient->expects($this->once())
            ->method('getPersonaClient')
            ->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())
            ->method('getHttpClient')
            ->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getRecentEvents('expected.event', 'foo', 'bar');

        $this->assertEquals([$expectedEvent], $result);
    }

    public function testGetEvents()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMockBuilder('\Talis\Persona\Client\Tokens')
            ->disableOriginalConstructor()
            ->getMock();
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedEvent = ['class' => 'test.expected.event'];
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(json_encode(['events' => [$expectedEvent]]));

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get','']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with('http://example.com:3002/1/events?limit=25&class=test.expected.event&key=foo&value=bar')
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getEvents('expected.event', 'foo', 'bar');

        $this->assertEquals([$expectedEvent], $result);
    }

    public function testGetEventsOffset()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedEvent = ['class' => 'test.expected.event'];
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(json_encode(['events' => [$expectedEvent]]));

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get', '']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with('http://example.com:3002/1/events?limit=25&offset=30&class=test.expected.event&key=foo&value=bar')
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getEvents('expected.event', 'foo', 'bar', 25, 30);

        $this->assertEquals([$expectedEvent], $result);
    }

    public function testGetEventsCsvFormat()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedResult = '"a","csv","file"' . "\n" . '"with","some,"data"';
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody($expectedResult);

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get', '']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with(
                'http://example.com:3002/1/events?limit=25&offset=1000&'
                . 'class=test.expected.event&key=foo&value=bar&format=csv'
            )
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getEvents('expected.event', 'foo', 'bar', 25, 1000, 'csv');

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetEventsToCertainTime()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMockBuilder('\Talis\Persona\Client\Tokens')
            ->disableOriginalConstructor()
            ->getMock();
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedEvent = ['class' => 'test.expected.event'];
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(json_encode(['events' => [$expectedEvent]]));

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get','']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with(
                'http://example.com:3002/1/events?limit=25'
                . '&class=test.expected.event&key=foo&value=bar'
                . '&from=2018-01-22T15%3A17%3A28%2B00%3A00'
            )->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getEvents('expected.event', 'foo', 'bar', 25, 0, null, 1516634248);

        $this->assertEquals([$expectedEvent], $result);
    }

    public function testGetEventsFromCertainTime()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMockBuilder('\Talis\Persona\Client\Tokens')
            ->disableOriginalConstructor()
            ->getMock();
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedEvent = ['class' => 'test.expected.event'];
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(json_encode(['events' => [$expectedEvent]]));

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get','']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with(
                'http://example.com:3002/1/events?limit=25&'
                . 'class=test.expected.event&key=foo&value=bar&'
                . 'to=2018-01-22T15%3A17%3A28%2B00%3A00'
            )
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getEvents('expected.event', 'foo', 'bar', 25, 0, null, null, 1516634248);

        $this->assertEquals([$expectedEvent], $result);
    }

    public function testGetEventsFromCertainTimeToACertainTime()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMockBuilder('\Talis\Persona\Client\Tokens')
            ->disableOriginalConstructor()
            ->getMock();
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedEvent = ['class' => 'test.expected.event'];
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(json_encode(['events' => [$expectedEvent]]));

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get','']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with(
                'http://example.com:3002/1/events?limit=25'
                . '&class=test.expected.event&key=foo&value=bar'
                . '&to=2018-01-22T15%3A20%3A17%2B00%3A00'
                . '&from=2018-01-22T15%3A17%3A28%2B00%3A00'
            )
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getEvents('expected.event', 'foo', 'bar', 25, 0, null, 1516634248, 1516634417);

        $this->assertEquals([$expectedEvent], $result);
    }

    public function testHitsReturnsExpectedJSON()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(
            '{'
            . '"head":{"type":"hits","class":"test.player.view","group_by":"source","count":27},'
            . '"results":['
            . '{"source":"web.talis-com.b50367b.2014-05-15","hits":45},'
            . '{"source":"web.talis-com.b692220.2014-05-15","hits":9},'
            . '{"source":"mobile.android-v1.9","hits":16},'
            . '{"source":"web.talis-com.f1afa4f.2014-05-13","hits":21},'
            . '{"source":"mobile.android-v1.7","hits":48},'
            . '{"source":"web.talis-com.d165ea5.2014-05-01","hits":411},'
            . '{"source":"web.talis-com.3dceffd.2014-05-15","hits":8},'
            . '{"source":"mobile.android-v1.6","hits":41},'
            . '{"source":"web.talis-com.35baf27.2014-04-29","hits":50},'
            . '{"source":"web.talis-com.13f1318.2014-05-14","hits":18},'
            . '{"source":"web.talis-com.no-release","hits":219},'
            . '{"source":"mobile.iOS-v1.97","hits":5},'
            . '{"source":"web.talis-com-no-release","hits":23},'
            . '{"source":"web.talis-com.12f4d8c.2014-04-29","hits":29},'
            . '{"source":"web.talis-com.4a51b66.2014-04-25","hits":56},'
            . '{"source":"mobile.android-v1.3","hits":4},'
            . '{"source":"mobile.android-v2.0","hits":39},'
            . '{"source":"web.talis-com.9df593e.2014-04-17","hits":44},'
            . '{"source":"web.talis-com.8dac333.2014-04-17","hits":1},'
            . '{"source":"mobile.iOS-v1.99","hits":60},'
            . '{"source":"mobile.iOS-v1.98","hits":116},'
            . '{"source":"web.talis-com.d5e099c.2014-05-15","hits":2},'
            . '{"source":"mobile.android-v1.8","hits":16},'
            . '{"source":"web.talis-com.64ade28.2014-04-17","hits":22},'
            . '{"source":"mobile.iOS-v1.95","hits":10},'
            . '{"source":"mobile.android-v1.4","hits":1},'
            . '{"source":"mobile.android-v1.5","hits":20}]}'
        );

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get','']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with('http://example.com:3002/1/analytics/hits?class=test.player.view')
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getHits('player.view');

        $this->assertTrue(isset($result['head']));
        $this->assertTrue(isset($result['results']));
    }

    public function testHitsReturnsExpectedCsv()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $expectedResponse = "here,are,some,headers\n,and,here,is,some,data";
        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody($expectedResponse);

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get', '']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with('http://example.com:3002/1/analytics/hits?class=test.player.view&format=csv')
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())
            ->method('getPersonaClient')
            ->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())
            ->method('getHttpClient')
            ->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getHits('player.view', ['format' => 'csv']);

        $this->assertSame($expectedResponse, $result);
    }

    public function testHitsReturnsExpectedJSONNoCache()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(
            '{"head":{"type":"hits","class":"test.player.view","group_by":"source","count":27},'
            . '"results":['
            . '{"source":"web.talis-com.b50367b.2014-05-15","hits":45},'
            . '{"source":"web.talis-com.b692220.2014-05-15","hits":9},'
            . '{"source":"mobile.android-v1.9","hits":16},'
            . '{"source":"web.talis-com.f1afa4f.2014-05-13","hits":21},'
            . '{"source":"mobile.android-v1.7","hits":48},'
            . '{"source":"web.talis-com.d165ea5.2014-05-01","hits":411},'
            . '{"source":"web.talis-com.3dceffd.2014-05-15","hits":8},'
            . '{"source":"mobile.android-v1.6","hits":41},'
            . '{"source":"web.talis-com.35baf27.2014-04-29","hits":50},'
            . '{"source":"web.talis-com.13f1318.2014-05-14","hits":18},'
            . '{"source":"web.talis-com.no-release","hits":219},'
            . '{"source":"mobile.iOS-v1.97","hits":5},'
            . '{"source":"web.talis-com-no-release","hits":23},'
            . '{"source":"web.talis-com.12f4d8c.2014-04-29","hits":29},'
            . '{"source":"web.talis-com.4a51b66.2014-04-25","hits":56},'
            . '{"source":"mobile.android-v1.3","hits":4},'
            . '{"source":"mobile.android-v2.0","hits":39},'
            . '{"source":"web.talis-com.9df593e.2014-04-17","hits":44},'
            . '{"source":"web.talis-com.8dac333.2014-04-17","hits":1},'
            . '{"source":"mobile.iOS-v1.99","hits":60},'
            . '{"source":"mobile.iOS-v1.98","hits":116},'
            . '{"source":"web.talis-com.d5e099c.2014-05-15","hits":2},'
            . '{"source":"mobile.android-v1.8","hits":16},'
            . '{"source":"web.talis-com.64ade28.2014-04-17","hits":22},'
            . '{"source":"mobile.iOS-v1.95","hits":10},'
            . '{"source":"mobile.android-v1.4","hits":1},'
            . '{"source":"mobile.android-v1.5","hits":20}'
            . ']}'
        );

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get', '']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with(
                'http://example.com:3002/1/analytics/hits?class=test.player.view',
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer some-token',
                    'Cache-Control' => 'none'
                ]
            )
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getHits('player.view', [], true);

        $this->assertTrue(isset($result['head']));
        $this->assertTrue(isset($result['results']));
    }

    public function testHitsWithOptsReturnsExpectedJSON()
    {
        $this->setRequiredDefines();

        $stubPersonaClient = $this->getMock('\Talis\Persona\Client\Tokens', [], [], '', false);
        $stubPersonaClient->expects($this->once())
            ->method('obtainNewToken')
            ->will($this->returnValue(['access_token' => 'some-token']));

        $response = new \Guzzle\Http\Message\Response('200');
        $response->setBody(
            '{"head":{"type":"hits","class":"test.player.view","group_by":"source","count":27},'
            . '"results":['
            . '{"source":"web.talis-com.b50367b.2014-05-15","hits":45},'
            . '{"source":"web.talis-com.b692220.2014-05-15","hits":9},'
            . '{"source":"mobile.android-v1.9","hits":16},'
            . '{"source":"web.talis-com.f1afa4f.2014-05-13","hits":21},'
            . '{"source":"mobile.android-v1.7","hits":48},'
            . '{"source":"web.talis-com.d165ea5.2014-05-01","hits":411},'
            . '{"source":"web.talis-com.3dceffd.2014-05-15","hits":8},'
            . '{"source":"mobile.android-v1.6","hits":41},'
            . '{"source":"web.talis-com.35baf27.2014-04-29","hits":50},'
            . '{"source":"web.talis-com.13f1318.2014-05-14","hits":18},'
            . '{"source":"web.talis-com.no-release","hits":219},'
            . '{"source":"mobile.iOS-v1.97","hits":5},'
            . '{"source":"web.talis-com-no-release","hits":23},'
            . '{"source":"web.talis-com.12f4d8c.2014-04-29","hits":29},'
            . '{"source":"web.talis-com.4a51b66.2014-04-25","hits":56},'
            . '{"source":"mobile.android-v1.3","hits":4},'
            . '{"source":"mobile.android-v2.0","hits":39},'
            . '{"source":"web.talis-com.9df593e.2014-04-17","hits":44},'
            . '{"source":"web.talis-com.8dac333.2014-04-17","hits":1},'
            . '{"source":"mobile.iOS-v1.99","hits":60},'
            . '{"source":"mobile.iOS-v1.98","hits":116},'
            . '{"source":"web.talis-com.d5e099c.2014-05-15","hits":2},'
            . '{"source":"mobile.android-v1.8","hits":16},'
            . '{"source":"web.talis-com.64ade28.2014-04-17","hits":22},'
            . '{"source":"mobile.iOS-v1.95","hits":10},'
            . '{"source":"mobile.android-v1.4","hits":1},'
            . '{"source":"mobile.android-v1.5","hits":20}]}'
        );

        $mockRequest = $this->getMock('\Guzzle\Http\Message\Request', ['send'], ['get', '']);
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($response));

        $stubHttpClient = $this->getMock('\Guzzle\Http\Client', ['get']);
        $stubHttpClient->expects($this->once())
            ->method('get')
            ->with(
                'http://example.com:3002/1/analytics/hits?'
                . 'class=test.player.view&key=some_key&value=some_value'
            )
            ->will($this->returnValue($mockRequest));

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getPersonaClient', 'getHttpClient']);
        $echoClient->expects($this->once())->method('getPersonaClient')->will($this->returnValue($stubPersonaClient));
        $echoClient->expects($this->once())->method('getHttpClient')->will($this->returnValue($stubHttpClient));

        $result = $echoClient->getHits('player.view', ['key' => 'some_key', 'value' => 'some_value']);

        $this->assertTrue(isset($result['head']));
        $this->assertTrue(isset($result['results']));
    }

    public function testHitsCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getAnalytics']);
        $echoClient->expects($this->once())->method('getAnalytics')->with('some.class', 'hits');

        $echoClient->getHits('some.class');
    }

    public function testAverageCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getAnalytics']);
        $echoClient->expects($this->once())->method('getAnalytics')->with('some.class', 'average');

        $echoClient->getAverage('some.class');
    }

    public function testSumCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getAnalytics']);
        $echoClient->expects($this->once())->method('getAnalytics')->with('some.class', 'sum');

        $echoClient->getSum('some.class');
    }

    public function testMaxCallsAnalytics()
    {
        $this->setRequiredDefines();

        $echoClient = $this->getMock('\Talis\EchoClient\Client', ['getAnalytics']);
        $echoClient->expects($this->once())->method('getAnalytics')->with('some.class', 'max');

        $echoClient->getMax('some.class');
    }

    /* ---------------------------------------------------------------------------------------------------------- */

    /**
     * The dataprovider for testRequiredDefines
     * @see http://phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers
     */
    public function mandatoryDefinesProvider()
    {
        $data = [];

        foreach ($this->arrMandatoryDefines as $defineKey) {
            $data[] = [$defineKey];
        }

        return $data;
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
}
