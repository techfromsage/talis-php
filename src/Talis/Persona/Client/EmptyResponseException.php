<?php
namespace Talis\Persona\Client;

/**
 * Response from the server included a empty body.
 */
class EmptyResponseException extends InvalidValidationException
{
    public function __construct($msg) {
        parent::__construct(
            $msg,
            ValidationResults::EmptyResponse
        );
    }
}
