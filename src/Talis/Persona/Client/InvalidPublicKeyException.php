<?php
namespace Talis\Persona\Client;

/**
 * Public key used to validate the JWT is considered invalid.
 */
class InvalidPublicKeyException extends TokenValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::InvalidPublicKey
        );
    }
}

