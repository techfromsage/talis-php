<?php
namespace Talis\Persona\Client;

/**
 * Authorisation request failed.
 */
class UnauthorisedException extends InvalidValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::Unauthorised
        );
    }
}
