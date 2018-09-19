<?php
namespace Talis\Persona\Client;

/**
 * A unexpected exception occurred.
 */
class UnknownException extends TokenValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::UnknownException
        );
    }
}
