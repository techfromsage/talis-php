<?php

namespace Talis\Persona\Client;

class Signing extends Base
{
    /**
     * Checks the supplied config, verifies that all required parameters are present and
     * contain a non null value;
     *
     * @param array $config the configuration options to validate
     * @throws \InvalidArgumentException If the config is invalid
     */
    protected function checkConfig(array $config)
    {
        return;
    }

    /**
     * Signs the given $url plus an $expiry param with the $secret and returns it
     * @param string $url url to sign
     * @param string $secret secret for signing
     * @param integer|string $expiry defaults to '+15 minutes'
     * @return string signed url
     * @throws \InvalidArgumentException Invalid arguments
     */
    public function presignUrl($url, $secret, $expiry = null)
    {
        if (empty($url) || empty($secret)) {
            throw new \InvalidArgumentException('invalid url or secret');
        }

        if (empty($expiry)) {
            $expiry = '+15 minutes';
        }

        $expParam = strpos($url, '?') === false ? '?' : '&';
        $expParam .= is_int($expiry) ? "expires=$expiry" : 'expires=' . strtotime($expiry);

        if (strpos($url, '#') !== false) {
            $url = substr_replace($url, $expParam, strpos($url, '#'), 0);
        } else {
            $url .= $expParam;
        }

        $sig = $this->getSignature($url, $secret);

        $sigParam = strpos($url, '?') === false ? "?signature=$sig" : "&signature=$sig";

        if (strpos($url, '#') !== false) {
            $url = substr_replace($url, $sigParam, strpos($url, '#'), 0);
        } else {
            $url .= $sigParam;
        }

        return $url;
    }

    /**
     * Check if a presigned URL is valid
     * @param string $url url to check
     * @param string $secret secret to generate the url signature with
     * @return boolean true if signed and valid
     */
    public function isPresignedUrlValid($url, $secret)
    {
        $query = [];
        $urlParts = parse_url($url);
        parse_str($urlParts['query'], $query);

        if (
            !isset($query['expires'])
            || !isset($query['signature'])
            || intval($query['expires']) < time()
        ) {
            return false;
        }

        // still here? Check sig
        $urlWithoutSignature = $this->removeQuerystringVar($url, 'signature');
        $signature = $this->getSignature($urlWithoutSignature, $secret);

        if ($query['signature'] === $signature) {
            return true;
        }

        return false;
    }

    /**
     * Returns a signature for the given $msg
     * @param string $msg message to generate the signatur efor
     * @param string $secret secret to sign the message with
     * @return string signature generated with secret and message
     */
    protected function getSignature($msg, $secret)
    {
        return hash_hmac('sha256', $msg, $secret);
    }

    /**
     * Remove a query parameter from the $url
     * @param string $url Url to remove the query parameter from
     * @param string $key Query parameter to remove
     * @see http://www.addedbytes.com/blog/code/php-querystring-functions/
     * @return string url with the parameter removed
     */
    protected function removeQuerystringVar($url, $key)
    {
        $anchor = strpos($url, '#') !== false ? substr($url, strpos($url, '#')) : null;
        $url = preg_replace('/(.*)(?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&');
        $url = substr($url, 0, -1);

        return empty($anchor) ? $url : $url . $anchor;
    }
}
