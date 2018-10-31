<?php
namespace Talis\Persona\Client;

use Talis\Persona\ValidationResults;

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
            ValidationResults::UNAUTHORISED
        );
    }
}
