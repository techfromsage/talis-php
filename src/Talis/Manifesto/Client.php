<?php

namespace Talis\Manifesto;

// phpcs:disable PSR1.Files.SideEffects
require_once 'common.inc.php';

class Client
{
    protected $clientId;
    protected $clientSecret;

    /**
     * @var Talis\Persona\Client\Tokens
     */
    protected $personaClient;

    /**
     * @var array
     */
    protected $personaConnectValues = [];

    /**
     * @var string
     */
    protected $manifestoBaseUrl;

    /**
     * @var \Guzzle\Http\Client
     */
    protected $httpClient;

    /**
     * @param string $manifestoBaseUrl Manifesto API base URL
     * @param array $personaConnectValues Persona client config
     */
    public function __construct($manifestoBaseUrl, array $personaConnectValues = [])
    {
        $this->manifestoBaseUrl = $manifestoBaseUrl;
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * @param array $personaConnectValues Persona client config
     */
    public function setPersonaConnectValues(array $personaConnectValues)
    {
        $this->personaConnectValues = $personaConnectValues;
    }

    /**
     * @return string
     */
    public function getManifestoBaseUrl()
    {
        return $this->manifestoBaseUrl;
    }

    /**
     * @param string $manifestoBaseUrl Manifesto API base URL
     */
    public function setManifestoBaseUrl($manifestoBaseUrl)
    {
        $this->manifestoBaseUrl = $manifestoBaseUrl;
    }

    /**
     * For mocking
     * @return \Talis\Persona\Client\Tokens
     */
    protected function getPersonaClient()
    {
        if (!isset($this->personaClient)) {
            $this->personaClient = new \Talis\Persona\Client\Tokens($this->personaConnectValues);
        }
        return $this->personaClient;
    }

    /**
     * Allows PersonaClient override, if PersonaClient has been initialized elsewhere
     * @param \Talis\Persona\Client\Tokens $personaClient Instance of persona client
     */
    public function setPersonaClient(\Talis\Persona\Client\Tokens $personaClient)
    {
        $this->personaClient = $personaClient;
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
     * Create an archive generation job request
     *
     * @param Manifest $manifest Manifest
     * @param string $clientId Persona client ID
     * @param string $clientSecret Persona client secret
     * @throws \Exception|\Guzzle\Http\Exception\ClientErrorResponseException API request error
     * @throws Exceptions\ManifestValidationException Misconfigured manifest
     * @throws Exceptions\UnauthorisedAccessException Persona token error
     * @throws Exceptions\ArchiveException Misconfigured Manifesto API URL
     * @return \Talis\Manifesto\Archive
     */
    public function requestArchive(Manifest $manifest, $clientId, $clientSecret)
    {
        $archiveLocation = $this->manifestoBaseUrl . '/1/archives';
        $manifestDocument = json_encode($manifest->generateManifest());

        try {
            $client = $this->getHTTPClient();
            $headers = $this->getHeaders($clientId, $clientSecret);

            $request = $client->post($archiveLocation, $headers, $manifestDocument);

            $response = $request->send();

            if ($response->getStatusCode() == 202) {
                $archive = new \Talis\Manifesto\Archive();
                $archive->loadFromJson($response->getBody(true));
                return $archive;
            } else {
                throw new \Talis\Manifesto\Exceptions\ArchiveException(
                    $response->getBody(true),
                    $response->getStatusCode()
                );
            }
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $exception) {
            $response = $exception->getResponse();
            $error = $this->processErrorResponseBody($response->getBody(true));
            switch ($response->getStatusCode()) {
                case 400:
                    throw new \Talis\Manifesto\Exceptions\ManifestValidationException(
                        $error['message'],
                        $error['error_code'],
                        $exception
                    );
                case 403:
                case 401:
                    throw new \Talis\Manifesto\Exceptions\UnauthorisedAccessException(
                        $error['message'],
                        $error['error_code'],
                        $exception
                    );
                    break;
                case 404:
                    throw new \Talis\Manifesto\Exceptions\ArchiveException('Misconfigured Manifesto base url', 404);
                    break;
                default:
                    throw $exception;
            }
        }
    }

    /**
     * Generate a pre signed URL via Manifesto API
     * @param string $jobId Job ID
     * @param string $clientId Persona client ID
     * @param string $clientSecret Persona client secret
     * @return string
     * @throws \Exception|\Guzzle\Http\Exception\ClientErrorResponseException API request error
     * @throws Exceptions\GenerateUrlException Unable to generate URL
     */
    public function generateUrl($jobId, $clientId, $clientSecret)
    {
        $url = $this->manifestoBaseUrl . '/1/archives/' . $jobId . '/generateUrl';
        try {
            $client = $this->getHTTPClient();
            $headers = $this->getHeaders($clientId, $clientSecret);

            $request = $client->post($url, $headers);

            $response = $request->send();

            if ($response->getStatusCode() == 200) {
                $body = $response->getBody(true);
                if (!empty($body)) {
                    $body = json_decode($response->getBody(true));
                }
                return $body->url;
            } else {
                throw new \Talis\Manifesto\Exceptions\GenerateUrlException(
                    $response->getBody(true),
                    $response->getStatusCode()
                );
            }
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $exception) {
            $response = $exception->getResponse();
            $error = $this->processErrorResponseBody($response->getBody(true));
            switch ($response->getStatusCode()) {
                case 401:
                    throw new \Talis\Manifesto\Exceptions\UnauthorisedAccessException(
                        $error['message'],
                        $error['error_code'],
                        $exception
                    );
                    break;
                case 404:
                    throw new \Talis\Manifesto\Exceptions\GenerateUrlException('Missing archive', 404);
                    break;
                default:
                    throw $exception;
            }
        }
    }

    /**
     * Setup the header array for any request to Manifesto
     * @param string $clientId Persona client ID
     * @param string $clientSecret Persona client secret
     * @return array
     */
    protected function getHeaders($clientId, $clientSecret)
    {
        $arrPersonaToken = $this->getPersonaClient()->obtainNewToken($clientId, $clientSecret);
        $personaToken = $arrPersonaToken['access_token'];
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $personaToken
        ];
        return $headers;
    }

    /**
     * Parse API error reponse.
     * @param string $responseBody The response body
     * @return array
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
