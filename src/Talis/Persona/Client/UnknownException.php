<?php
namespace Talis\Persona\Client;

use Talis\Persona\ValidationResults;

/**
 * A unexpected exception occurred.
 */
class UnknownException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg message
     */
    public function __construct($msg)
    {
        parent::__construct(
            $msg,
            ValidationResults::UNKNOWN_EXCEPTION
        );
    }
}
