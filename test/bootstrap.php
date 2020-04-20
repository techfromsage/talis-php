<?php

if (!defined('APPROOT')) {
    define('APPROOT', dirname(__DIR__));
}

require APPROOT . '/vendor/autoload.php';

date_default_timezone_set('Europe/London');

/**
 * Retrieve environment variable, else return a default
 * @param string $name name of environment value
 * @param string $default default to return
 * @return string
 */
function envvalue($name, $default)
{
    $value = getenv($name);
    return $value == false ? $default : $value;
}
