<?php

namespace Talis\Persona\Client;

class OAuthClients extends Base
{
    /**
     * Return an outh client
     *
     * @param string $clientId Persona client id
     * @param string $token Persona client token
     * @return array response from Persona
     * @throws \InvalidArgumentException Invalid arguments
     * @throws \Exception Persona communication issues
     */
    public function getOAuthClient($clientId, $token)
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
        return $this->personaGetOAuthClient($url, $token);
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
        if (empty($properties['scope'])) {
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
     * @return array Persona response
     * @throws \Exception Persona communication issues
     */
    protected function personaGetOAuthClient($url, $token)
    {
        return $this->performRequest($url, ['bearerToken' => $token]);
    }

    /**
     * Generate and append or replace a oauth client's secret.
     * @param string $clientId oauth client (persona user guid is also a oauth client id)
     * @param string $token Persona oauth token
     * @return string new the oauth client secret
     * @throws \InvalidConfigurationException persona_admin_host not supplied
     * @throws \Exception Persona communication issues
     */
    public function regenerateSecret($clientId, $token)
    {
        $adminHost = $this->getPersonaAdminHost();
        $resp = $this->performRequest(
             "$adminHost/clients/$clientId/generatesecret",
            [
                'method' => 'PATCH',
                'bearerToken' => $token,
                'expectResponse' => true,
            ]
        );

        if (isset($resp['secret'])) {
            return $resp['secret'];
        } else {
            throw new \Exception('invalid payload format from persona');
        }
    }
}
