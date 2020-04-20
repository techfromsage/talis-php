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
     * @var \GuzzleHttp\Client
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
     * @return \GuzzleHttp\Client
     */
    protected function getHTTPClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = new \GuzzleHttp\Client();
        }
        return $this->httpClient;
    }

    /**
     * Create an archive generation job request
     *
     * @param Manifest $manifest Manifest
     * @param string $clientId Persona client ID
     * @param string $clientSecret Persona client secret
     * @throws \Exception|\GuzzleHttp\Exception\RequestException API request error
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
            $headers = $this->getHeaders($clientId, $clientSecret);

            $response = $this->getHTTPClient()->post($archiveLocation, [
                \GuzzleHttp\RequestOptions::HEADERS => $headers,
                \GuzzleHttp\RequestOptions::BODY => $manifestDocument,
            ]);

            if ($response->getStatusCode() == 202) {
                $archive = new \Talis\Manifesto\Archive();
                $archive->loadFromJson((string) $response->getBody());
                return $archive;
            } else {
                throw new \Talis\Manifesto\Exceptions\ArchiveException(
                    (string) $response->getBody(),
                    $response->getStatusCode()
                );
            }
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            $response = $exception->getResponse();
            $error = $this->processErrorResponseBody((string) $response->getBody());
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
     * @throws \Exception|\GuzzleHttp\Exception\RequestException API request error
     * @throws Exceptions\GenerateUrlException Unable to generate URL
     */
    public function generateUrl($jobId, $clientId, $clientSecret)
    {
        $url = $this->manifestoBaseUrl . '/1/archives/' . $jobId . '/generateUrl';
        try {
            $headers = $this->getHeaders($clientId, $clientSecret);

            $response = $this->getHTTPClient()->post($url, [
                \GuzzleHttp\RequestOptions::HEADERS => $headers,
            ]);

            if ($response->getStatusCode() == 200) {
                $body = (string) $response->getBody();
                if (!empty($body)) {
                    $body = json_decode((string) $response->getBody());
                }
                return $body->url;
            } else {
                throw new \Talis\Manifesto\Exceptions\GenerateUrlException(
                    (string) $response->getBody(),
                    $response->getStatusCode()
                );
            }
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            $response = $exception->getResponse();
            $error = $this->processErrorResponseBody((string) $response->getBody());
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
