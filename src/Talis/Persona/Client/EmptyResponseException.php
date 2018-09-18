<?php
namespace Talis\Persona\Client;

class EmptyResponseException extends InvalidValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::EmptyResponse
        );
    }
}
