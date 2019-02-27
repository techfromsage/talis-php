<?php

namespace Talis\Persona\Client;

trait CertificateCache
{
    /**
     * Retrieve a cached certificate
     * @param string $id certificate id
     * @return string|null certificate
     */
    protected function getCachedCertificate($id='pub')
    {
        return $this->getCacheBackend()->fetch(
            $this->getCertificateCacheKey($id)
        );
    }

    /**
     * Cache a certificate
     * @param string $certificate certificate contents
     * @param integer $expiry expiry time in seconds
     * @param string $id certificate id
     */
    protected function cacheCertificate($certificate, $expiry, $id='pub')
    {
        $this->getCacheBackend()->save(
            $this->getCertificateCacheKey($id),
            $certificate,
            $expiry
        );
    }

    protected function getCertificateCacheKey($id='pub')
    {
        return "cert_$id";
    }

    /**
     * Retrieve the cache backend
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    abstract protected function getCacheBackend();
}
