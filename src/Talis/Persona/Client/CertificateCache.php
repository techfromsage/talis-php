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
        try {
            return $this->getCacheBackend()->fetch(
                $this->getCertificateCacheKey($id)
            );
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                'unable to get cached certificate',
                [
                    'id' => $id,
                    'exception' => $e,
                ]
            );
        }

        return null;
    }

    /**
     * Cache a certificate
     * @param string $certificate certificate contents
     * @param integer $expiry expiry time in seconds
     * @param string $id certificate id
     */
    protected function cacheCertificate($certificate, $expiry, $id='pub')
    {
        try {
            $this->getCacheBackend()->save(
                $this->getCertificateCacheKey($id),
                $certificate,
                $expiry
            );
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                'unable to cache certificate',
                [
                    'certificate' => $certificate,
                    'expiry' => $expiry,
                    'id' => $id,
                    'exception' => $exception,
                ]
            );
        }
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

    /**
     * Retrieve logger
     * @return Logger|\Psr\Log\LoggerInterface
     */
    abstract protected function getLogger();
}
