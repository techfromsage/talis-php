<?php

namespace Talis\Persona\Client;

use Monolog\Logger;
use Guzzle\Http\Exception\RequestException;
use \Domnikl\Statsd\Connection\Socket;
use \Domnikl\Statsd\Connection\Blackhole;
use \Guzzle\Http\Client as GuzzleClient;
use \Talis\Persona\Client\ClientVersionCache;

abstract class Base
{
    use ClientVersionCache;

    const STATSD_CONN = 'STATSD_CONN';
    const STATSD_PREFIX = 'STATSD_PREFIX';
    const LOGGER_NAME = 'PERSONA';
    const PERSONA_API_VERSION = '3';

    /**
     * Configuration object
     * @var Array
     */
    protected $config = null;

    /**
     * StatsD client
     * @var \Domnikl\Statsd\Client
     */
    private $statsD;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Doctrine\Common\Cache\CacheProvider
     */
    private $cacheBackend;

    /**
     * @var string
     */
    private $keyPrefix;

    /**
     * @var int
     */
    private $defaultTtl;

    /**
     * @var string
     */
    private $phpVersion;

    /**
     * Constructor
     *
     * @param array $config An array of options with the following keys: <pre>
     *      persona_host: (string) the persona host you'll be making requests to (e.g. 'http://localhost')
     *      persona_admin_host: (string) the persona admin host
     *      userAgent: Consuming application user agent string @since 2.0.0
     *            examples: rl/1723-9095ba4, rl/5.2, rl, rl/5, rl/5.2 (php/5.3; linux/2.5)
     *      cacheBackend: (Doctrine\Common\Cache\CacheProvider) cache storage
     *      cacheKeyPrefix: (string) optional prefix to append to the cache keys
     *      cacheDefaultTTL: (integer) optional cache TTL value
     * @throws \InvalidArgumentException If any of the required config parameters are missing
     * @throws \InvalidArgumentException If the user agent format is invalid
     */
    public function __construct(array $config)
    {
        $this->checkConfig($config);
        $this->config = $config;
        $this->config['persona_oauth_route'] = '/oauth/tokens';

        $userAgentPattern = '' .
            '/^[a-z0-9\-\._]+' .             // name of application
            '(\/' .                          // optional version beginning with /
            '[^\s]+' .                       // anything but whitespace
            ')?' .
            '( \([^\)]+\))?$/i';             // comment surrounded by round brackets

        $isValidUserAgent = preg_match(
            $userAgentPattern,
            $config['userAgent']
        );

        if ($isValidUserAgent == false) {
            throw new \InvalidArgumentException(
                "user agent format is not valid ({$config['userAgent']})"
            );
        }

        $this->logger = $this->get($config, 'logger', null);
        $this->cacheBackend = $config['cacheBackend'];
        $this->phpVersion = phpversion();
    }

    /**
     * Lazy-load statsD
     * @return \Domnikl\Statsd\Client
     */
    public function getStatsD()
    {
        if (is_null($this->statsD)) {
            $connStr = getenv(self::STATSD_CONN);

            if (!empty($connStr) && !empty(strpos($connStr, ':'))) {
                list($host, $port) = explode(':', $connStr);
                $conn = new Socket($host, $port);
            } else {
                $conn = new Blackhole();
            }

            $this->statsD = new \Domnikl\Statsd\Client($conn);
            $prefix = getenv(self::STATSD_PREFIX);

            if (empty($prefix)) {
                $prefix = 'persona.php.client';
            }

            $this->statsD->setNamespace($prefix);
        }

        return $this->statsD;
    }

    /**
     * Checks the supplied config, verifies that all required parameters are present and
     * contain a non null value;
     *
     * @param array $config the configuration options to validate
     * @throws \InvalidArgumentException If the config is invalid
     */
    protected function checkConfig(array $config)
    {
        $requiredProperties = [
            'userAgent',
            'persona_host',
            'persona_admin_host',
            'cacheBackend',
        ];

        foreach ($requiredProperties as $requiredProperty) {
            if (!isset($config[$requiredProperty])) {
                throw new \InvalidArgumentException(
                    "Configuration missing $requiredProperty"
                );
            }
        }
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->logger == null) {
            $this->logger = new Logger(self::LOGGER_NAME);
        }

        return $this->logger;
    }

    /**
     * Returns a unique id for tracing this request.
     * If there is already a value set as a header it uses that, otherwise it
     * generates a new one and sets that on $_SERVER
     * @return string
     */
    protected function getRequestId()
    {
        $requestId = null;
        if (array_key_exists('HTTP_X_REQUEST_ID', $_SERVER)) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'];
        }

        return empty($requestId) ? uniqid() : $requestId;
    }

    /**
     * Create a http client
     * @param string $host host to send a request to
     * @return \Guzzle\Http\Client http client
     */
    protected function getHTTPClient($host)
    {
        return new GuzzleClient($host);
    }

    /**
     * Create a HTTP request with a predefined set of headers
     * @param string $url url to request
     * @param array $opts options
     * @return mixed http request
     */
    protected function createRequest($url, array $opts)
    {
        $httpKeys = ['timeout', 'body'];
        $definedHttpConfig = array_intersect_key($opts, array_flip($httpKeys));

        $opts = array_merge(
            [
                'headers' => [
                    'Cache-Control' => 'max-age=0, no-cache',
                ],
                'method' => 'GET',
                'expectResponse' => true,
                'addContentType' => true,
                'parseJson' => true,
            ],
            $opts
        );

        $version = $this->getClientVersion();
        $httpConfig = array_merge(
            [
                'timeout' => 30,
                'User-Agent' => "{$this->config['userAgent']}"
                . "persona-php-client/{$version} "
                . "(php/{$this->phpVersion})",
                'X-Request-ID' => $this->getRequestId(),
                'X-Client-Version' => $version,
                'X-Client-Language' => 'php',
                'X-Client-Consumer' => $this->config['userAgent'],
            ],
            $definedHttpConfig
        );

        $body = isset($opts['body']) ? $opts['body'] : null;

        if (isset($opts['bearerToken'])) {
            $httpConfig['headers']['Authorization'] = "Bearer {$opts['bearerToken']}";
        }

        if ($body != null && $opts['addContentType']) {
            $httpConfig['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $client = $this->getHTTPClient($this->config['persona_host']);
        $request = $client->createRequest(
            $opts['method'],
            $url,
            $opts['headers'],
            $body,
            $httpConfig
        );

        return $request;
    }

    /**
     * Perform the request according to the $curlOptions.
     *
     * @param string $url request url
     * @param array $opts configuration / options:
     *      timeout: (30 seconds) HTTP timeout
     *      body: optional HTTP body
     *      headers: optional HTTP headers
     *      method: (default GET) HTTP method
     *      expectResponse: (default true) parse the http response
     *      addContentType: (default true) add type application/x-www-form-urlencoded
     *      parseJson: (default true) parse the response as JSON
     * @return array|null|string response body
     * @throws NotFoundException If the http status was a 404
     * @throws \Exception If response not 200 and valid JSON
     */
    protected function performRequest($url, array $opts = [])
    {
        $request = $this->createRequest($url, $opts);

        try {
            $response = $request->send();
        } catch (RequestException $exception) {
            $response = $exception->getRequest()->getResponse();

            if (isset($response)) {
                $status = $response->getStatusCode();
            } else {
                $status = -1;
            }

            if ($status === 404) {
                throw new NotFoundException();
            }

            throw new \Exception(
                "Did not retrieve successful response code from persona: ${status}",
                $status
            );
        }

        return $this->parseResponse($url, $response, $opts);
    }

    /**
     * Parse the response from Persona.
     * @param string $url url
     * @param mixed $response response from persona
     * @param array $opts options
     * @return string|array
     */
    protected function parseResponse($url, $response, array $opts)
    {
        $parseJson = $this->get($opts, 'parseJson', true) === true;
        $expectResponse = $this->get($opts, 'expectResponse', true) === true;
        $expectedResponseCode = $expectResponse ? 200 : 204;
        $statusCode = $response->getStatusCode();

        if ($statusCode !== $expectedResponseCode) {
            $this->getLogger()->error(
                'Did not retrieve expected response code',
                ['opts' => $opts, 'url' => $url, 'response' => $response]
            );

            throw new \Exception(
                'Did not retrieve expected response code from persona',
                $statusCode
            );
        }

        // Not expecting a body to be returned
        if ($expectResponse === false) {
            return null;
        }

        if ($parseJson === false) {
            return $response->getBody();
        }

        $json = json_decode($response->getBody(), true);

        if (empty($json)) {
            $this->getLogger()->error(
                "Could not parse response {$response} as JSON"
            );

            throw new \Exception(
                "Could not parse response from persona as JSON {$response->getBody()}"
            );
        }

        return $json;
    }

    /**
     * Get a value from a array, or return a default if the key doesn't exist.
     * @param array $array array to find the value within
     * @param string $key key to find the value from
     * @param mixed $default value to return when key doesn't exist
     * @return mixed
     */
    protected function get(array $array, $key, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        return $default;
    }

    /**
     * Retrieve the cache backend
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    protected function getCacheBackend()
    {
        return $this->cacheBackend;
    }

    /**
     * Return Persona admin host from the configuration object
     * @return string
     */
    protected function getPersonaAdminHost()
    {
        return $this->config['persona_admin_host'] . '/' . self::PERSONA_ADMIN_API_VERSION;
    }

    /**
     * Return Persona host from the configuration object
     * @return string
     */
    protected function getPersonaHost()
    {
        return $this->config['persona_host'] . '/' . self::PERSONA_API_VERSION;
    }

    /**
     * Attempts to find an access token based on the current request.
     * It first looks at $_SERVER headers for a Bearer, failing that
     * it checks the $_GET and $_POST for the access_token param.
     * If it can't find one it throws an exception.
     *
     * @return string access token
     * @throws \Exception Missing or invalid access token
     */
    protected function getTokenFromRequest()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }

            $withoutPrefix = strtolower(substr($key, 5));
            $removedUnderscores = str_replace('_', ' ', $withoutPrefix);
            $header = str_replace(' ', '-', ucwords($removedUnderscores));
            $headers[$header] = $value;
        }

        if (isset($headers['Bearer'])) {
            if (!preg_match('/Bearer\s(\S+)/', $headers['Bearer'], $matches)) {
                throw new \Exception('Malformed auth header');
            }

            return $matches[1];
        }

        if (isset($_GET['access_token'])) {
            return $_GET['access_token'];
        }

        if (isset($_POST['access_token'])) {
            return $_POST['access_token'];
        }

        $this->getLogger()->error('No OAuth token supplied in headers, GET or POST');
        throw new \Exception('No OAuth token supplied');
    }
}
