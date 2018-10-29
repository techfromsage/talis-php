<?php

namespace Talis\Persona\Client;

class OAuthClients extends Base
{
    /**
     * Return an outh client
     *
     * @param string $clientId Persona client id
     * @param string $token Persona client token
     * @param integer $cacheTTL time to live in seconds value for cached request
     * @return array response from Persona
     * @throws \InvalidArgumentException Invalid arguments
     * @throws \Exception Persona communication issues
     */
    public function getOAuthClient($clientId, $token, $cacheTTL = 300)
    {
        if (!is_string($clientId) || empty(trim($clientId))) {
            $this->getLogger()->error("Invalid clientId '$clientId'");
            throw new \InvalidArgumentException('Invalid clientId');
        }

        if (!is_string($token) || empty(trim($token))) {
            $this->getLogger()->error("Invalid token '$token'");
            throw new \InvalidArgumentException('Invalid token');
        }

        $url = $this->getPersonaHost() . '/clients/' . $clientId;
        return $this->personaGetOAuthClient($url, $token, $cacheTTL);
    }

    /**
     * Update a users OAuth client
     *
     * @param string $clientId Persona client id
     * @param array $properties oauth client properties
     * @param string $token Persona client token
     * @return boolean true if successful
     * @throws \InvalidArgumentException Invalid arguments
     * @throws \Exception Persona communication issues
     */
    public function updateOAuthClient($clientId, array $properties, $token)
    {
        if (!is_string($clientId) || empty(trim($clientId))) {
            throw new \InvalidArgumentException('Invalid guid');
        }

        if (!is_array($properties) || empty($properties)) {
            throw new \InvalidArgumentException('Invalid properties');
        }

        if (!is_string($token) || empty(trim($token))) {
            throw new \InvalidArgumentException('Invalid token');
        }

        // Check valid keys.
        // "scope" only supports 2 keys, "$add" and "$remove". These 2 checks
        // ensure that at least 1 of these must be present, and that there are
        // no others passed through.
        if (!isset($properties['scope']) || count($properties['scope']) === 0) {
            throw new \InvalidArgumentException('Invalid properties');
        }

        $operations = array_intersect(
            ['$add', '$remove'],
            array_keys($properties['scope'])
        );

        if (count($operations) !== count($properties['scope'])) {
            throw new \InvalidArgumentException('Invalid properties');
        }

        $url = $this->getPersonaHost() . "/clients/$clientId";
        return $this->personaPatchOAuthClient($url, $properties, $token);
    }

    /**
     * Patch an OAuth Client
     * @param string $url Persona url
     * @param array $properties http body
     * @param string $token Persona oauth token
     * @throws \Exception Persona communication issues
     */
    protected function personaPatchOAuthClient($url, array $properties, $token)
    {
        $this->performRequest(
            $url,
            [
                'method' => 'PATCH',
                'body' => json_encode($properties),
                'bearerToken' => $token,
                'expectResponse' => false,
            ]
        );
    }

    /**
     * Get an OAuth Client
     * @param string $url Persona url
     * @param string $token Persona oauth token
     * @param integer $cacheTTL time to live in seconds value for cached request
     * @return array Persona response
     * @throws \Exception Persona communication issues
     */
    protected function personaGetOAuthClient($url, $token, $cacheTTL = 300)
    {
        return $this->performRequest(
            $url,
            [
                'bearerToken' => $token,
                'cacheTTL' => $cacheTTL,
            ]
        );
    }
}
