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
     * @param $babelHost
     * @param $babelPort
     * @throws ClientException
     */
    function __construct($babelHost, $babelPort=null)
    {
        if (empty($babelHost))
        {
            throw new \Talis\Babel\ClientException('babelHost must be specified');
        }

        if (!preg_match('/^http/', $babelHost))
        {
            throw new \Talis\Babel\ClientException('babelHost must also specify a scheme, either http:// or https://');
        }

        $this->babelHost = $babelHost;
        $this->babelPort = $babelPort;
    }

    /**
     * Specify an instance of MonoLog Logger for the Babel client to use.
     * @param Logger $logger
     */
    function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get a feed based off a target identifier. Return either a list of feed identifiers, or hydrate it and
     * pass back the data as well
     *
     * @param string $target Feed target identifier
     * @param string $token Persona token
     * @param bool $hydrate Gets a fully hydrated feed, i.e. actually contains the posts
     * @param array $options Valid values for the options array:-
     *   delta_token  - Filter to annotations made after the high water mark represented by delta_token
     *   limit        - limit returned results
     *   offset       - offset start of results
     * @throws \Talis\Babel\ClientException
     * @return mixed
     */
    function getTargetFeed($target, $token, $hydrate=false, array $options=array())
    {
        if (empty($target))
        {
            throw new \Talis\Babel\ClientException('Missing target');
        }
        if (empty($token))
        {
            throw new \Talis\Babel\ClientException('Missing token');
        }

        $url = '/feeds/targets/'.md5($target).'/activity/annotations'.($hydrate ? '/hydrate':'');

        $queryString = http_build_query($options);
        if (!empty($queryString))
        {
            $url .= '?'.$queryString;
        }

        return $this->performBabelGet($url, $token);
    }

    /**
     * Gets the count of new items on the $target feed since $deltaToken was issued
     * @param $target
     * @param $token
     * @param int $deltaToken
     * @return mixed
     * @throws \Talis\Babel\ClientException
     * @throws InvalidPersonaTokenException
     * @throws NotFoundException
     */
    function getTargetFeedCount($target, $token, $deltaToken=0)
    {
        if (empty($target))
        {
            throw new \Talis\Babel\ClientException('Missing target');
        }
        if (empty($token))
        {
            throw new \Talis\Babel\ClientException('Missing token');
        }

        $url = '/feeds/targets/'.md5($target)."/activity/annotations?delta_token=$deltaToken";

        $headers = $this->performBabelHead($url,$token);
        $newItemsHeader = $headers->get("X-Feed-New-Items")->toArray();
        if (count($newItemsHeader)!==1)
        {
            throw new \Talis\Babel\ClientException('Unexpected amount of X-Feed-New-Items headers returned');
        }
        return intval($newItemsHeader[0]);
    }

    /***
     * Queries multiple feeds. Given an array of feed ids it will return a merged hydrated feed.
     *
     * NB: "feedIds" are fairly cryptic redis keys it seems, according to the limited docs in babel-server.
     *     An example would be 'targets:<md5 hash of targetUri>:activity'.
     *     There maybe other examples of feedIds but I've not found them yet...
     *
     * @param array $feedIds An array of Feed Identifiers (see note above)
     * @param string $token Persona token
     * @throws \Talis\Babel\ClientException
     * @return array
     */
    function getFeeds(array $feedIds, $token)
    {
        $strFeedIds = implode(',', $feedIds);
        $url = '/feeds/annotations/hydrate?feed_ids='.urlencode($strFeedIds);
        return $this->performBabelGet($url, $token);
    }

    /**
     * Get annotations feed based off options passed in
     *
     * TODO See if all these are supported in the node client...
     *
     * @param $token
     * @param array $options Valid values for the options array:-
     *   hasTarget    - restrict to a specific target
     *   annotatedBy  - restrict to annotations made by a specific user
     *   hasBody.uri  - restrict to a specific body URI
     *   hasBody.type - restrict to annotations by the type of the body
     *   q            - perform a text search on hasBody.char field. If used, annotatedBy and hasTarget will be ignored
     *   limit        - limit returned results
     *   offset       - offset start of results
     * @return mixed
     * @throws \Talis\Babel\ClientException
     * @throws InvalidPersonaTokenException
     * @throws NotFoundException
     */
    function getAnnotations($token, array $options=array())
    {
        $url = '/annotations';

        $queryString = http_build_query($options);
        if (!empty($queryString))
        {
            $url .= '?'.$queryString;
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
     * @param bool $bCreateSynchronously If set, will not return until the feed for this annotation has also been created in Redis.
     * @throws InvalidPersonaTokenException
     * @throws \Talis\Babel\ClientException
     * @return array
     */
    function createAnnotation($token, array $arrData, $bCreateSynchronously=false)
    {
        if (empty($token))
        {
            throw new InvalidPersonaTokenException('No persona token specified');
        }

        if (!array_key_exists('annotatedBy', $arrData))
        {
            throw new \Talis\Babel\ClientException("Missing annotatedBy in data array");
        }

        if (!array_key_exists('hasTarget', $arrData))
        {
            throw new \Talis\Babel\ClientException("Missing hasTarget in data array");
        }
        if (!is_array($arrData['hasTarget']))
        {
            throw new \Talis\Babel\ClientException('hasTarget must be an array containing uri');
        }
        $hasTarget = $arrData['hasTarget'];
        if (!array_key_exists('uri', $hasTarget))
        {
            // perhaps it is multi-target
            foreach($hasTarget as $h)
            {
                if (!array_key_exists('uri', $h))
                {
                    throw new \Talis\Babel\ClientException("Missing hasTarget.uri in data array");
                }
            }
        }

        if (!array_key_exists('hasBody', $arrData))
        {
            throw new \Talis\Babel\ClientException('Missing hasBody in data array');
        }
        if (!is_array($arrData['hasBody']))
        {
            throw new \Talis\Babel\ClientException('hasBody must be an array containing format and type');
        }
        $hasBody = $arrData['hasBody'];
        if (!array_key_exists('format', $hasBody))
        {
            throw new \Talis\Babel\ClientException("Missing hasBody.format in data array");
        }
        if (!array_key_exists('type', $hasBody))
        {
            throw new \Talis\Babel\ClientException("Missing hasBody.type in data array");
        }

        if ($bCreateSynchronously)
        {
            // Specific header that Babel server accepts to not return until the feed has also been created for the annotation.
            $requestOptions = array('headers'=>array('X-Ingest-Synchronously'=>'true'));
        }
        else
        {
            $requestOptions = null;
        }

        $url = '/annotations';

        return $this->performBabelPost($url, $token, $arrData, $requestOptions);
    }


    /**
     * Perform a GET request against Babel and return the response or handle error.
     *
     * @param $url
     * @param $token
     * @return mixed
     * @throws InvalidPersonaTokenException
     * @throws NotFoundException
     * @throws \Talis\Babel\ClientException
     */
    protected function performBabelGet($url, $token)
    {
        $headers = array(
            'Accept'=>'application/json',
            'Authorization'=>'Bearer '.$token
        );

        $this->getLogger()->debug('Babel GET: '.$url, $headers);

        $httpClient = $this->getHttpClient();

        $request = $httpClient->get($url, $headers, array('exceptions'=>false));

        $response = $request->send();

        if ($response->isSuccessful())
        {
            $responseBody = $response->getBody(true);

            $arrResponse = json_decode($responseBody, true);
            if ($arrResponse == null)
            {
                $this->getLogger()->error('Failed to decode JSON response: '.$responseBody);
                throw new \Talis\Babel\ClientException('Failed to decode JSON response: '.$responseBody);
            }

            return $arrResponse;
        }
        else
        {
            /*
             * For error scenarios we want to distinguish Persona problems and instances where no data is found.
             * Anything else raises a generic \Talis\Babel\ClientException.
             */
            $statusCode = $response->getStatusCode();
            switch ($statusCode)
            {
                case 401:
                    $this->getLogger()->error('Persona token invalid/expired for request: GET '.$url);
                    throw new InvalidPersonaTokenException('Persona token is either invalid or has expired');
                case 404:
                    $this->getLogger()->error('Nothing found for request: GET '.$url);
                    throw new NotFoundException('Nothing found for request:'.$url);
                default:
                    $errorMessage = 'Unknown error';
                    $responseBody = $response->getBody(true);
                    if ($responseBody)
                    {
                        $arrResponse = json_decode($responseBody, true);
                        if (is_array($arrResponse) && array_key_exists('message', $arrResponse))
                        {
                            $errorMessage = $arrResponse['message'];
                        }
                    }
                    $this->getLogger()->error('Babel GET failed for request: '.$url, array('statusCode'=>$statusCode, 'message'=>$response->getMessage(), 'body'=>$responseBody));
                    throw new \Talis\Babel\ClientException("Error ${statusCode} for GET ${url}: ${errorMessage}", $statusCode);
            }
        }
    }

    /**
     * Perform a HEAD request against Babel and return the response headers or handle error.
     *
     * @param $url
     * @param $token
     * @return HeaderCollection
     * @throws InvalidPersonaTokenException
     * @throws NotFoundException
     * @throws \Talis\Babel\ClientException
     */
    protected function performBabelHead($url, $token)
    {
        $headers = array(
            'Accept'=>'application/json',
            'Authorization'=>'Bearer '.$token
        );

        $this->getLogger()->debug('Babel HEAD: '.$url, $headers);

        $httpClient = $this->getHttpClient();

        $request = $httpClient->head($url, $headers, array('exceptions'=>false));

        $response = $request->send();

        if ($response->isSuccessful())
        {
            return $response->getHeaders();
        }
        else
        {
            /*
             * For error scenarios we want to distinguish Persona problems and instances where no data is found.
             * Anything else raises a generic \Talis\Babel\ClientException.
             */
            $statusCode = $response->getStatusCode();
            switch ($statusCode)
            {
                case 401:
                    $this->getLogger()->error('Persona token invalid/expired for request: HEAD '.$url);
                    throw new InvalidPersonaTokenException('Persona token is either invalid or has expired');
                case 404:
                    $this->getLogger()->error('Nothing found for request: HEAD '.$url);
                    throw new NotFoundException('Nothing found for request:'.$url);
                default:
                    $this->getLogger()->error('Babel HEAD failed for request: '.$url, array('statusCode'=>$statusCode, 'message'=>$response->getMessage(), 'body'=>$response->getBody(true)));
                    throw new \Talis\Babel\ClientException("Error ${statusCode} for HEAD ${url}", $statusCode);
            }
        }
    }

    /**
     * Perform a GET request against Babel and return the response or handle error.
     *
     * @param $url
     * @param $token
     * @param array $arrData
     * @param array|null $requestOptions Additional request options to use.
     * @return mixed
     * @throws InvalidPersonaTokenException
     * @throws \Talis\Babel\ClientException
     */
    protected function performBabelPost($url, $token, array $arrData, $requestOptions=null)
    {
        if (empty($requestOptions))
        {
            $requestOptions = array();
        }
        elseif (!is_array($requestOptions))
        {
            throw new \Talis\Babel\ClientException('requestOptions must be an array');
        }

        $headers = array(
            'Accept'=>'application/json',
            'Authorization'=>'Bearer '.$token
        );

        if (isset($requestOptions['headers']))
        {
            $headers = array_merge($headers, $requestOptions['headers']);
        }

        $this->getLogger()->debug('Babel POST: '.$url, $arrData);

        $httpClient = $this->getHttpClient();

        $request = $httpClient->post($url, $headers, $arrData, array('exceptions'=>false));

        $response = $request->send();

        if ($response->isSuccessful())
        {
            $responseBody = $response->getBody(true);

            $arrResponse = json_decode($responseBody, true);
            if ($arrResponse == null)
            {
                $this->getLogger()->error('Failed to decode JSON response: '.$responseBody);
                throw new \Talis\Babel\ClientException('Failed to decode JSON response: '.$responseBody);
            }

            return $arrResponse;
        }
        else
        {
            /*
             * Is is a Persona token problem?
             */
            $statusCode = $response->getStatusCode();
            if ($statusCode == 401)
            {
                $this->getLogger()->error('Persona token invalid/expired for request: POST '.$url);
                throw new InvalidPersonaTokenException('Persona token is either invalid or has expired');
            }
            else
            {
                $errorMessage = 'Unknown error';
                $responseBody = $response->getBody(true);
                if ($responseBody)
                {
                    $arrResponse = json_decode($responseBody, true);
                    if (is_array($arrResponse) && array_key_exists('message', $arrResponse))
                    {
                        $errorMessage = $arrResponse['message'];
                    }
                }
                $this->getLogger()->error('Babel POST failed for request: '.$url, array('statusCode'=>$statusCode, 'message'=>$response->getMessage(), 'body'=>$responseBody));
                throw new \Talis\Babel\ClientException("Error ${statusCode} for POST ${url}: ${errorMessage}" , $statusCode);
            }
        }
    }

    /**
     * Get an instance to the passed in logger or lazily create one for Babel logging.
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->logger == null)
        {
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
        if ($this->httpClient == null)
        {
            $port = $this->getBabelPort();
            if ($port == null)
            {
                $baseUrl = $this->getBabelHost();
            }
            else
            {
                $baseUrl = $this->getBabelHost().':'.$this->getBabelPort();
            }

            $this->httpClient = new \Guzzle\Http\Client($baseUrl);
        }

        return $this->httpClient;
    }
}
