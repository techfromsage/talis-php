<?php
namespace Talis\Persona\Client;

/**
 * A unexpected exception occurred.
 */
public class UnknownException extends TokenValidationException
{
    /**
     * Constructor
     * @param string $msg message
     */
    public function __construct($msg)
    {
        parent::__construct(
            $msg,
            ValidationResults::UnknownException
        );
    }
}
