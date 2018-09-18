<?php
namespace Talis\Persona\Client;

class InvalidSignatureException extends InvalidValidationException
{
    public function __construct($msg)
        parent::__construct(
            $msg,
            ValidationResults::InvalidSignature
        );
    }
}
