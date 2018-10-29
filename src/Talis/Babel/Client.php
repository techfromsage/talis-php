<?php
namespace Talis\Babel;

use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Header\HeaderCollection;
use \Monolog\Handler\StreamHandler;
use \Monolog\Logger;

/**
 * Babel client.
 *
 * This is a port of the babel-node-client, please try to keep the two libraries in sync.
 *
 * @package Talis\Babel
 */
class Client
{
    /**
     * @var string
     */
    private $babelHost;

    /**
     * @var string
     */
    private $babelPort;

    /**
     * @var \Guzzle\Http\Client
     */
    private $httpClient = null;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger = null;

    /**
     * Babel client must be created with a host/port to connect to Babel.
     *
     * @param string $url babel http url
     * @param integer|string $port babel http port
     * @throws ClientException Invalid babel host parameter
     */
    public function __construct($host, $port = null)
    {
        if (empty($host)) {
            throw new \Talis\Babel\ClientException('host must be specified');
        }

        if (!preg_match('/^http/', $host)) {
            throw new \Talis\Babel\ClientException(
                'host must also specify a scheme, either http:// or https://'
            );
        }

        $this->babelHost = $host;
        $this->babelPort = $port;
    }

    /**
     * Specify an instance of MonoLog Logger for the Babel client to use.
     * @param \Psr\Log\LoggerInterface $logger logger to use
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get a feed based off a target identifier. Return either a list of feed
     * identifiers, or hydrate it and pass back the data as well.
     *
     * @param string $target Feed target identifier
     * @param string $token Persona token
     * @param boolean $hydrate Gets fully hydrated feed, i.e. contains the posts
     * @param array $options Valid values for the options array:-
     *   delta_token  - Filter to annotations made after the high water mark
     *      represented by delta_token
     *   limit        - limit returned results
     *   offset       - offset start of results
     * @throws \Talis\Babel\ClientException Error communicating with Babel
     * @return mixed
     */
    public function getTargetFeed($target, $token, $hydrate = false, array $options = [])
    {
        if (empty($target)) {
            throw new \Talis\Babel\ClientException('Missing target');
        }

        if (empty($token)) {
            throw new \Talis\Babel\ClientException('Missing token');
        }

        $hash = md5($target);
        $url = "/feeds/targets/$hash/activity/annotations";

        if ($hydrate) {
            $url .= '/hydrate';
        }

        $queryString = http_build_query($options);
        if (!empty($queryString)) {
            $url .= "?$queryString";
        }

        return $this->performBabelGet($url, $token);
    }

    /**
     * Gets the count of new items on the $target feed since $deltaToken was issued
     * @param string $target target feed
     * @param string $token persona oauth token
     * @param integer $deltaToken delta token for feed
     * @return mixed
     * @throws \Talis\Babel\ClientException Issue communicating with Babel
     * @throws InvalidPersonaTokenException Invalid Persona token
     * @throws NotFoundException Could not find feed
     */
    public function getTargetFeedCount($target, $token, $deltaToken = 0)
    {
        if (empty($target)) {
            throw new \Talis\Babel\ClientException('Missing target');
        }

        if (empty($token)) {
            throw new \Talis\Babel\ClientException('Missing token');
        }

        $hash = md5($target);
        $queryParams = http_build_query(['delta_token' => $deltaToken]);
        $url = "/feeds/targets/$hash/activity/annotations?$queryParams";

        $headers = $this->performBabelHead($url, $token);
        $newItemsHeader = $headers->get('X-Feed-New-Items')->to[];

        if (count($newItemsHeader) !== 1) {
            throw new \Talis\Babel\ClientException(
                'Unexpected amount of X-Feed-New-Items headers returned'
            );
        }

        return intval($newItemsHeader[0]);
    }

    /***
     * Queries multiple feeds. Given an array of feed ids it will return a
     * merged hydrated feed.
     *
     * NB: "feedIds" are fairly cryptic redis keys it seems, according to
     * the limited docs in babel-server.
     *
     * An example would be 'targets:<md5 hash of targetUri>:activity'.
     * There maybe other examples of feedIds but I've not found them yet...
     *
     * @param array $feedIds An array of Feed Identifiers (see note above)
     * @param string $token Persona token
     * @throws \Talis\Babel\ClientException Babel communication error
     * @return array response from babel
     */
    public function getFeeds(array $feedIds, $token)
    {
        $queryParams = http_build_query([
            'feed_ids' => implode(',', $feedIds)
        ]);

        $url = "/feeds/annotations/hydrate?$queryParams";
        return $this->performBabelGet($url, $token);
    }

    /**
     * Get annotations feed based off options passed in
     *
     * TODO See if all these are supported in the node client...
     *
     * @param string $token Persona oauth token
     * @param array $options Valid values for the options array:-
     *   hasTarget    - restrict to a specific target
     *   annotatedBy  - restrict to annotations made by a specific user
     *   hasBody.uri  - restrict to a specific body URI
     *   hasBody.type - restrict to annotations by the type of the body
     *   q            - perform a text search on hasBody.char field. If used, annotatedBy and hasTarget will be ignored
     *   limit        - limit returned results
     *   offset       - offset start of results
     * @return mixed
     * @throws \Talis\Babel\ClientException Babel communication error
     * @throws InvalidPersonaTokenException Invalid token
     * @throws NotFoundException Cannot find feed
     */
    public function getAnnotations($token, array $options = [])
    {
        $url = '/annotations';
        $queryParams = http_build_query($options);

        if (!empty($queryParams)) {
            $url .= "?$queryParams";
        }

        return $this->performBabelGet($url, $token);
    }

    /**
     * Create an annotation.
     *
     * TODO See if all these are supported in the node client...
     * Valid values for the data array:-
     *   data.hasBody.format
     *   data.hasBody.type
     *   data.hasBody.chars
     *   data.hasBody.details
     *   data.hasBody.uri
     *   data.hasBody.asReferencedBy
     *   data.hasTarget
     *   data.hasTarget.uri
     *   data.hasTarget.fragment
     *   data.hasTarget.asReferencedBy
     *   data.annotatedBy
     *   data.motiviatedBy
     *   data.annotatedAt
     *
     * @param string $token A valid Persona token.
     * @param array $arrData The data from which to create the annotation
     * @param boolean $bCreateSynchronously If set, will not return until
     *      the feed for this annotation has also been created in Redis.
     * @throws InvalidPersonaTokenException Invalid Persona token.
     * @throws \Talis\Babel\ClientException Babel communication error.
     * @return array
     */
    public function createAnnotation($token, array $arrData, $bCreateSynchronously = false)
    {
        if (empty($token)) {
            throw new InvalidPersonaTokenException(
                'No persona token specified'
            );
        }

        $this->checKeysExist(['annotatedBy', 'hasTarget', 'hasBody'], $arrData);

        $hasTarget = $arrData['hasTarget'];
        $hasBody = $arrData['hasBody'];

        if (!is_array($arrData['hasTarget'])) {
            throw new \Talis\Babel\ClientException(
                'hasTarget must be an array containing uri'
            );
        }

        // TODO: doesn't check for empty array
        if (!array_key_exists('uri', $hasTarget)) {
            // perhaps it is multi-target
            foreach ($hasTarget as $h) {
                $this->checkKeysExist(['uri'], $h);
            }
        }

        $this->checkKeysExist(['format', 'type'], $hasBody);

        $requestOptions = null;
        if ($bCreateSynchronously) {
            // Specific header that Babel server accepts to not return until the
            // feed has also been created for the annotation.
            $requestOptions = ['headers' => ['X-Ingest-Synchronously' => 'true']];
        }

        $url = '/annotations';
        return $this->performBabelPost($url, $token, $arrData, $requestOptions);
    }

    /**
     * Perform a GET request against Babel and return the response or handle error.
     *
     * @param string $url Babel url
     * @param string $token Persona oauth token
     * @return mixed response from babel
     * @throws InvalidPersonaTokenException Invalid persona token
     * @throws NotFoundException Babel feed not found
     * @throws \Talis\Babel\ClientException Cannot communicate with Babel
     */
    protected function performBabelGet($url, $token)
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => "Bearer $token",
        ];

        $this->getLogger()->debug("Babel GET: $url", $headers);
        $httpClient = $this->getHttpClient();

        $request = $httpClient->get($url, $headers, ['exceptions' => false]);
        $response = $request->send();

        if ($response->isSuccessful()) {
            $responseBody = $response->getBody(true);
            $arrResponse = json_decode($responseBody, true);

            if ($arrResponse == null) {
                $msg = "Failed to decode JSON response: $responseBody";
                $this->getLogger()->error($msg);
                throw new \Talis\Babel\ClientException($msg);
            }

            return $arrResponse;
        }

        /*
         * For error scenarios we want to distinguish Persona problems and
         * instances where no data is found.  Anything else raises a generic
         * \Talis\Babel\ClientException.
         */
        $this->handleBabelError($url, $response);
    }

    /**
     * Perform a HEAD request against Babel and return the response headers
     * or handle error.
     *
     * @param string $url babel url
     * @param string $token persona oauth token
     * @return HeaderCollection
     * @throws InvalidPersonaTokenException Invalid Persona oauth token
     * @throws NotFoundException Babel feed not found
     * @throws \Talis\Babel\ClientException Could not communicate with Babel
     */
    protected function performBabelHead($url, $token)
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => "Bearer $token",
        ];

        $this->getLogger()->debug('Babel HEAD: '.$url, $headers);

        $httpClient = $this->getHttpClient();
        $request = $httpClient->head($url, $headers, ['exceptions' => false]);
        $response = $request->send();

        if ($response->isSuccessful()) {
            return $response->getHeaders();
        }

        /*
         * For error scenarios we want to distinguish Persona problems and
         * instances where no data is found.  Anything else raises a generic
         * \Talis\Babel\ClientException.
         */
        $this->handleBabelError($url, $response);
    }

    /**
     * Perform a GET request against Babel and return the response or handle error.
     *
     * @param string $url babel http url
     * @param string $token persona oauth token
     * @param array $arrData http post parameters
     * @param array|null $requestOptions Additional request options to use.
     * @return mixed
     * @throws InvalidPersonaTokenException Invalid Persona token
     * @throws \Talis\Babel\ClientException Babel communication errors
     */
    protected function performBabelPost($url, $token, array $arrData, array $requestOptions = null)
    {
        if (empty($requestOptions)) {
            $requestOptions = [];
        }

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => "Bearer $token",
        ];

        if (isset($requestOptions['headers'])) {
            $headers = array_merge($headers, $requestOptions['headers']);
        }

        $this->getLogger()->debug("Babel POST: $url", $arrData);

        $httpClient = $this->getHttpClient();
        $request = $httpClient->post($url, $headers, $arrData, ['exceptions' => false]);
        $response = $request->send();

        if ($response->isSuccessful()) {
            $responseBody = $response->getBody(true);
            $arrResponse = json_decode($responseBody, true);

            if ($arrResponse == null) {
                $msg = "Failed to decode JSON response: $responseBody";
                $this->getLogger()->error($msg);
                throw new \Talis\Babel\ClientException($msg);
            }

            return $arrResponse;
        }

        // Is is a Persona token problem?
        $statusCode = $response->getStatusCode();
        if ($statusCode == 401) {
            $this->getLogger()->error(
                "Persona token invalid/expired for request: POST $url"
            );

            throw new InvalidPersonaTokenException(
                'Persona token is either invalid or has expired'
            );
        }

        $errorMessage = 'Unknown error';
        $responseBody = $response->getBody(true);

        if ($responseBody) {
            $arrResponse = json_decode($responseBody, true);
            if (is_array($arrResponse) && array_key_exists('message', $arrResponse)) {
                $errorMessage = $arrResponse['message'];
            }
        }

        $this->getLogger()->error(
            "Babel POST failed for request: $url",
            [
                'statusCode' => $statusCode,
                'message' => $response->getMessage(),
                'body' => $responseBody,
            ]
        );

        throw new \Talis\Babel\ClientException(
            "Error {$statusCode} for POST {$url}: {$errorMessage}",
            $statusCode
        );
    }

    /**
     * Get an instance to the passed in logger or lazily create one for Babel logging.
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->logger == null) {
            $this->logger = new Logger('BabelClient');
            $this->logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
        }

        return $this->logger;
    }

    /**
     * Get the Babel host - can be mocked in tests.
     * @return string
     */
    protected function getBabelHost()
    {
        return $this->babelHost;
    }

    /**
     * Get the Babel port - can be mocked in tests.
     * @return string
     */
    protected function getBabelPort()
    {
        return $this->babelPort;
    }

    /**
     * Get an instance of the Guzzle HTTP client.
     *
     * @return \Guzzle\Http\Client
     */
    protected function getHttpClient()
    {
        if ($this->httpClient == null) {
            $port = $this->getBabelPort();
            $baseUrl = $this->getBabelHost();

            if ($port != null) {
                $baseUrl .= ":$port";
            }

            $this->httpClient = new \Guzzle\Http\Client($baseUrl);
        }

        return $this->httpClient;
    }

    /**
     * Check that all checks exist within array
     * @param array keys keys to check for
     * @param array array which holds expected keys
     * @throws \Talis\Babel\ClientException Key missing
     */
    protected function checKeysExist(array $keys, array $array)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array) === false) {
                throw new \Talis\Babel\ClientException("Missing $key in data array");
            }
        }
    }

    /**
     * Handle a babel error response
     * @param string $url babel url which was called
     * @param mixed $response http response
     */
    protected function handleBabelError($url, $response)
    {
        $statusCode = $response->getStatusCode();

        switch ($statusCode) {
            case 401:
                $this->getLogger()->error("Persona token invalid/expired for request: $url");
                throw new InvalidPersonaTokenException('Persona token is either invalid or has expired');
            case 404:
                $this->getLogger()->error("Nothing found for request: $url");
                throw new NotFoundException("Nothing found for request: $url");
            default:
                $errorMessage = 'Unknown error';
                $responseBody = $response->getBody(true);

                if ($responseBody) {
                    $arrResponse = json_decode($responseBody, true);

                    if (is_array($arrResponse) && array_key_exists('message', $arrResponse)) {
                        $errorMessage = $arrResponse['message'];
                    }
                }

                $this->getLogger()->error(
                    "Babel failed for request: $url",
                    [
                        'statusCode' => $statusCode,
                        'message' => $response->getMessage(),
                        'body' => $responseBody,
                    ]
                );

                throw new \Talis\Babel\ClientException(
                    "Error {$statusCode} for {$url}: {$errorMessage}",
                    $statusCode
                );
        }
    }
}
