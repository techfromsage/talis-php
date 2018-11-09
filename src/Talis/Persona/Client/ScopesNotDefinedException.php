<?php

namespace Talis\Persona\Client;

use Talis\Persona\Client\ValidationResults;

/**
 * JWT includes a 'scopeCount' rather than a list of scopes.
 */
class ScopesNotDefinedException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg exception message
     */
    public function __construct($msg)
    {
        parent::__construct($msg, ValidationResults::INVALID_TOKEN);
    }
}
