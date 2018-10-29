<?php

namespace Talis\Critic;

class Client
{
    protected $clientId;
    protected $clientSecret;

    /**
     * @var \Talis\Persona\Client\Tokens
     */
    protected $tokenClient;

    /**
     * @var array
     */
    protected $personaConnectValues = [];

    /**
     * @var string
     */
    protected $criticBaseUrl;

    /**
     * @var \Guzzle\Http\Client
     */
    protected $httpClient;

    /**
     * Constructor
     * @param string $criticBaseUrl base url to critic
     * @param array $personaConnectValues see \Talis\Persona\Client\Tokens
     */
    public function __construct($criticBaseUrl, array $personaConnectValues = [])
    {
        $this->criticBaseUrl = $criticBaseUrl;
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * @param array $personaConnectValues see \Talis\Persona\Client\Tokens
     */
    public function setPersonaConnectValues(array $personaConnectValues)
    {
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * For mocking
     * @return \Talis\Persona\Client\Tokens
     */
    protected function getTokenClient()
    {
        if (!isset($this->tokenClient)) {
            $this->tokenClient = new \Talis\Persona\Client\Tokens(
                $this->personaConnectValues
            );
        }

        return $this->tokenClient;
    }

    /**
     * Allows PersonaClient override, if PersonaClient has been initialized elsewhere
     * @param \Talis\Persona\Client\Tokens $personaClient persona tokens client
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
        if (!$this->httpClient) {
            $this->httpClient = new \Guzzle\Http\Client();
        }

        return $this->httpClient;
    }

    /**
     * Create a review within critic.
     * @param array $postFields HTTP post fields to be sent to critic
     * @param string $clientId client id
     * @param string $clientSecret client secret
     * @param array $headerParams a set of optional parameters you can pass into method to obtain a persona token <pre>
     *          scope: (string) to obtain a new scoped token
     *          useCookies: (boolean) to enable or disable checking cookies
     *              for pre-existing access_token (and setting a new cookie
     *              with the resultant token)
     *          use_cache: (boolean) use cached called (defaults to true) </pre>
     * @throws \Exception|\Guzzle\Http\Exception\ClientErrorResponseException Http communication error
     * @throws Exceptions\UnauthorisedAccessException Authorisation error
     */
    public function createReview(array $postFields, $clientId, $clientSecret, array $headerParams = [])
    {

        try {
            $client = $this->getHTTPClient();
            $headers = $this->getHeaders($clientId, $clientSecret, $headerParams);

            $request = $client->post($this->criticBaseUrl, $headers, $postFields);
            $response = $request->send();

            if ($response->getStatusCode() == 201) {
                $body = json_decode($response->getBody(true));
                return $body->id;
            }

            throw new \Talis\Critic\Exceptions\ReviewException();
        /** @var \Guzzle\Http\Exception\ClientErrorResponseException $e */
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $response = $e->getResponse();
            $error = $this->processErrorResponseBody($response->getBody(true));

            switch ($response->getStatusCode()) {
                case 403:
                case 401:
                    throw new \Talis\Critic\Exceptions\UnauthorisedAccessException(
                        $error['message'],
                        $error['error_code'],
                        $e
                    );
                    break;
                default:
                    throw $e;
            }
        }
    }

    /**
     * Setup the header array for any request to Critic
     * @param string $clientId persona client id
     * @param string $clientSecret persona client secret
     * @param array $params see \Talis\Persona\Client\Tokens
     * @return array http headers
     */
    protected function getHeaders($clientId, $clientSecret, array $params = [])
    {
        $token = $this->getTokenClient()->obtainNewToken(
            $clientId,
            $clientSecret,
            $params
        );

        return [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$token['access_token']}",
        ];
    }

    /**
     * Convert a http response body into a associative array which includes keys
     * error_code and message.
     * @param string $responseBody http json blob
     * @return array array that describes the error
     */
    protected function processErrorResponseBody($responseBody)
    {
        $error = ['error_code' => null, 'message' => null];
        $response = json_decode($responseBody, true);

        if (isset($response['error_code'])) {
            $error['error_code'] = $response['error_code'];
        }

        if (isset($response['message'])) {
            $error['message'] = $response['message'];
        }

        return $error;
    }
}
