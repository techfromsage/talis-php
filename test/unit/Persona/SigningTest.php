<?php

$appRoot = dirname(dirname(dirname(__DIR__)));
if (!defined('APPROOT')) {
    define('APPROOT', $appRoot);
}

require_once $appRoot . '/test/unit/TestBase.php';

use Talis\Persona\Client\Signing;

class SigningTest extends TestBase
{
    public function testMissingUrlThrowsException()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'invalid url or secret'
        );
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        date_default_timezone_set('UTC');
        $signedUrl = $personaClient->presignUrl('', 'mysecretkey', null);
    }

    public function testMissingSecretThrowsException()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'invalid url or secret'
        );
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        date_default_timezone_set('UTC');
        $signedUrl = $personaClient->presignUrl('http://someurl', '', null);
    }

    public function testPresignUrlNoExpiry()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        date_default_timezone_set('UTC');
        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute',
            'mysecretkey',
            null
        );

        $this->assertContains('?expires=', $signedUrl);
    }

    public function testPresignUrlNoExpiryAnchor()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        date_default_timezone_set('UTC');
        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute#myAnchor',
            'mysecretkey',
            null
        );

        // assert ?expiry comes before #
        $pieces = explode('#', $signedUrl);
        $this->assertTrue(count($pieces) == 2);
        $this->assertContains('?expires=', $pieces[0]);
    }

    public function testPresignUrlNoExpiryExistingQueryString()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        date_default_timezone_set('UTC');
        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo#myAnchor',
            'mysecretkey',
            null
        );

        $this->assertContains('?myparam=foo&expires=', $signedUrl);
    }

    public function testPresignUrlNoExpiryAnchorExistingQueryString()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        date_default_timezone_set('UTC');
        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo#myAnchor',
            'mysecretkey',
            null
        );

        // assert ?expiry comes before #
        $pieces = explode('#', $signedUrl);
        $this->assertTrue(count($pieces) == 2);
        $this->assertContains('?myparam=foo&expires=', $pieces[0]);
    }

    public function testPresignUrlWithExpiry()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute',
            'mysecretkey',
            1234567890
        );

        $this->assertEquals(
            'http://someurl/someroute?expires=1234567890'
            . '&signature=5be20a17931f220ca03d446a2574'
            . '8a9ef707cd508c753760db11f1f95485f1f6',
            $signedUrl
        );
    }

    public function testPresignUrlWithExpiryAnchor()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute#myAnchor',
            'mysecretkey',
            1234567890
        );

        $this->assertEquals(
            'http://someurl/someroute?expires=1234567890&'
            .  'signature=c4fbb2b15431ef08e861687bd55fd0ab98'
            . 'bb52eee7a1178bdd10888eadbb48bb#myAnchor',
            $signedUrl
        );
    }

    public function testPresignUrlWithExpiryExistingQuerystring()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo',
            'mysecretkey',
            1234567890
        );

        $this->assertEquals(
            'http://someurl/someroute?myparam=foo&expires=1234567890'
            . '&signature=7675bae38ddea8c2236d208a5003337f926af4ebd3'
            . '3aac03144eb40c69d58804',
            $signedUrl
        );
    }

    public function testPresignUrlWithExpiryAnchorExistingQuerystring()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $signedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo#myAnchor',
            'mysecretkey',
            1234567890
        );

        $this->assertEquals(
            'http://someurl/someroute?myparam=foo&expires=1234567890'
            . '&signature=f871db0896f6e893b607d2987ccc838786114b9778'
            . 'b4dbae2b554c2faf9486a1#myAnchor',
            $signedUrl
        );
    }

    public function testIsPresignedUrlValidTimeInFuture()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $presignedUrl = $personaClient->presignUrl(
            'http://someurl/someroute',
            'mysecretkey',
            '+5 minutes'
        );

        $this->assertTrue($personaClient->isPresignedUrlValid(
            $presignedUrl,
            'mysecretkey'
        ));
    }

    public function testIsPresignedUrlValidTimeInFutureExistingParams()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $presignedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo',
            'mysecretkey',
            '+5 minutes'
        );

        $this->assertTrue($personaClient->isPresignedUrlValid(
            $presignedUrl,
            'mysecretkey'
        ));
    }

    public function testIsPresignedUrlValidTimeInFutureExistingParamsAnchor()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $presignedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo#myAnchor',
            'mysecretkey',
            '+5 minutes'
        );

        $this->assertTrue($personaClient->isPresignedUrlValid(
            $presignedUrl,
            'mysecretkey'
        ));
    }

    public function testIsPresignedUrlValidTimeInPastExistingParamsAnchor()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $presignedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo#myAnchor',
            'mysecretkey',
            '-5 minutes'
        );

        $this->assertFalse($personaClient->isPresignedUrlValid(
            $presignedUrl,
            'mysecretkey'
        ));
    }

    public function testIsPresignedUrlValidRemoveExpires()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $presignedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo#myAnchor',
            'mysecretkey',
            '+5 minutes'
        );

        $presignedUrl = str_replace('expires=', 'someothervar=', $presignedUrl);

        $this->assertFalse($personaClient->isPresignedUrlValid(
            $presignedUrl,
            'mysecretkey'
        ));
    }

    public function testIsPresignedUrlValidRemoveSig()
    {
        $personaClient = new Signing(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );

        $presignedUrl = $personaClient->presignUrl(
            'http://someurl/someroute?myparam=foo#myAnchor',
            'mysecretkey',
            '+5 minutes'
        );

        $presignedUrl = str_replace('signature=', 'someothervar=', $presignedUrl);

        $this->assertFalse($personaClient->isPresignedUrlValid(
            $presignedUrl,
            'mysecretkey'
        ));
    }
}
