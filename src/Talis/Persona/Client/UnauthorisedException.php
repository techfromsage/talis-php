<?php
namespace Talis\Persona\Client;

/**
 * Authorisation request failed.
 */
class UnauthorisedException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg message
     */
    public function __construct($msg)
    {
        parent::__construct(
            $msg,
            ValidationResults::Unauthorised
        );
    }
}
