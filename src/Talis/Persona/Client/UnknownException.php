<?php
namespace Talis\Persona\Client;

class UnknownException extends InvalidValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::UnknownException
        );
    }
}
