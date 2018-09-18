<?php
namespace Talis\Persona\Client;

class InvalidPublicKeyException extends InvalidValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::InvalidPublicKey
        );
    }
}

