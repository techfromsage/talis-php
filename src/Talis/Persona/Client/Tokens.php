<?php

namespace Talis\Persona\Client;

use \Firebase\JWT\JWT;

use \Talis\Persona\Client\ScopesNotDefinedException;
use \Talis\Persona\Client\EmptyResponseException;
use \Talis\Persona\Client\InvalidPublicKeyException;
use \Talis\Persona\Client\InvalidSignatureException;
use \Talis\Persona\Client\InvalidTokenException;
use \Talis\Persona\Client\TokenValidationException;
use \Talis\Persona\Client\UnauthorisedException;
use \Talis\Persona\Client\UnknownException;
use \Talis\Persona\Client\CertificateCache;
use \Talis\Persona\Client\TokenCache;

class Tokens extends Base
{
    use TokenCache;
    use CertificateCache;

    /**
     * Validates the supplied token using JWT or a remote Persona server.
     * An optional scope can be supplied to validate against. If a token
     * is not provided within the parameter one will be extracted from
     * either $_SERVER, $_GET or $_POST. If scope parameter is an array and at
     * least one of the scopes can be validated, the result is a success.
     *
     * The order of validation is as follows: JWT, local Redis cache, then remote Persona.
     *
     * @param array $params a set of optional parameters you can pass to this method <pre>
     *      access_token: (string) a token to validate explicitly, if you do
     *          not specify one the method tries to find one
     *      scope: (string|array) specify this if you wish to validate a scoped token
     * @return integer ValidationResults enum
     * @throws \Exception If you do not supply a token AND it cannot extract
     *      one from $_SERVER, $_GET, $_POST
     */
    public function validateToken(array $params = [])
    {
        if (isset($params['access_token']) && !empty($params['access_token'])) {
            $token = $params['access_token'];
        } else {
            $token = $this->getTokenFromRequest();
        }

        $scope = isset($params['scope']) ? $params['scope'] : null;
        $scope = is_null($scope) || is_array($scope) ? $scope : [$scope];
        $scope = is_null($scope) ? [] : $scope;

        try {
            return $this->validateTokenUsingJWT($token, $scope);
        } catch (\Exception $e) {
            if ($e instanceof ScopesNotDefinedException
                || $e instanceof CommunicationException
            ) {
                return $this->validateTokenUsingPersona($token, $scope);
            }

            throw $e;
        }
    }

    /**
     * Validate the given token by using JWT. If the $scopes attribute is
     * provided and at least one of the scopes can be validated, the result is a
     * success.
     *
     * @param string $token a token to validate explicitly, if you do not
     *      specify one the method tries to find one
     * @throws ScopesNotDefinedException If the JWT token doesn't include the user's scopes
     * @throws CommunicationException Cannot communicate with Persona
     * @throws \Exception If not able to communicate with Persona to retrieve the public certificate
     */
    protected function validateTokenUsingJWT($token, array $scopes = null)
    {
        $publicCert = $this->retrieveJWTCertificate();

        if (empty($publicCert)) {
            throw new CommunicationException('cannot retrieve certificate');
        }

        try {
            $decodedToken = $this->decodeToken($token, $publicCert);
        } catch (TokenValidationException $e) {
            return $e->getCode();
        }

        if (empty($scopes)) {
            return ValidationResults::SUCCESS;
        } elseif (isset($decodedToken['scopeCount'])) {
            // user scopes not included within
            // the JWT as there are too many
            throw new ScopesNotDefinedException('too many scopes');
        }

        $isSu = in_array('su', $decodedToken['scopes'], true);
        $hasScope = count(array_intersect($scopes, $decodedToken['scopes'])) > 0;

        if ($isSu || $hasScope) {
            return ValidationResults::SUCCESS;
        }

        return ValidationResults::UNAUTHORISED;
    }

    /**
     * Validate and decode a JWT token
     *
     * @param string $token a token to validate explicitly, if you do not
     *      specify one the method tries to find one
     * @param string $rawPublicCert public key to validate the token
     * @return array decoded token
     * @throws TokenValidationException Could not validate token
     */
    protected function decodeToken($token, $rawPublicCert)
    {
        try {
            // JWT::decode calls openssl_verify which will cause a fatal error
            // if the certificate is invalid. Calling openssl_pkey_get_public
            // first ensures that the certificate is valid before progressing.
            $pubCert = openssl_pkey_get_public($rawPublicCert);

            if ($pubCert) {
                return (array) JWT::decode($token, $pubCert, ['RS256']);
            }

            $this->getLogger()->error('Invalid public key');
            throw new InvalidPublicKeyException('invalid key');
        } catch (\DomainException $exception) {
            $this->getLogger()->error('Invalid signature', [$exception]);
            throw new InvalidSignatureException('could not validate signature');
        } catch (\UnexpectedValueException $exception) {
            // Expired, before valid, invalid json, etc
            $this->getLogger()->debug('Invalid token', [$exception]);
            throw new InvalidTokenException('invalid token');
        }
    }

    /**
     * Retrieve Persona's public certificate for verifying
     * the integrity & authentication of a given JWT
     * @param integer $cacheTTL time to live in seconds for cached responses
     * @return string certificate
     * @throws \Exception Cannot comminucate with Persona or Redis
     */
    public function retrieveJWTCertificate($cacheTTL = 300)
    {
        $certificate = $this->getCachedCertificate();

        if (!empty($certificate)) {
            return $certificate;
        }

        $certificate = $this->retrievePublicKeyFromPersona();

        if (!empty($certificate)) {
            $this->cacheCertificate($certificate, $cacheTTL);
        }

        return $certificate;
    }

    /**
     * Retrieve Persona's public key
     * @throws \Talis\Persona\Client\CommunicationException Could not retrieve
     *      Persona's public key
     * @return string public key
     */
    protected function retrievePublicKeyFromPersona()
    {
        try {
            $response = $this->performRequest(
                '/oauth/keys',
                [
                    'expectResponse' => true,
                    'addContentType' => true,
                    'parseJson' => false,
                ]
            );

            return $response->__toString();
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                'could not retrieve persona public certificate'
            );

            throw new CommunicationException('cannot retrieve certificate');
        }
    }

    /**
     * Validate the given token by using Persona
     * @param string $token Persona oauth token. If the token is empty the
     *      request will be used to hopefully find a token.
     * @param array $scopes specify this if you wish to validate a scoped token
     * @return integer ValidationResults enum
     * @throws \Exception If you do not supply a token AND it cannot extract
     *      one from $_SERVER, $_GET, $_POST
     */
    protected function validateTokenUsingPersona($token, array $scopes = [])
    {
        // verify against persona
        $this->getStatsD()->increment('validateToken.cache.miss');

        $this->getStatsD()->startTiming('validateToken.rest.get');
        $success = $this->personaCheckTokenIsValid($token, $scopes);
        $this->getStatsD()->endTiming('validateToken.rest.get');

        if ($success === ValidationResults::SUCCESS) {
            $this->getStatsD()->increment('validateToken.rest.valid');
        } else {
            $this->getStatsD()->increment('validateToken.rest.invalid');
        }

        return $success;
    }

    /**
     * Use this method to generate a new token.  You must specify client credentials
     * to do this, for that reason this method will throw an exception if the
     * credentials are missing. If configured, this method will also use the token
     * cache for recently created tokens instead of going to Persona.
     *
     * @param string $clientId Persona client id
     * @param string $clientSecret Persona client secret
     * @param array $params a set of optional parameters you can pass into this method <pre>
     *          scope: (string) to obtain a new scoped token
     *          use_cache: (boolean) use cached called (defaults to true)</pre>
     * @return array containing the token details
     * @throws \Exception If we were unable to generate a new token or if credentials were missing
     */
    public function obtainNewToken($clientId, $clientSecret, array $params = [])
    {
        $this->getStatsD()->increment('obtainNewToken');

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('You must specify clientId, and clientSecret to obtain a new token');
        }

        if (!isset($params['use_cache']) || $params['use_cache'] !== false) {
            $token = $this->getCachedToken($clientId);
            if ($token) {
                return $token;
            }
        }

        $query = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        if (isset($params['scope']) && !empty($params['scope'])) {
            $query['scope'] = $params['scope'];
        }

        $url = $this->getPersonaHost() . $this->config['persona_oauth_route'];
        $this->getStatsD()->startTiming('obtainNewToken.rest.get');
        $token = $this->personaObtainNewToken($url, $query);
        $this->getStatsD()->endTiming('obtainNewToken.rest.get');

        $this->cacheToken($clientId, $token);
        return $token;
    }

    /**
     * List all scopes that belong to a given token
     * @param array $tokenInArray An array containing a JWT under the key `access_token`
     * @return array list of scopes
     *
     * @throws TokenValidationException Invalid signature, key or token
     * @throws \Exception If not able to communicate with Persona to retrieve the public certificate
     */
    public function listScopes(array $tokenInArray)
    {
        if (!isset($tokenInArray['access_token'])) {
            throw new TokenValidationException('missing access token');
        }

        $publicCert = $this->retrieveJWTCertificate();
        $encodedToken = $tokenInArray['access_token'];
        $decodedToken = $this->decodeToken($encodedToken, $publicCert);

        if (isset($decodedToken['scopes']) && is_array($decodedToken['scopes'])) {
            return $decodedToken['scopes'];
        }

        if (isset($decodedToken['scopeCount'])) {
            $meta = $this->personaRetrieveTokenMetadata($encodedToken);

            if (isset($meta['scopes']) && is_string($meta['scopes'])) {
                return explode(' ', $meta['scopes']);
            }

            throw new InvalidTokenException('token metadata missing scopes attribute');
        }

        throw new InvalidTokenException('decoded token has neither scopes nor scopeCount');
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
        if (empty($config) || !isset($config['persona_host'])) {
            throw new \InvalidArgumentException('invalid configuration');
        }
    }

    /**
     * Call Persona
     * @param string $url fully qualified url that will be hit
     * @return array body from http response
     * @throws TokenValidationException Could not validate token
     */
    protected function makePersonaHttpRequest($url)
    {
        try {
            $body = $this->performRequest($url);
        } catch (\Exception $e) {
            $this->getLogger()->error(
                'unable to retrieve token metadata',
                ['exception' => $e]
            );

            switch ($e->getCode()) {
                case 401:
                case 403:
                    throw new UnauthorisedException(
                        "authorisation/authentication issue: {$e->getCode()}"
                    );
                default:
                    throw new UnknownException(
                        "unknown communication error: {$e->getCode()}"
                    );
            }
        }

        if (empty($body)) {
            throw new EmptyResponseException('response body empty');
        }

        return $body;
    }

    /**
     * This method wraps the curl request that is made to persona and
     * returns true or false depending on whether or not persona was
     * able to validate the token.
     *
     * @param string $token token to validate
     * @param array|null $scopes optional scopes to validate
     * @return integer ValidationResults enum
     */
    protected function personaCheckTokenIsValid($token, array $scopes = [])
    {
        $url = $this->getPersonaHost()
            . $this->config['persona_oauth_route']
            . '/'
            . $token;

        if (!empty($scopes)) {
            $queryParams = http_build_query(['scope' => join(',', $scopes)]);
            $url .= "?$queryParams";
        }

        try {
            $this->makePersonaHttpRequest($url);
        } catch (TokenValidationException $e) {
            return $e->getCode();
        }

        $this->getLogger()->debug('Token valid at server');
        return ValidationResults::SUCCESS;
    }

    /**
     * Retrieve a token's metadata
     * @param string $token token to retrieve the metadata for
     * @return array metadata
     * @throws TokenValidationException Could not validate token
     */
    protected function personaRetrieveTokenMetadata($token)
    {
        $url = $this->getPersonaHost()
            . $this->config['persona_oauth_route']
            . '/'
            . $token;

        return $this->makePersonaHttpRequest($url);
    }

    /**
     * Method that wraps the curl post request to persona for obtaining a new
     * token.
     *
     * @param string $url the persona endpoint to make the request against
     * @param array $query the set of parameters that will make up the post fields
     * @return array json decoded array containing the response body from persona
     * @throws \Exception If persona was unable to generate a token
     */
    protected function personaObtainNewToken($url, array $query)
    {
        return $this->performRequest(
            $url,
            [
                'method' => 'POST',
                'body' => http_build_query($query, '', '&'),
            ]
        );
    }
}
