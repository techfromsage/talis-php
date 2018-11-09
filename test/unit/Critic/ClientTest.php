<?php
namespace Talis\Critic;

if (!defined('APPROOT')) {
    define('APPROOT', dirname(dirname(dirname(__DIR__))));
}

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

    /**
     * Exception thrown when response code is 200
     *
     * @expectedException \Talis\Critic\Exceptions\ReviewException
     */
    public function testCreateReviewException()
    {
        $this->setUp();

        $plugin = new \Guzzle\Plugin\Mock\MockPlugin();
        $plugin->addResponse(new \Guzzle\Http\Message\Response(
            200,
            null,
            json_encode([])
        ));

        $client = new \Guzzle\Http\Client();
        $client->addSubscriber($plugin);

        /** @var \Talis\Critic\Client | PHPUnit_Framework_MockObject_MockObject $criticClient */
        $criticClient = $this->getMock(
            '\Talis\Critic\Client',
            ['getHTTPClient','getHeaders'],
            [$this->criticBaseUrl]
        );

        $criticClient->expects($this->once())
            ->method('getHTTPClient')
            ->will($this->returnValue($client));

        $criticClient->expects($this->once())
            ->method('getHeaders')
            ->will($this->returnValue([]));

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
        $this->setUp();

        $plugin = new \Guzzle\Plugin\Mock\MockPlugin();
        $plugin->addResponse(new \Guzzle\Http\Message\Response(
            401,
            null,
            json_encode([])
        ));

        $client = new \Guzzle\Http\Client();
        $client->addSubscriber($plugin);

        /** @var \Talis\Critic\Client | PHPUnit_Framework_MockObject_MockObject $criticClient */
        $criticClient = $this->getMock(
            '\Talis\Critic\Client',
            ['getHTTPClient','getHeaders'],
            [$this->criticBaseUrl]
        );

        $criticClient->expects($this->once())
            ->method('getHTTPClient')
            ->will($this->returnValue($client));

        $criticClient->expects($this->once())
            ->method('getHeaders')
            ->will($this->returnValue([]));

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
        $this->setUp();

        $plugin = new \Guzzle\Plugin\Mock\MockPlugin();
        $plugin->addResponse(new \Guzzle\Http\Message\Response(
            201,
            null,
            json_encode([])
        ));

        $client = new \Guzzle\Http\Client();
        $client->addSubscriber($plugin);

        /** @var \Talis\Critic\Client | PHPUnit_Framework_MockObject_MockObject $criticClient */
        $criticClient = $this->getMock(
            '\Talis\Critic\Client',
            ['getHTTPClient'],
            [$this->criticBaseUrl]
        );

        $criticClient->expects($this->once())
            ->method('getHTTPClient')
            ->will($this->returnValue($client));

        $criticClient->setPersonaConnectValues($this->personaConfig);

        $criticClient->createReview(
            $this->postFields,
            'someClientId',
            'someClientSecret'
        );
    }
}
