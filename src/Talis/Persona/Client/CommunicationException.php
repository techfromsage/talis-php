<?php

namespace Talis\Persona\Client;

use Talis\Persona\Client\ValidationResults;

/**
 * Could not communicate with remote target
 */
class CommunicationException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg exception message
     */
    public function __construct($msg)
    {
        parent::__construct($msg, ValidationResults::COMMUNICATION_ISSUE);
    }
}
