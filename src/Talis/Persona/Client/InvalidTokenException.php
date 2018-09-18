<?php
namespace Talis\Persona\Client;

/**
 * Either the token is malformed or it has expired.
 */
class InvalidTokenException extends InvalidValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::InvalidToken
        );
    }
}
