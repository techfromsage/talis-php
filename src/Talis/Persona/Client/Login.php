<?php

namespace Talis\Persona\Client;

class Login extends Base
{
    const LOGIN_PREFIX = 'PERSONA';

    /**
     * Require authentication on your route
     * @param string $provider The login provider name you want to authenticate against - e.g. 'google'
     * @param string $appId The ID of the persona application (http://docs.talispersona.apiary.io/#applications)
     * @param string $appSecret The secret of the persona application (http://docs.talispersona.apiary.io/#applications)
     * @param string $redirectUri Origin of the request - used to send a user back to where they originated from
     * @param array $query parameters passed to Persona (currently supports require=profile)
     * @return mixed
     * @throws \InvalidArgumentException Invalid arguments
     */
    public function requireAuth(
        $provider,
        $appId,
        $appSecret,
        $redirectUri = '',
        array $query = null
    ) {
        // Already authenticated
        if ($this->isLoggedIn()) {
            return;
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

        $_SESSION[self::LOGIN_PREFIX . ':loginAppId'] = $appId;
        $_SESSION[self::LOGIN_PREFIX . ':loginProvider'] = $provider;
        $_SESSION[self::LOGIN_PREFIX . ':loginAppSecret'] = $appSecret;

        $this->login($redirectUri, $query);
    }

    /**
     * Validate a callback route
     * @return boolean true if authenticated
     * @throws \Exception Invalid signature
     */
    public function validateAuth()
    {
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

        if (
            !isset($_SESSION[self::LOGIN_PREFIX . ':loginState'])
            || !isset($payload['state'])
            || $payload['state'] !== $_SESSION[self::LOGIN_PREFIX . ':loginState']
        ) {
            // Error with state - not authenticated
            $this->getLogger()->error('Login state does not match');
            unset($_SESSION[self::LOGIN_PREFIX . ':loginState']);
            throw new \Exception('Login state does not match');
        }

        $signature = hash_hmac(
            'sha256',
            $encodedPayload,
            $_SESSION[self::LOGIN_PREFIX . ':loginAppSecret']
        );

        if ($payloadSignature !== $signature) {
            unset($_SESSION[self::LOGIN_PREFIX . ':loginState']);
            $this->getLogger()->error('Signature does not match');
            throw new \Exception('Signature does not match');
        }

        // Delete the login state ready for next login
        unset($_SESSION[self::LOGIN_PREFIX . ':loginState']);

        // Final step - validate the token
        $_SESSION[self::LOGIN_PREFIX . ':loginSSO'] = array_merge(
            [
                'token' => false,
                'guid' => '',
                'gupid' => [],
                'profile' => [],
                'redirect' => '',
            ],
            $payload
        );

        return $this->isLoggedIn();
    }

    /**
     * Get users persistent ID - it finds a persistent ID that matches the login provider
     * @return boolean|string pid else boolean false
     */
    public function getPersistentId()
    {
        if (!isset($_SESSION[self::LOGIN_PREFIX . ':loginProvider'])) {
            return false;
        }

        if (
            isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['gupid'])
            && !empty($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['gupid'])
        ) {
            // Loop through all gupids and match against the login provider - it should be
            // the prefix of the persona profile
            foreach ($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['gupid'] as $gupid) {
                $loginProvider = $_SESSION[self::LOGIN_PREFIX . ':loginProvider'];
                if (strpos($gupid, $loginProvider) === 0) {
                    return str_replace("$loginProvider:", '', $gupid);
                }
            }
        }

        return false;
    }

    /**
     * Get redirect URL value
     * @return string|boolean redirect url else boolean false
     */
    public function getRedirectUrl()
    {
        if (
            isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['redirect'])
            && !empty($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['redirect'])
        ) {
            return $_SESSION[self::LOGIN_PREFIX . ':loginSSO']['redirect'];
        }

        return false;
    }

    /**
     * Return all scopes for a user
     * @return array|boolean array of scopes else boolean false
     */
    public function getScopes()
    {
        if (
            isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO'])
            && isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['token'])
            && isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['token']['scope'])
        ) {
            return $_SESSION[self::LOGIN_PREFIX . ':loginSSO']['token']['scope'];
        }

        return false;
    }

    /**
     * Get profile
     * @return array user's profile
     */
    public function getProfile()
    {
        if (
            isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO'])
            && isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']['profile'])
        ) {
            return $_SESSION[self::LOGIN_PREFIX . ':loginSSO']['profile'];
        }

        return [];
    }

    /**
     * Check if a user is logged in based on whether session variables exist
     * @return boolean
     */
    protected function isLoggedIn()
    {
        return isset($_SESSION[self::LOGIN_PREFIX . ':loginSSO']);
    }

    /**
     * Perform a Persona login to the login provider of choice. This method will
     * not return as the user will be redirect to Persona and the PHP process
     * will exist.
     *
     * @param string $redirectUri where to return to once login has completed
     * @param array $query parameters passed in Persona (currently supports require=profile)
     */
    protected function login($redirectUri = '', array $query = null)
    {
        // Create a uniq ID for state - prefixed with md5 hash of app ID
        $loginState = $this->getLoginState();

        // Save login state in session
        $_SESSION[self::LOGIN_PREFIX . ':loginState'] = $loginState;

        // Log user in
        $redirect = $this->getPersonaHost()
            . '/auth/providers/'
            . $_SESSION[self::LOGIN_PREFIX . ':loginProvider']
            . '/login';

        if (empty($query)) {
            $query = [];
        }

        if (!empty($redirectUri)) {
            $query['redirectUri'] = $redirectUri;
        }

        $query['state'] = $loginState;
        $query['app'] = $_SESSION[self::LOGIN_PREFIX . ':loginAppId'];

        $redirect .= '?' . http_build_query($query);
        $this->redirect($redirect);
    }

    /**
     * Generate a unique id which is seeded by the hash of the loginAppId value.
     */
    protected function getLoginState()
    {
        $appId = $_SESSION[self::LOGIN_PREFIX . ':loginAppId'];
        $seed = md5("$appId::");
        return uniqid($seed, true);
    }

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
