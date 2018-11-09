<?php
namespace Talis\Persona\Client;

use Talis\Persona\Client\ValidationResults;

/**
 * Signature within the JWT does not represent the token.
 */
class InvalidSignatureException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg message
     */
    public function __construct($msg)
    {
        parent::__construct(
            $msg,
            ValidationResults::INVALID_SIGNATURE
        );
    }
}
