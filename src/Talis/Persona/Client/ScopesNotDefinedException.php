<?php

namespace Talis\Persona\Client;

/**
 * JWT includes a 'scopeCount' rather than a list of scopes.
 */
class ScopesNotDefinedException extends TokenValidationException
{
    public function __construct($msg) {
        parent::__construct($msg, ValidationResults::InvalidToken);
    }
}
