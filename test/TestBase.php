<?php

namespace test;

use PHPUnit\Framework\TestCase;

abstract class TestBase extends TestCase
{
    /**
     * Backward-compatible way of setting exception expectations.
     *
     * @param mixed  $exceptionName
     * @param string $exceptionMessage
     * @param int    $exceptionCode
     * @return void
     */
    public function setExpectedException($exceptionName, $exceptionMessage = '', $exceptionCode = null)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($exceptionName);
            if ($exceptionMessage !== '') {
                $this->expectExceptionMessage($exceptionMessage);
            }
            if ($exceptionCode !== null) {
                $this->expectExceptionCode($exceptionCode);
            }
        } else {
            parent::setExpectedException($exceptionName, $exceptionMessage, $exceptionCode);
        }
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

    /**
     * @before
     */
    protected function printName()
    {
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
