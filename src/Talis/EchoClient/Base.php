<?php

namespace Talis\EchoClient;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

abstract class Base
{
    private static $logger;
    private $tokenCacheClient;
    private $personaClient;

    /**
     * Get the current Logger instance.
     *
     * @return \Monolog\Logger
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
     * Allow the calling project to use its own instance of a MonoLog Logger class.
     *
     * @param \Monolog\Logger $logger logger
     */
    public static function setLogger(Logger $logger)
    {
        self::$logger = $logger;
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
     * Returns the size of a given string in bytes
     *
     * @param string $input The string whose length we wish to compute
     * @return integer The length of the string in bytes
     */
    protected function getStringSizeInBytes($input)
    {
        return strlen(utf8_decode($input));
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
     * Parse date from string
     * @param string $date date to parse
     * @return null|date
     */
    protected function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        return date('c', $date);
    }
}
