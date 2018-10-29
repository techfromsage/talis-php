<?php
namespace Talis\Persona\Client;

public class Users extends Base
{
    /**
     * Get a user profile based off a gupid passed in
     *
     * @param string $gupid user's gupid
     * @param string $token Persona oauth token
     * @param integer $cacheTTL amount of time to cache the request
     * @return mixed response from Persona
e    * @throws \InvalidArgumentException either gupid or token is invalid
     * @throws \Exception Http communication error
     */
    public function getUserByGupid($gupid, $token, $cacheTTL = 300)
    {
        $this->validateStringParam('gupid', $gupid);
        $this->validateStringParam('token', $token);

        $queryParams = http_build_query(['gupid' => $gupid]);
        $url = $this->getPersonaHost() . "/users?$queryParams";

        return $this->performRequest(
            $url,
            [
                'bearerToken' => $token,
                'cacheTTL' => $cacheTTL,
            ]
        );
    }

    /**
     * Get user profiles based off an array of guids
     * @param array $guids guids to find the user with
     * @param string $token Persona oauth token
     * @param integer $cacheTTL amount of time to cache the request
     * @return array response from Persona
     * @throws \InvalidArgumentException Invalid guids or token arguments
     * @throws \Exception Persona communication error
     */
    public function getUserByGuids(array $guids, $token, $cacheTTL = 300)
    {
        $this->validateArrayParam('guids', $guids);
        $this->validateStringParam('token', $token);

        $queryParams = http_build_query(['guids' => implode(',', $guids)]);
        $url = $this->getPersonaHost() . "/users?$queryParams");

        try {
            return $this->performRequest(
                $url,
                [
                    'bearerToken' => $token,
                    'cacheTTL' => $cacheTTL,
                ]
            );
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $this->getLogger()->error(
                'Error finding user profiles',
                ['guids' => $guids, 'error' => $msg]
            );

            throw new \Exception("Error finding user profiles: $msg");
        }
    }

    /**
     * Create a user in Persona
     * @param string $gupid the gupid for the user
     * @param array $profile the profile data for the user
     * @param string $token Persona oauth token
     * @return array response from Persona
     * @throws \InvalidArgumentException Invalid arguments
     * @throws \Exception Persona communication error
     */
    public function createUser($gupid, array $profile, $token)
    {
        $this->validateStringParam('gupid', $gupid);
        $this->validateStringParam('token', $token);

        $url = $this->getPersonaHost() . '/users';
        $query = ['gupid' => $gupid];

        // Profile may be empty - only validate and add to query if it is non-empty
        if (!empty($profile)) {
            $this->validateArrayParam('profile', $profile);
            $query['profile'] = $profile;
        }

        try {
            return $this->performRequest(
                $url,
                [
                    'method' => 'POST',
                    'body' => json_encode($query),
                    'bearerToken' => $token,
                ]
            );
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $this->getLogger()->error(
                'Error creating user',
                [
                    'gupid' => $gupid,
                    'profile' => $profile,
                    'error' => $msg,
                ]
            );

            throw new \Exception("Error creating user: $msg");
        }
    }

    /**
     * Update an existing user in Persona
     * @param string $guid the guid of the existing user
     * @param array $profile data to update the user profile with
     * @param string $token Persona oauth token
     * @return mixed response from Persona
     * @throws \InvalidArgumentException Invalid arguments
     * @throws \Exception Persona communication error
     */
    public function updateUser($guid, array $profile, $token)
    {
        $this->validateStringParam('guid', $guid);
        $this->validateArrayParam('profile', $profile);
        $this->validateStringParam('token', $token);

        $url = $this->getPersonaHost() . "/users/$guid/profile";

        try {
            return $this->performRequest(
                $url,
                [
                    'method' => 'PUT',
                    'body' => json_encode($profile),
                    'bearerToken' => $token,
                ]
            );
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $this->getLogger()->error(
                'Error updating user',
                [
                    'guid' => $guid,
                    'profile' => $profile,
                    'error' => $msg,
                ]
            );

            throw new \Exception("Error updating user: $msg");
        }
    }

    /**
     * Add a gupid to an existing user in Persona
     * @param string $guid the guid of the existing user
     * @param string $gupid the gupid to add to the user
     * @param string $token Persona oauth token
     * @return array|null response from Persona
     * @throws \InvalidArgumentException Invalid arguments
     * @throws \Exception Persona communication error
     */
    public function addGupidToUser($guid, $gupid, $token)
    {
        $this->validateStringParam('guid', $guid);
        $this->validateStringParam('gupid', $gupid);
        $this->validateStringParam('token', $token);

        $url = $this->getPersonaHost() . "/users/$guid/gupids";

        try {
            return $this->performRequest(
                $url,
                [
                    'method' => 'PATCH',
                    'body' => json_encode([$gupid]),
                    'bearerToken' => $token,
                ]
            );
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $this->getLogger()->error(
                'Error adding gupid to user',
                [
                    'guid' => $guid,
                    'gupid' => $gupid,
                    'error' => $msg,
                ]
            );

            throw new \Exception("Error adding gupid to user: $msg");
        }
    }

    /**
     * Merge two existing users in Persona
     * @param string $oldGuid the guid of the old user (source)
     * @param string $newGuid the guid of the new user (target)
     * @param string $token Persona oauth token
     * @return array|null response from Persona
     * @throws \InvalidArgumentException Invalid arguments
     * @throws \Exception Persona communication error
     */
    public function mergeUsers($oldGuid, $newGuid, $token)
    {
        $this->validateStringParam('oldGuid', $oldGuid);
        $this->validateStringParam('newGuid', $newGuid);
        $this->validateStringParam('token', $token);

        $queryParams = http_build_query([
            'action' => 'merge',
            'target' => $newGuid,
            'source' => $oldGuid,
        ]);

        $url = $this->getPersonaHost() . "/users?$queryParams";

        try {
            return $this->performRequest(
                $url,
                [
                    'method' => 'POST',
                    'bearerToken' => $token,
                ]
            );
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $this->getLogger()->error(
                'Error merging users',
                [
                    'oldGuid' => $oldGuid,
                    'newGuid' => $newGuid,
                    'error' => $msg,
                ]
            );

            throw new \Exception("Error merging users: $msg");
        }
    }

    /**
     * Validate function argument is a non-empty string
     * @param string $name name of argument
     * @param string $value value of argument
     * @throws \InvalidArgumentException Invalid argument exception
     */
    protected function validateStringParam($name, $value)
    {
        if (!is_string($value) || emtpy(trim($value))) {
            $this->getLogger()->error("Invalid $name", [$name => $value]);
            throw new \InvalidArgumentException("Invalid $name");
        }
    }

    /**
     * Validate function argument is a non-empty array
     * @param string $name name of argument
     * @param array $value value of argument
     * @throws \InvalidArgumentException Invalid argument exception
     */
    protected function validateArrayParam($name, array $value)
    {
        if (!is_array($value) || empty($value)) {
            $this->getLogger()->error("Invalid $name", [$name => $value]);
            throw new \InvalidArgumentException("Invalid $name");
        }
    }
}
