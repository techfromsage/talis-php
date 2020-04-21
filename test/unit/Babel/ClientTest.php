<?php

namespace test\unit\Babel;

/**
 * Travis-CI runs against the unit tests but can only test certain things.
 *
 * You should run the integration tests locally, with a running local Babel server setup, as the
 * integration tests actually prove that this client library can read/write to Babel correctly.
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    private $babelClient;
    private $baseCreateAnnotationData;

    protected function setUp()
    {
        $this->babelClient = new \Talis\Babel\Client('http://someHost', '3001');

        $this->baseCreateAnnotationData = [
            'annotatedBy' => 'a',
            'hasTarget' => [
                'uri' => 'http://foo'
            ],
            'hasBody' => [
                'type' => 't',
                'format' => 'f',
            ]
        ];
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage host must be specified
     */
    public function testConstructorFailure()
    {
        $client = new \Talis\Babel\Client(null, null);

        $target = null;
        $token = 'personaToken';

        $client->getTargetFeed($target, $token);
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage Missing target
     */
    public function testGetTargetWithNoTarget()
    {
        $target = null;
        $token = 'personaToken';

        $this->babelClient->getTargetFeed($target, $token);
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage Missing target or token
     */
    public function testGetTargetFeedWithNoToken()
    {
        $this->babelClient->getTargetFeed('target', null);
    }

    /**
     * @expectedException \Talis\Babel\InvalidPersonaTokenException
     * @expectedExceptionMessage No persona token specified
     */
    public function testCreateAnnotationMissingToken()
    {
        $this->babelClient->createAnnotation(
            null,
            $this->baseCreateAnnotationData
        );
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage Missing hasBody in data array
     */
    public function testCreateAnnotationMissingHasBody()
    {
        unset($this->baseCreateAnnotationData['hasBody']);
        $this->babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage hasBody must be an array containing format and type
     */
    public function testCreateAnnotationHasBodyNotArray()
    {
        $this->baseCreateAnnotationData['hasBody'] = 'foo';
        $this->babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage Missing format in data array
     */
    public function testCreateAnnotationMissingHasBodyFormat()
    {
        unset($this->baseCreateAnnotationData['hasBody']['format']);
        $this->babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage Missing type in data array
     */
    public function testCreateAnnotationMissingHasBodyType()
    {
        unset($this->baseCreateAnnotationData['hasBody']['type']);
        $this->babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage Missing annotatedBy in data array
     */
    public function testCreateAnnotationMissingAnnotatedBy()
    {
        unset($this->baseCreateAnnotationData['annotatedBy']);
        $this->babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage Missing hasTarget in data array
     */
    public function testCreateAnnotationMissingHasTarget()
    {
        unset($this->baseCreateAnnotationData['hasTarget']);
        $this->babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    /**
     * @expectedException \Talis\Babel\ClientException
     * @expectedExceptionMessage hasTarget must be an array containing uri
     */
    public function testCreateAnnotationHasTargetIsNotArray()
    {
        $this->baseCreateAnnotationData['hasTarget'] = 'foo';
        $this->babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    public function testCreateAnnotationErrorMessage()
    {
        $responseBody = ['message' => 'Some kind of validation failure'];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(400, [], json_encode($responseBody)),
        ]);

        $this->setExpectedException(
            'Talis\Babel\ClientException',
            "Error 400 for /annotations: {$responseBody['message']}"
        );

        $babelClient->createAnnotation(
            'someToken',
            $this->baseCreateAnnotationData
        );
    }

    public function testGetAnnotationsSuccess()
    {
        $responseBody = [
            'count' => 2,
            'annotations' => [
                [
                    'hasBody' => [
                        'type' => 'Text',
                        'format' => 'text/plain',
                        'chars' => 'This is a simple text annotation',
                    ],
                    'hasTarget' => [
                        'uri' => 'http://some-video',
                        'fragment' => 't=npt:0,5',
                    ],
                    'annotatedBy' => 'users:1234',
                    'annotatedAt' => '2007-03-01T13:00:00Z',
                    'motivatedBy' => 'commenting',
                    'serializedBy' => 'SOME_OAUTH_CLIENT_ID',
                ],
                [
                    'hasBody' => [
                        'type' => 'Text',
                        'format' => 'text/plain',
                        'chars' => 'This is a simple text annotation',
                    ],
                    'hasTarget' => [
                        'uri' => 'http://some-video',
                        'fragment' => 't=npt:0,5',
                    ],
                    'annotatedBy' => 'users:1234',
                    'motivatedBy' => 'commenting',
                ],
            ],
        ];
        $history = [];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($responseBody)),
        ], $history);

        $actualBody = $babelClient->getAnnotations('someToken', ['annotatedBy' => '/users/1234']);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals($responseBody, $actualBody);
        $this->assertEquals(
            '/annotations?annotatedBy=' . urlencode('/users/1234'),
            (string) $request->getUri()
        );
    }

    public function testGetFeedSuccess()
    {
        $responseBody = [
            'annotations' => ['51e45ed9bf55dcf9b7000001'],
            'feed_length' => 1,
            'delta_token' => '1',
            'limit' => 25,
            'offset' => 0,
        ];
        $history = [];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($responseBody)),
        ], $history);

        $actualBody = $babelClient->getTargetFeed('1234', 'someToken');
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals($responseBody, $actualBody);
        $this->assertRegExp(
            '#^/feeds/targets/[a-f0-9]{32}/activity/annotations$#',
            (string) $request->getUri()
        );
    }

    public function testGetFeedHydrateSuccess()
    {
        $responseBody = [
            'annotations' => [
                [
                    '_id' => '51e45ed9bf55dcf9b7000001',
                    '__v' => 0,
                    'annotatedBy' => 'mqdOrdxgRJA1jGWjs-O58A',
                    'annotatedAt' => '2013-07-15T20:43:05.660Z',
                    'motivatedBy' => 'commenting',
                    'hasTarget' => ['uri' => 'http://some/location'],
                    'hasBody' => [
                        'format' => 'text/plain',
                        'type' => 'Text',
                        'chars' => 'sometext',
                    ],
                ],
            ],
            'feed_length' => 1,
            'userProfiles' => ['mqdOrdxgRJA1jGWjs-O58A' => ['name' => 'russ']],
            'delta_token' => '1',
            'limit' => 25,
            'offset' => 0,
        ];
        $history = [];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($responseBody)),
        ], $history);

        $actualBody = $babelClient->getTargetFeed('1234', 'someToken', true, ['delta_token' => '1']);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals($responseBody, $actualBody);
        $this->assertRegExp(
            '#^/feeds/targets/[a-f0-9]{32}/activity/annotations/hydrate\?delta_token=1$#',
            (string) $request->getUri()
        );
    }

    public function testGetFeedErrorMessage()
    {
        $path = '/feeds/targets/' . md5('1234') . '/activity/annotations';
        $responseBody = ['message' => 'Something important was left out'];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(400, [], json_encode($responseBody)),
        ]);

        $this->setExpectedException(
            'Talis\Babel\ClientException',
            'Error 400 for ' . $path . ': ' . $responseBody['message']
        );

        $babelClient->getTargetFeed('1234', 'someToken');
    }

    public function testGetFeedErrorOnEmptyReponse()
    {
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], '{"garbage"}'),
        ]);

        $this->setExpectedException(
            'Talis\Babel\ClientException',
            'Failed to decode JSON response: {"garbage"}'
        );

        $babelClient->getTargetFeed('1234', 'someToken');
    }

    public function testGetFeedCountSuccess()
    {
        $responseBody = ['message' => 'Something went bang'];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, ['X-Feed-New-Items' => [42]]),
        ]);

        $this->assertEquals(42, $babelClient->getTargetFeedCount('1234', 'someToken'));
    }

    public function testGetFeedCountErrorMessage()
    {
        $path = '/feeds/targets/' . md5('1234') . '/activity/annotations?delta_token=0';
        $responseBody = ['message' => 'Something went bang'];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(500, [], json_encode($responseBody)),
        ]);

        $this->setExpectedException(
            'Talis\Babel\ClientException',
            'Error 500 for ' . $path
        );

        $babelClient->getTargetFeedCount('1234', 'someToken');
    }

    public function testGetFeedCountErrorIfHeaderMissing()
    {
        $responseBody = ['message' => 'Something went bang'];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, []),
        ]);

        $this->setExpectedException(
            'Talis\Babel\ClientException',
            'Unexpected amount of X-Feed-New-Items headers returned'
        );

        $babelClient->getTargetFeedCount('1234', 'someToken');
    }

    public function testGetFeedCountErrorIfTooManyValues()
    {
        $responseBody = ['message' => 'Something went bang'];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, ['X-Feed-New-Items' => [3, 7]]),
        ]);

        $this->setExpectedException(
            'Talis\Babel\ClientException',
            'Unexpected amount of X-Feed-New-Items headers returned'
        );

        $babelClient->getTargetFeedCount('1234', 'someToken');
    }

    public function testGetFeedsSuccess()
    {
        $responseBody = [
            'annotations' => [
                ['_id' => '51e45ed9bf55dcf9b7000001'],
                ['_id' => '51e5347c4c7f72d861000002'],
            ],
            'feed_length' => 2,
        ];
        $history = [];
        $babelClient = $this->getClientWithMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($responseBody)),
        ], $history);

        $actualBody = $babelClient->getFeeds(['123', '456'], 'someToken');
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = array_pop($history)['request'];
        $this->assertEquals($responseBody, $actualBody);
        $this->assertEquals(
            '/feeds/annotations/hydrate?feed_ids=' . urlencode('123,456'),
            (string) $request->getUri()
        );
    }

    /**
     * Gets the client with mocked HTTP responses.
     *
     * @param \GuzzleHttp\Psr7\Response[] $responses The responses
     * @param array $history History middleware container
     * @return \Talis\Babel\Client|\PHPUnit_Framework_MockObject_MockObject The client.
     */
    private function getClientWithMockResponses(array $responses, array &$history = null)
    {
        $mockHandler = new \GuzzleHttp\Handler\MockHandler($responses);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);

        if (isset($history)) {
            $handlerStack->push(\GuzzleHttp\Middleware::history($history));
        }

        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $babelClient = $this->getMockBuilder(\Talis\Babel\Client::class)
            ->setMethods(['getHTTPClient'])
            ->setConstructorArgs(['http://someHost', '3001'])
            ->getMock();

        $babelClient->expects($this->once())
            ->method('getHTTPClient')
            ->willReturn($httpClient);

        $babelClient->setLogger(new \Psr\Log\NullLogger());

        return $babelClient;
    }
}
