<?php

namespace Talis\Persona\Client;

trait TokenCache
{
    /**
     * Get cached access token for client id
     * @param string $clientId client id that owns the access token
     * @return string|null access token
     */
    protected function getCachedToken($clientId)
    {
        $cacheKey = $this->getAccessTokenCacheKey($clientId);
        return $this->getCacheBackend()->fetch($cacheKey);
    }

    /**
     * Cache a access token
     * @param string $clientId client id the access token belongs to
     * @param array $token access token to store
     */
    protected function cacheToken($clientId, array $token)
    {
        if ($token && isset($token['expires_in'])) {
            // Add a 60 second leeway as the expires time does not take into
            // consideration the time taken to communication with Persona
            // in both directions.. This leads to a edge case where the
            // token has expired, but the cache hasn't removed it yet
            $expiresIn = intval($token['expires_in'], 10) - 60;

            if ($expiresIn > 0) {
                $this->getCacheBackend()->save(
                    $this->getAccessTokenCacheKey($clientId),
                    $token,
                    $expiresIn
                );
            }
        }
    }

    /**
     * Format a access token cache key
     * @param string $clientId client id the access token belongs to
     * @return string access token cache key
     */
    protected function getAccessTokenCacheKey($clientId)
    {
        return 'accesstoken_' . md5($clientId);
    }

    /**
     * Retrieve the cache backend
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    abstract protected function getCacheBackend();
}
