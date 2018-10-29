<?php
namespace Talis\EchoClient;

use \Monolog\Handler\StreamHandler;
use \Monolog\Logger;

use \Talis\EchoClient\Event;

/**
 * Sends events to Echo, if an echo server is enabled.
 */
class Client
{
    const ECHO_API_VERSION = 1;

    const ECHO_ANALYTICS_HITS = 'hits';
    const ECHO_ANALYTICS_MAX = 'max';
    const ECHO_ANALYTICS_SUM = 'sum';
    const ECHO_ANALYTICS_AVG = 'average';
    const ECHO_MAX_BATCH_EVENTS = 100;
    const ECHO_MAX_BATCH_SIZE_IN_BYTES = 1000000;

    private $personaClient;
    private $tokenCacheClient;
    private $debugEnabled = false;
    private static $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        /*
         * The calling project needs to have already set these up.
         */

        if (!defined('OAUTH_USER')) {
            throw new \Exception('Missing define: OAUTH_USER');
        }

        if (!defined('OAUTH_SECRET')) {
            throw new \Exception('Missing define: OAUTH_SECRET');
        }

        if (!defined('PERSONA_HOST')) {
            throw new \Exception('Missing define: PERSONA_HOST');
        }

        if (!defined('PERSONA_OAUTH_ROUTE')) {
            throw new \Exception('Missing define: PERSONA_OAUTH_ROUTE');
        }

        if (!defined('PERSONA_TOKENCACHE_HOST')) {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_HOST');
        }

        if (!defined('PERSONA_TOKENCACHE_PORT')) {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_PORT');
        }

        if (!defined('PERSONA_TOKENCACHE_DB')) {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_DB');
        }

        if (!defined('ECHO_CLASS_PREFIX')) {
            define('ECHO_CLASS_PREFIX', '');
        }
    }

    /**
     * Adds a single event to echo
     *
     * @param string $class event class
     * @param string $source event source
     * @param array $props event properties
     * @param string|null $userId user id related to event
     * @param string|null $timestamp unix timestamp
     * @return boolean True if successful, else false
     */
    public function createEvent(
        $class,
        $source,
        array $props = [],
        $userId = null,
        $timestamp = null
    ) {
        try {
            $event = new \Talis\EchoClient\Event(
                $class,
                $source,
                $props,
                $userId,
                $timestamp
            );

            return $this->sendBatchEvents([$event]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Adds multiple events to echo
     *
     * @param \Talis\EchoClient\Event[] $events An array of EchoEvent objects
     * @return boolean True if successful
     * @throws PayloadTooLargeException If the size, in bytes, of batched events exceeds configured limit of 1mb
     * @throws BadEventDataException If any of the events in the batch are not EchoEvent objects
     * @throws TooManyEventsInBatchException If the number of events in the batch exceeds configured limit of 100 events
     * @throws HttpException If the server responded with an error
     * @throws CouldNotSendDataException If we were unable to send data to the Echo server
     */
    public function sendBatchEvents(array $events)
    {
        if (empty($events)) {
            return true;
        }

        if (count($events) > self::ECHO_MAX_BATCH_EVENTS) {
            $this->getLogger()->warning(
                'Batch contains more than '
                .  self::ECHO_MAX_BATCH_EVENTS
                . ' events'
            );

            throw new \Talis\EchoClient\TooManyEventsInBatchException(
                'Batch of events exceeds the maximum allowed size'
            );
        }

        foreach ($events as $event) {
            if (!$event instanceof \Talis\EchoClient\Event) {
                throw new \Talis\EchoClient\BadEventDataException(
                    'Batch must only contain Echo Event objects'
                );
            }
        }

        $eventsJson = json_encode($events, true);

        // strlen returns no. bytes in a string.
        $sizeOfBatch = $this->getStringSizeInBytes($eventsJson);
        if ($sizeOfBatch > self::ECHO_MAX_BATCH_SIZE_IN_BYTES) {
            throw new \Talis\EchoClient\PayloadTooLargeException(
                'Batch must be less than 1mb in size'
            );
        }

        return $this->sendJsonEventDataToEcho($eventsJson);
    }

    /**
     * Get most recent events of $class with property matching $key and $value,
     * up to a $limit.
     *
     * @param string|null $class event class
     * @param string|null $key event key
     * @param string|null $value event value
     * @param integer $limit maximum amount of events to return
     * @return array events
     * @throws \Exception Communication errors with Echo
     */
    public function getRecentEvents(
        $class = null,
        $key = null,
        $value = null,
        $limit = 25
    ) {
        return $this->getEvents($class, $key, $value, $limit);
    }

    /**
     * Retrieve events from Echo
     *
     * @param string $class event class
     * @param string $key event key to filter on
     * @param mixed $value event value to filter on
     * @param integer $limit maximum amouint to return
     * @param integer $offset offset from the beginning
     * @param string $format response format, csv or json (default: json)
     * @param integer $from events from a certain point in time (unix timestamp)
     * @param integer $to events up to a certain point in time (unix timestamp)
     * @throws \Exception Communication issues with Echo
     * @return array|string an array if the response is json, otherwise a string
     */
    public function getEvents(
        $class = null,
        $key = null,
        $value = null,
        $limit = 25,
        $offset = 0,
        $format = null,
        $from = null,
        $to = null
    ) {
        if (!empty($class)) {
            $class = ECHO_CLASS_PREFIX . $class;
        }

        $baseUrl = $this->getBaseUrl();

        if (!$baseUrl) {
            // fail silently when creating events, should not stop user
            // interaction as echo events are collected on a best-endeavours
            // basis
            $this->getLogger()->warning(
                'Echo server is not defined (missing ECHO_HOST define), '
                . "not getting events - $class"
            );

            return false;
        }

        $eventUrl = "$baseUrl/events?";
        $params = [];

        if (!empty($limit)) {
            $params['limit'] = $limit;
        }
        if (!empty($offset)) {
            $params['offset'] = $offset;
        }
        if (!empty($class)) {
            $params['class'] = $class;
        }
        if (!empty($key)) {
            $params['key'] = $key;
        }
        if (!empty($value)) {
            $params['value'] = $value;
        }
        if (!empty($format)) {
            $params['format'] = $format;
        }
        if (!empty($to)) {
            $params['to'] = date('c', $to);
        }
        if (!empty($from)) {
            $params['from'] = date('c', $from);
        }

        $eventUrl .= http_build_query($params);

        try {
            $client = $this->getHttpClient();
            $request = $client->get(
                $eventUrl,
                $this->getHeaders(),
                ['connect_timeout' => 2]
            );

            $response = $request->send();
            if ($response->isSuccessful()) {
                switch ($format) {
                    case 'csv':
                        $result = $response->getBody(true);

                        if ($result) {
                            return $result;
                        }

                        break;
                    default:
                        $result = json_decode($response->getBody(true), true);

                        if (isset($result['events'])) {
                            $this->getLogger()->debug(
                                "Success getting events from echo - $class"
                            );

                            return $result['events'];
                        }
                }

                $this->getLogger()->warning(
                    "Failed getting events from echo - $class",
                    [
                        'responseCode' => $response->getStatusCode(),
                        'responseBody' => $response->getBody(true),
                    ]
                );

                throw new \Exception(
                    'Failed getting events from echo, could not decode response'
                );
            } else {
                $this->getLogger()->warning(
                    "Failed getting events from echo - $class",
                    [
                        'responseCode' => $response->getStatusCode(),
                        'responseBody' => $response->getBody(true),
                    ]
                );

                throw new \Exception(
                    'Failed getting events from echo, response was not successful'
                );
            }
        } catch (\Exception $e) {
            // For any exception issue, just log the issue and fail silently.
            // E.g. failure to connect to echo server, or whatever.
            $this->getLogger()->warning(
                "Failed getting events from echo - $class",
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Get hits analytics from echo
     *
     * @param string $class event class
     * @param array $opts (optional) params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     * @param boolean $noCache if to cache the request
     * @return array|string echo response
     * @throws \Exception Echo communication issues
     */
    public function getHits($class, array $opts = [], $noCache = false)
    {
        return $this->getAnalytics(
            $class,
            self::ECHO_ANALYTICS_HITS,
            $opts,
            $noCache
        );
    }

    /**
     * Get sum analytics from echo
     *
     * @param string $class event class
     * @param array $opts (optional) params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     * @return array|string echo response
     * @throws \Exception Echo communication issues
     */
    public function getSum($class, array $opts = [])
    {
        return $this->getAnalytics($class, self::ECHO_ANALYTICS_SUM, $opts);
    }

    /**
     * Get max analytics from echo
     *
     * @param string $class event class
     * @param array $opts (optional params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     * @return array|string echo response
     * @throws \Exception Echo communication issues
     */
    public function getMax($class, array $opts = [])
    {
        return $this->getAnalytics($class, self::ECHO_ANALYTICS_MAX, $opts);
    }

    /**
     * Get average analytics from echo
     *
     * @param string $class event class
     * @param array $opts (optional) params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     * @return array|string echo response
     * @throws \Exception Echo communication issues
     */
    public function getAverage($class, array $opts = [])
    {
        return $this->getAnalytics($class, self::ECHO_ANALYTICS_AVG, $opts);
    }

    /**
     * Request for analytics.
     *
     * @param string $class event class
     * @param string $type analytic type
     * @param array $opts optional params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     * @param boolean $noCache whether to cache the request
     * @return array|string an array if the result is json, otherwise a string
     * @throws \Exception Echo communication issues
     */
    protected function getAnalytics(
        $class,
        $type,
        array $opts = [],
        $noCache = false
    ) {
        $class = ECHO_CLASS_PREFIX . $class;
        $baseUrl = $this->getBaseUrl();

        if (!in_array(
            $type,
            [
                self::ECHO_ANALYTICS_HITS,
                self::ECHO_ANALYTICS_AVG,
                self::ECHO_ANALYTICS_MAX,
                self::ECHO_ANALYTICS_SUM
            ]
        )) {
            throw new \Exception('You must supply a valid analytics type');
        }

        if (empty($class)) {
            throw new \Exception('You must supply a class');
        }

        if (empty($baseUrl)) {
            throw new \Exception('Could not determine echo base URL');
        }

        $eventUrl = $baseUrl
            . '/analytics/'
            .  $type
            . '?'
            . http_build_query(['class' => $class]);

        if (count($opts) > 0) {
            $eventUrl .= '&' . http_build_query($opts);
        }

        $client = $this->getHttpClient();
        $request = $client->get(
            $eventUrl,
            $this->getHeaders($noCache),
            ['connect_timeout' => 10]
        );

        $response = $request->send();
        if ($response->isSuccessful()) {
            $this->getLogger()->debug(
                "Success getting analytics from echo - $type",
                $opts
            );

            $format = isset($opts['format']) ? $opts['format'] : 'json';
            switch ($format) {
                case 'csv':
                    $result = $response->getBody(true);
                    if ($result) {
                        return $result;
                    }

                    break;
                default:
                    $json = json_decode($response->getBody(true), true);
                    if ($json) {
                        return $json;
                    }

                    $this->getLogger()->warning(
                        "Failed getting analytics from echo, json did not decode - $class",
                        [
                            'body' => $response->getBody(true),
                            'responseCode' => $response->getStatusCode(),
                            'responseBody' => $response->getBody(true),
                            'requestClass' => $class,
                            'requestType' => $type,
                            'requestOpts' => $opts,
                        ]
                    );

                    throw new \Exception(
                        'Could not get analytics from echo, json did not decode: '
                        . $response->getBody(true)
                    );
            }
        } else {
            $this->getLogger()->warning(
                'Failed getting analytics from echo - ' . $class,
                [
                    'responseCode' => $response->getStatusCode(),
                    'responseBody' => $response->getBody(true),
                    'requestClass' => $class,
                    'requestType' => $type,
                    'requestOpts' => $opts,
                ]
            );

            throw new \Exception(
                'Could not get analytics from echo, statusCode: '
                . $response->getStatusCode()
            );
        }
    }

    /**
     * Enable debug mode for this client.  If this is enabled we log things
     * like the Persona client. Only use in development, not production!
     *
     * @param boolean $debugEnabled Whether debug mode should be enabled or
     *      not (default = false)
     */
    public function setDebugEnabled($debugEnabled)
    {
        $this->debugEnabled = $debugEnabled;
    }

    /**
     * Is debugging enabled for this class? We log out things like the Persona
     * token etc. Only to be used in development!.
     *
     * @return boolean
     */
    public function isDebugEnabled()
    {
        return $this->debugEnabled;
    }

    /**
     * Allow the calling project to use its own instance of a MonoLog Logger class.
     *
     * @param Logger $logger logger
     */
    public static function setLogger(Logger $logger)
    {
        self::$logger = $logger;
    }

    /**
     * Setup the header array for any request to echo
     *
     * @param boolean $noCache whether to use cache
     * @return array
     */
    protected function getHeaders($noCache = false)
    {
        $personaToken = $this->getPersonaClient()->obtainNewToken(
            OAUTH_USER,
            OAUTH_SECRET
        );

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$personaToken['access_token']}",
        ];

        if ($noCache) {
            $headers['Cache-Control'] = 'none';
        }

        return $headers;
    }

    /**
     * Sends json encoded events data to echo
     *
     * @param string $eventsData The json encoded events data to send
     * @return boolean True if successful
     * @throws EchoHttpException If the server responded with an error
     * @throws EchoCouldNotSendException If we were unable to send data to the Echo server
     */
    protected function sendJsonEventDataToEcho($eventsData)
    {
        $baseUrl = $this->getBaseUrl();

        if (!$baseUrl) {
            // fail silently when creating events, should not stop user
            // interaction as echo events are collected on a best-endeavours basis
            $this->getLogger()->warning(
                'Echo server is not defined (missing ECHO_HOST define), '
                . ' not sending events'
            );

            return false;
        }

        $eventUrl = "$baseUrl/events";

        try {
            $client = $this->getHttpClient();
            $request = $client->post(
                $eventUrl,
                $this->getHeaders(),
                $eventsData,
                ['connect_timeout' => 2]
            );

            $response = $request->send();
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                'Failed sending events to echo',
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'batchSize' => count(json_decode($eventsData)),
                    'batchSizeBytes' => $this->getStringSizeInBytes($eventsData),
                    'events' => $eventsData,
                ]
            );

            throw new \Talis\EchoClient\CouldNotSendDataException(
                "Failed sending events to echo. {$e->getMessage()}"
            );
        }

        if ($response->isSuccessful()) {
            $this->getLogger()->debug('Success sending events to echo');
            return true;
        }

        // if the response wasn't successful then log the error
        // and raise an exception
        $this->getLogger()->warning(
            'Failed sending events to echo',
            [
                'responseCode' => $response->getStatusCode(),
                'responseBody' => $response->getBody(true),
                'batchSize' => count(json_decode($eventsData)),
                'batchSizeBytes' => $this->getStringSizeInBytes($eventsData),
            ]
        );

        throw new \Talis\EchoClient\HttpException(
            "{$response->getStatusCode()} - {$response->getBody(true)}"
        );
    }

    /**
     * Return the echo server base url
     *
     * @return boolean|string false is not available, else string
     */
    protected function getBaseUrl()
    {
        if (!defined('ECHO_HOST')) {
            $this->getLogger()->warning(
                'Echo server is not defined (missing ECHO_HOST define)'
            );

            return false;
        }

        return ECHO_HOST . '/' . self::ECHO_API_VERSION;
    }

    /**
     * To allow mocking of the Guzzle client for testing.
     *
     * @return Client
     */
    protected function getHttpClient()
    {
        return new \Guzzle\Http\Client();
    }

    /**
     * To allow mocking of the PersonaClient for testing.
     *
     * @return \Talis\Persona\Client\Tokens
     */
    protected function getPersonaClient()
    {
        if (!isset($this->personaClient)) {
            $cacheDriver = new \Doctrine\Common\Cache\PredisCache(
                $this->getCacheClient()
            );

            $this->personaClient = new \Talis\Persona\Client\Tokens([
                'persona_host' => PERSONA_HOST,
                'persona_oauth_route' => PERSONA_OAUTH_ROUTE,
                'userAgent' => 'echo-php-client',
                'cacheBackend' => $cacheDriver,
            ]);
        }

        return $this->personaClient;
    }

    /**
     * Lazy Loader, returns a predis client instance
     *
     * @return boolean|\Predis\Client false else a connected predis instance
     * @throws \Predis\Connection\ConnectionException If it cannot connect to the server specified
     */
    protected function getCacheClient()
    {
        if (!isset($this->tokenCacheClient)) {
            $this->tokenCacheClient = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => PERSONA_TOKENCACHE_HOST,
                'port' => PERSONA_TOKENCACHE_PORT,
                'database' => PERSONA_TOKENCACHE_DB,
            ]);
        }

        return $this->tokenCacheClient;
    }

    /**
     * Get the current Logger instance.
     *
     * @return Logger
     */
    protected function getLogger()
    {
        if (self::$logger == null) {
            // If an instance of the MongoLog Logger hasn't been passed
            // in then default to stderr.
            self::$logger = new Logger('echoclient');
            $streamHandler = new StreamHandler(
                '/tmp/echo-client.log',
                Logger::DEBUG
            );

            self::$logger->pushHandler($streamHandler);
        }

        return self::$logger;
    }

    /**
     * Returns the size of a given string in bytes
     *
     * @param string $input The string whose length we wish to compute
     * @return integer The length of the string in bytes
     */
    protected function getStringSizeInBytes($input)
    {
        return strlen(utf8_decode($input));
    }
}
