<?php
namespace Talis\Persona\Client;

/**
 * Response from the server included a empty body.
 */
class EmptyResponseException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg message
     */
    public function __construct($msg)
    {
        parent::__construct(
            $msg,
            ValidationResults::EmptyResponse
        );
    }
}
