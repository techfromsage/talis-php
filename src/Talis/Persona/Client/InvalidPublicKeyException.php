<?php
namespace Talis\Persona\Client;

use Talis\Persona\ValidationResults;

/**
 * Public key used to validate the JWT is considered invalid.
 */
class InvalidPublicKeyException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg message
     */
    public function __construct($msg)
    {
        parent::__construct(
            $msg,
            ValidationResults::INVALID_PUBLIC_KEY
        );
    }
}
