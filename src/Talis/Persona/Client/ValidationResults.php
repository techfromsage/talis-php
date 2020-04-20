<?php

namespace Talis\Persona\Client;

class ValidationResults
{
    // TODO: oops, these have changed
    const SUCCESS = 0;
    const INVALID_PUBLIC_KEY = 1;
    const INVALID_TOKEN = 2;
    const EMPTY_RESPONSE = 3;
    const UNKNOWN = 4;
    const UNAUTHORISED = 5;
    const INVALID_SIGNATURE = 6;
    const COMMUNICATION_ISSUE = 7;
}
