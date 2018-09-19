<?php
namespace Talis\Persona\Client;

/**
 * Signature within the JWT does not represent the token.
 */
class InvalidSignatureException extends TokenValidationException
{
    public function __construct($msg)
        parent::__construct(
            $msg,
            ValidationResults::InvalidSignature
        );
    }
}
