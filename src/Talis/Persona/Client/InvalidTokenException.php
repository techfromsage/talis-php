<?php
namespace Talis\Persona\Client;

use Talis\Persona\Client\ValidationResults;

/**
 * Either the token is malformed or it has expired.
 */
class InvalidTokenException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg message
     */
    public function __construct($msg)
    {
        parent::__construct(
            $msg,
            ValidationResults::INVALID_TOKEN
        );
    }
}
