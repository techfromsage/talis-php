<?php

namespace Talis\Critic\Exceptions;

class ErrorResponseException extends \Exception
{
    /**
     * @param string $message optional Critic error message
     * @param string $code optional Critic error code
     * @param \Exception $previous optional the previous exception used for the exception chaining.
     */
    public function __construct($message = '', $code = '', \Exception $previous = null)
    {
        $this->code = $code;
        parent::__construct($message, null, $previous);
    }
}
