<?php

namespace Talis\Persona\Client;

class Login extends Base
{
    // Constants Related to simple-nonce library
    const NONCE_EXPIRY_TIME_SECONDS = 300; // Nonce expires after 5 minutes at which point the persion in logged out
    const NONCE_REFRESH_TIME_SECONDS = 180; // Nonce is refreshed if it is older than the refresh time of 3 minutes
    const LOGIN_STATE_ACTION = 'loginState';
    const NONCE_SALT = 'This is the salt used for generating the nonce';

    // Keys or key prefixes for reading from data saved in cache.
    const NONCE_TIMESTAMP = "NONCE_TIMESTAMP";
    const LOGIN_PREFIX = 'PERSONA';

    /**
     * Require authentication on your route
     * @param string $provider The login provider name you want to authenticate against - e.g. 'google'
     * @param string $appId The ID of the persona application (http://docs.talispersona.apiary.io/#applications)
     * @param string $appSecret The secret of the persona application (http://docs.talispersona.apiary.io/#applications)
     * @param string $redirectUri Origin of the request - used to send a user back to where they originated from
     * @param array $query parameters passed to Persona (currently supports require=profile)
     * @param string $nonce
     * @return string New nonce
     * @throws \InvalidArgumentException Invalid arguments
     */
    public function requireAuth(
        $provider,
        $appId,
        $appSecret,
        $redirectUri = '',
        array $query = null,
        string $nonce = null
    ) {
        if (!is_string($nonce)) {
            $this->getLogger()->error('Invalid nonce');
            throw new \InvalidArgumentException('Invalid nonce');
        }

        if ($this->isLoggedIn($nonce)) {
            if ($this->nonceNeedsRefresh($nonce)) {
                return refreshNonce($nonce);
            }
            return $nonce;
        }

        if (!is_string($provider)) {
            $this->getLogger()->error('Invalid provider');
            throw new \InvalidArgumentException('Invalid provider');
        }
        if (!is_string($appId)) {
            $this->getLogger()->error('Invalid appId');
            throw new \InvalidArgumentException('Invalid appId');
        }
        if (!is_string($appSecret)) {
            $this->getLogger()->error('Invalid appSecret');
            throw new \InvalidArgumentException('Invalid appSecret');
        }
        if ($redirectUri !== '' && !is_string($redirectUri)) {
            $this->getLogger()->error('Invalid redirectUri');
            throw new \InvalidArgumentException('Invalid redirectUri');
        }
 
        $nonceValues = \SoftSmart\Utilities\SimpleNonce::GenerateNonce(self::LOGIN_STATE_ACTION, [self::NONCE_SALT]);
        $nonce = $nonceValues['nonce'];
        $nonceTimestamp = $nonceValues['timestamp'];
        $data = [
          self::NONCE_TIMESTAMP => $nonceTimestamp,
          self::LOGIN_PREFIX . ':loginAppId' => $appId,
          self::LOGIN_PREFIX . ':loginProvider' => $provider,
          self::LOGIN_PREFIX . ':loginAppSecret' => $appSecret
        ];

        $cacheBackend = $this->getCacheBackend();
        try {
            // Save this to the cache with the same expiry as the nonce.
            $cacheBackend->save($nonce, $data, self::NONCE_EXPIRY_TIME_SECONDS);
        } catch (\Exception $e) {
            $this->getLogger()->error('Unable to write to cache');
            // TODO - Define a better exception type?
            throw new \Exception('Unable to write to cache');
        }

        // TODO - Remove - just left for reference while developing
        /* $_SESSION[self::LOGIN_PREFIX . ':loginAppId'] = $appId; */
        /* $_SESSION[self::LOGIN_PREFIX . ':loginProvider'] = $provider; */
        /* $_SESSION[self::LOGIN_PREFIX . ':loginAppSecret'] = $appSecret; */

        // TODO - Moved from above - is that correct?
        /* if ($this->isLoggedIn($nonce)) { */
        /*     return; */
        /* } */

        $this->login($redirectUri, $nonce, $provider, $query);

        return $nonce;
    }

    /**
     * Validate a callback route
     * @param string $nonce
     * @return boolean true if authenticated
     * @throws \Exception Invalid signature
     */
    public function validateAuth($nonce)
    {
        if (!is_string($nonce)) {
            $this->getLogger()->error('Invalid nonce');
            throw new \InvalidArgumentException('Invalid nonce');
        }

        if (!isset($_POST['persona:payload'])) {
            $this->getLogger()->error('Payload not set');
            throw new \Exception('Payload not set');
        }

        if (!isset($_POST['persona:signature'])) {
            $this->getLogger()->error('Signature not set');
            throw new \Exception('Signature not set');
        }

        $payloadSignature = $_POST['persona:signature'];
        $encodedPayload = $_POST['persona:payload'];
        $payload = json_decode(base64_decode($encodedPayload), true);

        // Check for invalid payload strings
        if (!$payload || !is_array($payload)) {
            $this->getLogger()->error("Payload not json: {$payload}");
            throw new \Exception('Payload not json');
        }

        $cacheBackend = $this->getCacheBackend();
        $data = $cacheBackend->fetch($nonce);
        
        if (!isset($data)) {
            // Error with state - not authenticated
            $this->getLogger()->error('Nonce does not match and data in cache');
            throw new \Exception('Nonce does not match and data in cache');
        }
        /* if (!isset($_SESSION[self::LOGIN_PREFIX . ':loginState']) */
        /*     || !isset($payload['state']) */
        /*     || $payload['state'] !== $_SESSION[self::LOGIN_PREFIX . ':loginState'] */
        /* ) { */
        /*     // Error with state - not authenticated */
        /*     $this->getLogger()->error('Login state does not match'); */
        /*     unset($_SESSION[self::LOGIN_PREFIX . ':loginState']); */
        /*     throw new \Exception('Login state does not match'); */
        /* } */

        $signature = hash_hmac(
            'sha256',
            $encodedPayload,
            $data[self::LOGIN_PREFIX . ':loginAppSecret']
            // TODO- Remove Left in for reference
            /* $_SESSION[self::LOGIN_PREFIX . ':loginAppSecret'] */
        );

        if ($payloadSignature !== $signature) {
            // TODO- Remove Left in for reference
            /* unset($_SESSION[self::LOGIN_PREFIX . ':loginState']); */

            // TODO - we used to unset this in the session 
            // Do we need to do anything here? Remove it from the cache?
            $this->getLogger()->error('Signature does not match');
            throw new \Exception('Signature does not match');
        }

        // TODO - Should we be doing anything new here?
        /* // Delete the login state ready for next login */
        /* unset($_SESSION[self::LOGIN_PREFIX . ':loginState']); */

        /* // Final step - validate the token */
        /* $_SESSION[self::LOGIN_PREFIX . ':loginSSO'] = array_merge( */
        /*     [ */
        /*         'token' => false, */
        /*         'guid' => '', */
        /*         'gupid' => [], */
        /*         'profile' => [], */
        /*         'redirect' => '', */
        /*     ], */
        /*     $payload */
        /* ); */

        return $this->isLoggedIn($nonce);
    }

    /**
     * Get users persistent ID - it finds a persistent ID that matches the login provider
     * @param string $nonce
     * @return boolean|string pid else boolean false
     */
    public function getPersistentId($nonce)
    {
        $cacheBackend = $this->getCacheBackend();
        $data = $cacheBackend->fetch($nonce);
        
        if (!isset($data)) {
            return false;
        }

        /* if (isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['gupid']) */
        /*     && !empty($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['gupid']) */
        if (isset($data[self::LOGIN_PREFIX . ':loginSSO']['gupid'])
            && !empty($data[self::LOGIN_PREFIX . ':loginSSO']['gupid'])
        ) {
            // Loop through all gupids and match against the login provider - it should be
            // the prefix of the persona profile
            foreach ($data[self::LOGIN_PREFIX . ':loginSSO']['gupid'] as $gupid) {
                $loginProvider = $data[self::LOGIN_PREFIX . ':loginProvider'];
                if (strpos($gupid, $loginProvider) === 0) {
                    return str_replace("$loginProvider:", '', $gupid);
                }
            }
        }

        return false;
    }

    /**
     * Get redirect URL value
     * @param string $nonce
     * @return string|boolean redirect url else boolean false
     */
    public function getRedirectUrl()
    {
        $cacheBackend = $this->getCacheBackend();
        $data = $cacheBackend->fetch($nonce);
        
        if (!isset($data)) {
            return false;
        }

        if (isset($data[self::LOGIN_PREFIX . ':loginSSO']['redirect'])
            && !empty($data[self::LOGIN_PREFIX . ':loginSSO']['redirect'])
        ) {
            return $data[self::LOGIN_PREFIX . ':loginSSO']['redirect'];
        }

        return false;
    }

    /**
     * Return all scopes for a user
     * @param string $nonce
     * @return array|boolean array of scopes else boolean false
     */
    public function getScopes($nonce)
    {
        $cacheBackend = $this->getCacheBackend();
        $data = $cacheBackend->fetch($nonce);
        
        if (!isset($data)) {
            return false;
        }

        if (isset($data[self::LOGIN_PREFIX . ':loginSSO'])
            && isset($data[self::LOGIN_PREFIX . ':loginSSO']['token'])
            && isset($data[self::LOGIN_PREFIX . ':loginSSO']['token']['scope'])
        ) {
            return $data[self::LOGIN_PREFIX . ':loginSSO']['token']['scope'];
        }

        return false;
    }

    /**
     * Get profile
     * @param string $nonce
     * @return array user's profile
     */
    public function getProfile($nonce)
    {
        $cacheBackend = $this->getCacheBackend();
        $data = $cacheBackend->fetch($nonce);
        
        if (!isset($data)) {
            return false;
        }

        if (isset($data[self::LOGIN_PREFIX . ':loginSSO'])
            && isset($data[self::LOGIN_PREFIX . ':loginSSO']['profile'])
        ) {
            return $data[self::LOGIN_PREFIX . ':loginSSO']['profile'];
        }

        return [];
    }

    /**
     * Check if a user is logged in based on whether session variables exist
     * @param string $nonce
     * @return boolean
     */
    protected function isLoggedIn($nonce)
    {
        // No need to check the cache if we don't have a nonce.
        if ($nonce == null || empty($nonce))
        {
            return false;
        }

        $cacheBackend = $this->getCacheBackend();
        $data = $cacheBackend->fetch($nonce);

        // If we didn't find any data, or the data doesn't contain a timestamp
        // then we are not logged in.
        if ($data == null || !isset($data[self::NONCE_TIMESTAMP]))
        {
            return false;
        }

        // If the nonce is older than the expiry time, then
        // the user is no longer logged in.
        $validWithinExpiryTime = SimpleNonce::VerifyNonce(
            $nonce,
            self::LOGIN_STATE_ACTION,
            $data[self::NONCE_TIMESTAMP],
            [self::NONCE_SALT],
            self::NONCE_EXPIRY_TIME_SECONDS
        );
        if (!$validWithinExpiryTime) {
            return false;
        }
        return true;

        // TODO - DELETE - Left for reference
        /* return isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']); */
    }

    /**
     * Check if nonce should be refreshed
     * @param string $nonce
     * @return boolean
     */
    protected function nonceNeedsRefresh($nonce)
    {
        // No need to check the cache if we don't have a nonce.
        if ($nonce == null || empty($nonce))
        {
            return false;
        }

        $cacheBackend = $this->getCacheBackend();
        $data = $cacheBackend->fetch($nonce);

        // If we didn't find any data, or the data doesn't contain a timestamp
        // then we are not logged in.
        if ($data == null || !isset($data[self::NONCE_TIMESTAMP]))
        {
            return false;
        }

        // If the nonce is older than the expiry time, then
        // the user is no longer logged in.
        $validWithinExpiryTime = SimpleNonce::VerifyNonce(
            $nonce,
            self::LOGIN_STATE_ACTION,
            $data[self::NONCE_TIMESTAMP],
            [self::NONCE_SALT],
            self::NONCE_REFRESH_TIME_SECONDS
        );
        // This could return !SimpleNonce::VerifyNonce(...); But I think this makes it more readable.
        return $validWithinExpiryTime == false;
    }

    /**
     * Recreate a new nonce, before the old one expires.
     *
     * This function creates a new nonce, and moves the data in the
     * cache from the old nonce to the new nonce.
     *
     * @param string $oldNonce
     * @return string $newNonce
     */
    protected function refreshNonce($nonce)
    {
        // No need to check the cache if we don't have a nonce.
        if ($nonce == null || empty($nonce))
        {
            $this->getLogger()->error('Invalid nonce');
            throw new \InvalidArgumentException('Invalid nonce');
        }

        $cacheBackend = $this->getCacheBackend();
        $oldData = $cacheBackend->fetch($nonce);

        // If we didn't find any data, or the data doesn't contain a timestamp
        // then we are not logged in.
        if ($oldData == null)
        {
            $this->getLogger()->error('Invalid nonce');
            throw new \InvalidArgumentException('Invalid nonce');
        }

        $newNonceValues = \SoftSmart\Utilities\SimpleNonce::GenerateNonce(self::LOGIN_STATE_ACTION, [self::NONCE_SALT]);
        $newNonce = $newNonceValues['nonce'];
        $newNonceTimestamp = $newNonceValues['timestamp'];
        
        $newData = [
          self::NONCE_TIMESTAMP => $newNonceTimestamp,
          self::LOGIN_PREFIX . ':loginAppId' => $oldData[self::LOGIN_PREFIX . ':loginAppId'],
          self::LOGIN_PREFIX . ':loginProvider' => $oldData[self::LOGIN_PREFIX . ':loginProvider'],
          self::LOGIN_PREFIX . ':loginAppSecret' => $oldData[self::LOGIN_PREFIX . ':loginAppSecret']
        ];

        try {
            $cacheBackend->save($newNonce, $newData, self::NONCE_EXPIRY_TIME_SECONDS);
            // TODO Is the following the correct way to clear it from the cache?
            // If it's goung to expire - do we need to?
            $cacheBackend->save($oldNonce, null, self::NONCE_EXPIRY_TIME_SECONDS);
        } catch (\Exception $e) {
            $this->getLogger()->error('Unable to write to cache');
            // TODO - Define a better exception type?
            throw new \Exception('Unable to write to cache');
        }

        return $newNonce;
    }

    /**
     * Perform a Persona login to the login provider of choice. This method will
     * not return as the user will be redirect to Persona and the PHP process
     * will exist.
     *
     * @param string $redirectUri where to return to once login has completed
     * @param string $nonce
     * @param string $loginProvider
     * @param array $query parameters passed in Persona (currently supports require=profile)
     */
    protected function login($redirectUri = '', string $nonce, string $loginProvider, array $query = null)
    {
        // TODO Remove - we no longer store login state in the session?
        /* // Create a uniq ID for state - prefixed with md5 hash of app ID */
        /* $loginState = $this->getLoginState(); */

        /* // Save login state in session */
        /* $_SESSION[self::LOGIN_PREFIX . ':loginState'] = $loginState; */

        // Log user in
        $redirect = $this->getPersonaHost()
            . '/auth/providers/'
            . $loginProvider
            // TODO - Remove - just left for reference
            // . $_SESSION[self::LOGIN_PREFIX . ':loginProvider']
            . '/login';

        if (empty($query)) {
            $query = [];
        }

        if (!empty($redirectUri)) {
            $query['redirectUri'] = $redirectUri;
        }

        // TODO Replaced State with Nonce - remove this
        // $query['state'] = $loginState;
        $query['app'] = $_SESSION[self::LOGIN_PREFIX . ':loginAppId'];
        $query['nonce'] = $nonce;

        $redirect .= '?' . http_build_query($query);
        $this->redirect($redirect);
    }

    /**
     * Generate a unique id which is seeded by the hash of the loginAppId value.
     */
    //TODO - This is reading from the session - remove it. Do we need an  equivalent?
    /* protected function getLoginState() */
    /* { */
    /*     $appId = $_SESSION[self::LOGIN_PREFIX . ':loginAppId']; */
    /*     $seed = md5("$appId::"); */
    /*     return uniqid($seed, true); */
    /* } */

    /**
     * Redirect the browser. This method will not return.
     *
     * @param string $location http url to redirect to
     */
    protected function redirect($location)
    {
        header("Location: $location");
        exit;
    }
}
