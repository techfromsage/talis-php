<?php

namespace Talis\Persona\Client;

use \Guzzle\Plugin\Cache\CacheStorageInterface;
use \Guzzle\Plugin\Cache\DefaultRevalidation;
use \Guzzle\Plugin\Cache\DefaultCacheStorage;
use \Guzzle\Plugin\Cache\SkipRevalidation;
use \Guzzle\Cache\DoctrineCacheAdapter;
use \Guzzle\Plugin\Cache\CachePlugin;
use \Guzzle\Http\Client;

use \Doctrine\Common\Cache\CacheProvider;

class HttpClientFactory
{
    /** @var CacheProvider Object used to cache responses */
    protected $cacheBackend;

    /** @var string endpoint to contact */
    protected $host;

    /** @var array configuration */
    protected $config;

    /**
     * Constructor
     *
     * @param string $host http endpoint (format: 'protocol://host')
     * @param DoctrineCachePlugin $cacheBackend cache for http responses
     * @param array $opts configuration options
     *      keyPrefix: prefix for the cache key (default: '')
     *      defaultTtl: time to live for the cache (default: 3600)
     *      autoPurge: automatically clear out old cache (default: true)
     */
    public function __construct(
        $host,
        CacheProvider $cacheBackend,
        array $opts = []
    ) {
        if (empty($host) || empty($cacheBackend)) {
            throw new \InvalidArgumentException('invalid arguments');
        }

        $this->host = $host;
        $this->cacheBackend = $cacheBackend;

        $this->config = array_merge(
            [
                'keyPrefix' => '',
                'defaultTtl' => 3600,
                'autoPurge' => true,
            ],
            $opts
        );
    }

    /**
     * Create a http client with caching that adheres to common caching rules
     *
     * @param boolean $skipRevalidation whether to skip validating a previous
     *    request that has been cached. The validation uses the remote server
     *    to retrieve the current etag/cache headers & compare them against the
     *    original values.
     * @return \Guzzle\Http\Client
     */
    public function create($skipRevalidation = false)
    {
        $httpClient = $this->createGuzzleClient();
        $cachePlugin = $this->createCachePlugin(
            $this->createStorage(),
            $skipRevalidation
        );

        $httpClient->addSubscriber($cachePlugin);
        return $httpClient;
    }

    /**
     * Create guzzle http client instance
     * @return \Guzzle\Http\Client
     */
    protected function createGuzzleClient()
    {
        return new Client($this->host);
    }

    /**
     * Create storage wrapper for the cache backend
     * @return Guzzle\Plugin\Cache\CacheStorageInterface
     */
    protected function createStorage()
    {
        $adapter = new DoctrineCacheAdapter($this->cacheBackend);
        $storage = new DefaultCacheStorage(
            $adapter,
            $this->config['keyPrefix'],
            $this->config['defaultTtl']
        );

        return $storage;
    }

    /**
     * Create a cache plugin which includes mechanisms for caching http calls
     * and revalidation of the original request/responses.
     * @param CacheStorageInterface $storage object to store the cache
     * @param boolean $skipRevalidation whether to skip validating a previous
     *     request that has been cached. The validation uses the remote server
     *     to retrieve the current etag/cache headers & compare them against the
     *     original values.
     * @return CachePlugin
     */
    protected function createCachePlugin(
        CacheStorageInterface $storage,
        $skipRevalidation = false
    ) {
        if ($skipRevalidation) {
            $revalidation = new SkipRevalidation();
        } else {
            $revalidation = new DefaultRevalidation($storage);
        }

        return new CachePlugin(
            [
                'storage' => $storage,
                'auto_purge' => $this->config['autoPurge'],
                'revalidation' => $revalidation,
            ]
        );
    }
}