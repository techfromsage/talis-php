<?php

namespace Talis\Persona\Client;

class ScopesNotDefinedException extends InvalidValidationException
{
    public function __construct($msg) {
        parent::__construct($msg, ValidationResults::InvalidToken);
    }
}
