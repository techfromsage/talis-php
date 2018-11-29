<?php

$appRoot = dirname(dirname(dirname(__DIR__)));
require_once $appRoot . '/test/unit/TestBase.php';

use \Talis\Persona\Client\Login;
use \Talis\Persona\Client\HttpClientFactory;
use \Doctrine\Common\Cache\ArrayCache;
use \Guzzle\Http\Message\Response;
use \Guzzle\Plugin\Mock\MockPlugin;
use \Guzzle\Http\Exception\CurlException;

class HttpClientFactoryTest extends TestBase
{
    public function testDefaultCaching()
    {
        $cacheBackend = $this->getMock(
            '\Doctrine\Common\Cache\ArrayCache',
            ['doSave']
        );

        $lifetimes = [];
        $cacheBackend->expects($this->exactly(2))
            ->method('doSave')
            ->will($this->returnCallback(function($id, $data, $lifetime) use (&$lifetimes) {
                array_push($lifetimes, $lifetime);
            }));

        $factory = new HttpClientFactory(
            'http://localhost',
            $cacheBackend
        );

        $httpClient = $factory->create();

        $plugin = new MockPlugin();
        $plugin->addResponse(new Response(200, [], 'body'));
        $httpClient->addSubscriber($plugin);

        $request = $httpClient->createRequest('get', '/test/path');
        $response = $request->send();

        $this->assertEquals([3600, 0], $lifetimes);
    }

    public function testSettingKeyPrefix()
    {
        $cacheBackend = $this->getMock(
            '\Doctrine\Common\Cache\ArrayCache',
            ['doSave']
        );

        $cacheBackend->expects($this->exactly(2))
            ->method('doSave')
            ->will($this->returnCallback(function($id, $data, $lifetime) {
                $this->assertTrue(strpos($id, 'prefix_') === 1);
            }));

        $factory = new HttpClientFactory(
            'http://localhost',
            $cacheBackend,
            ['keyPrefix' => 'prefix_']
        );

        $httpClient = $factory->create();

        $plugin = new MockPlugin();
        $plugin->addResponse(new Response(200, [], 'body'));
        $httpClient->addSubscriber($plugin);

        $request = $httpClient->createRequest('get', '/test/path');
        $response = $request->send();
    }

    public function testSettingTtl()
    {
        $cacheBackend = $this->getMock(
            '\Doctrine\Common\Cache\ArrayCache',
            ['doSave']
        );

        $lifetimes = [];
        $cacheBackend->expects($this->exactly(2))
            ->method('doSave')
            ->will($this->returnCallback(function($id, $data, $lifetime) use (&$lifetimes) {
                array_push($lifetimes, $lifetime);
            }));

        $factory = new HttpClientFactory(
            'http://localhost',
            $cacheBackend,
            ['defaultTtl' => 300]
        );

        $httpClient = $factory->create();

        $plugin = new MockPlugin();
        $plugin->addResponse(new Response(200, [], 'body'));
        $httpClient->addSubscriber($plugin);

        $request = $httpClient->createRequest('get', '/test/path');
        $response = $request->send();

        $this->assertEquals([300, 0], $lifetimes);
    }

    public function testSecondRequestUsesCache()
    {
        $cacheBackend = new ArrayCache();

        $factory = new HttpClientFactory(
            'http://localhost',
            $cacheBackend
        );

        $httpClient = $factory->create();
        $plugin = new MockPlugin();
        $plugin->addResponse(new Response(200, [], 'body'));
        $httpClient->addSubscriber($plugin);

        $request = $httpClient->createRequest('get', '/test/path');
        $response = $request->send();

        $httpClient = $factory->create();
        $request = $httpClient->createRequest('get', '/test/path');
        $response = $request->send();

        $this->assertEquals('body', $response->getBody());
    }

    public function testSkipRevalidation()
    {
        $cacheBackend = new ArrayCache();

        $factory = new HttpClientFactory(
            'http://localhost',
            $cacheBackend
        );

        $httpClient = $factory->create();
        $plugin = new MockPlugin();
        $plugin->addResponse(new Response(
            200,
            ['ETag' => '1c3-54c7d0388d415'],
            'body'
        ));

        $httpClient->addSubscriber($plugin);

        $request = $httpClient->createRequest('get', '/test/path');
        $response = $request->send();

        // create a client that skip revalidation
        $skipRevalidation = true;
        $httpClient = $factory->create($skipRevalidation);
        $request = $httpClient->createRequest('get', '/test/path');

        // a exception is thrown if Guzzle attempts to make the request
        $response = $request->send();

        $this->assertEquals('body', $response->getBody());
    }
}
