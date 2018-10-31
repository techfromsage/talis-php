<?php
namespace Talis\Persona\Client;

use Talis\Persona\Client\ValidationResults;

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
            ValidationResults::EMPTY_RESPONSE
        );
    }
}
