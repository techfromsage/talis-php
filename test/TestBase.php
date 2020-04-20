<?php

namespace test;

use Doctrine\Common\Cache\FilesystemCache;

abstract class TestBase extends \PHPUnit_Framework_TestCase
{
    protected $cacheBackend = null;

    public function __construct()
    {
        $this->cacheBackend = new FilesystemCache(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'personaCache'
        );
    }

    protected function removeCacheFolder()
    {
        $dir = '/tmp/personaCache';

        if (!file_exists($dir)) {
            return;
        }

        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Retrieve Persona's configuration
     * @return array configuration (host, oauthClient, oauthSecret)
     */
    protected function getPersonaConfig()
    {
        return [
            'host' => envvalue('PERSONA_TEST_HOST', 'http://persona.talis.local'),
            'oauthClient' => envvalue('PERSONA_TEST_OAUTH_CLIENT', 'primate'),
            'oauthSecret' => envvalue('PERSONA_TEST_OAUTH_SECRET', 'bananas'),
        ];
    }

    protected function setUp()
    {
        $this->removeCacheFolder();
        $className = get_class($this);
        $testName = $this->getName();
        echo " Test: {$className}->{$testName}\n";
    }

    /**
     * @param string $version Override this to a specific version (defaults to the latest).
     * @return string The versioned persona host used in the test environment.
     */
    protected function versionedPersonaHost($version = \Talis\Persona\Client\Base::PERSONA_API_VERSION)
    {
        return "localhost/${version}";
    }
}
