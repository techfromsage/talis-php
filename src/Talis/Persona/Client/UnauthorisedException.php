<?php
namespace Talis\Persona\Client;

/**
 * Authorisation request failed.
 */
class UnauthorisedException extends TokenValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::Unauthorised
        );
    }
}
