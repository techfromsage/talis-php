<?php

namespace Talis\Persona\Client;

trait ClientVersionCache
{
    const COMPOSER_VERSION_CACHE_KEY = 'composer_version';
    const COMPOSER_VERSION_CACHE_TTL_SEC = 3600; // 1 hour

    /**
     * Retrieve the Persona client version
     * @return string Persona client version
     */
    protected function getClientVersion()
    {
        $cacheBackend = $this->getCacheBackend();
        $version = $cacheBackend->fetch(self::COMPOSER_VERSION_CACHE_KEY);

        if ($version) {
            return $version;
        }

        $composerFileContent = file_get_contents(
            __DIR__ . '/../../../../composer.json'
        );

        if ($composerFileContent === false) {
            return 'unknown';
        }

        $composer = json_decode($composerFileContent, true);

        $version = 'unknown';
        if (isset($composer['version'])) {
            $version = $composer['version'];
        }

        $cacheBackend->save(
            self::COMPOSER_VERSION_CACHE_KEY,
            $version,
            self::COMPOSER_VERSION_CACHE_TTL_SEC
        );

        return $version;
    }

    /**
     * Retrieve the cache backend
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    abstract protected function getCacheBackend();
}
