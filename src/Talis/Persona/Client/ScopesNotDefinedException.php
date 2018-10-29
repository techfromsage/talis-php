<?php

namespace Talis\Persona\Client;

/**
 * JWT includes a 'scopeCount' rather than a list of scopes.
 */
public class ScopesNotDefinedException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg exception message
     */
    public function __construct($msg)
    {
        parent::__construct($msg, ValidationResults::InvalidToken);
    }
}
