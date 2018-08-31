<?php

namespace Talis\Critic;

class Client {

    protected $clientId;
    protected $clientSecret;

    /**
     * @var \Talis\Persona\Client\Tokens
     */
    protected $tokenClient;

    /**
     * @var array
     */
    protected $personaConnectValues = array();

    /**
     * @var string
     */
    protected $criticBaseUrl;

    /**
     * @var \Guzzle\Http\Client
     */
    protected $httpClient;

    /**
     * @param string $criticBaseUrl
     * @param array $personaConnectValues
     */
    public function __construct($criticBaseUrl, $personaConnectValues = array())
    {
        $this->criticBaseUrl = $criticBaseUrl;
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * @param array $personaConnectValues
     */
    public function setPersonaConnectValues($personaConnectValues)
    {
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * For mocking
     * @return \Talis\Persona\Client\Tokens
     */
    protected function getTokenClient()
    {
        if(!isset($this->tokenClient))
        {
            $this->tokenClient = new \Talis\Persona\Client\Tokens($this->personaConnectValues);
        }

        return $this->tokenClient;
    }

    /**
     * Allows PersonaClient override, if PersonaClient has been initialized elsewhere
     * @param \Talis\Persona\Client\Tokens $personaClient
     */
    public function setTokenClient(\Talis\Persona\Client\Tokens $personaClient)
    {
        $this->tokenClient = $personaClient;
    }

    /**
     * For mocking
     * @return \Guzzle\Http\Client
     */
    protected function getHTTPClient()
    {
        if(!$this->httpClient)
        {
            $this->httpClient = new \Guzzle\Http\Client();
        }
        return $this->httpClient;
    }

    /**
     *
     * @param array $postFields
     * @param string $clientId
     * @param string $clientSecret
     * @param array $headerParams a set of optional parameters you can pass into method to obtain a persona token <pre>
     *          scope: (string) to obtain a new scoped token
     *          useCookies: (boolean) to enable or disable checking cookies for pre-existing access_token (and setting a new cookie with the resultant token)
     *          use_cache: (boolean) use cached called (defaults to true) </pre>
     * @throws \Exception|\Guzzle\Http\Exception\ClientErrorResponseException
     * @throws Exceptions\UnauthorisedAccessException
     */
    public function createReview($postFields, $clientId, $clientSecret, $headerParams = array())
    {

        try
        {
            $client = $this->getHTTPClient();
            $headers = $this->getHeaders($clientId, $clientSecret, $headerParams);

            $request = $client->post($this->criticBaseUrl, $headers, $postFields);

            $response = $request->send();

            if($response->getStatusCode() == 201)
            {
                $body = json_decode($response->getBody(true));
                return $body->id;
            }
            else
            {
                throw new \Talis\Critic\Exceptions\ReviewException();
            }
        }
        /** @var \Guzzle\Http\Exception\ClientErrorResponseException $e */
        catch(\Guzzle\Http\Exception\ClientErrorResponseException $e)
        {
            $response = $e->getResponse();
            $error = $this->processErrorResponseBody($response->getBody(true));
            switch($response->getStatusCode())
            {
                case 403:
                case 401:
                    throw new \Talis\Critic\Exceptions\UnauthorisedAccessException($error['message'], $error['error_code'], $e);
                    break;
                default:
                    throw $e;
            }
        }

    }

    /**
     * Setup the header array for any request to Critic
     * @param string $clientId
     * @param string $clientSecret
     * @param array $params
     * @return array
     */
    protected function getHeaders($clientId, $clientSecret, $params = array())
    {
        $arrPersonaToken = $this->getTokenClient()->obtainNewToken($clientId, $clientSecret, $params);
        $personaToken = $arrPersonaToken['access_token'];
        $headers = array(
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$personaToken
        );
        return $headers;
    }

    /**
     * @param $responseBody
     * @return array
     */
    protected function processErrorResponseBody($responseBody)
    {
        $error = array('error_code'=>null, 'message'=>null);
        $response = json_decode($responseBody, true);

        if(isset($response['error_code']))
        {
            $error['error_code'] = $response['error_code'];
        }

        if(isset($response['message']))
        {
            $error['message'] = $response['message'];
        }

        return $error;
    }
}
