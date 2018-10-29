<?php

namespace Talis\EchoClient;

public class Event implements \JsonSerializable
{
    private $class;
    private $source;
    private $props;
    private $userId;
    private $timestamp;

    /**
     * @param string $class The class of this event
     * @param string $source The source of this event
     * @param array $props A set of any properties that relate to this event
     * @param string $userId The user this event relates to
     * @param string $timestamp When this event was raised, represented as
 *              seconds since the epoch as a string
     */
    public function __construct(
        $class,
        $source,
        array $props = [],
        $userId = null,
        $timestamp = null
    ) {
        if (!defined('ECHO_CLASS_PREFIX')) {
            define('ECHO_CLASS_PREFIX', '');
        }

        if (!is_string($class)) {
            throw new \InvalidArgumentException('`class` must be a string');
        }

        if (!is_string($source)) {
            throw new \InvalidArgumentException('`source` must be a string');
        }

        if (!is_array($props)) {
            throw new \InvalidArgumentException('`props` must be an array');
        }

        if (!is_null($userId) && !is_string($userId)) {
            throw new \InvalidArgumentException('`userId` must be a string');
        }

        if (!is_null($timestamp) && !is_string($timestamp)) {
            throw new \InvalidArgumentException('`timestamp` must be a string');
        }

        $this->class = ECHO_CLASS_PREFIX . $class;
        $this->source = $source;
        $this->props  = $props;
        $this->userId = $userId;
        $this->timestamp = $timestamp;
    }

    /**
     * Get the event data in a json serialization format
     * @return array
     */
    public function jsonSerialize()
    {
        $event = [
            'class' => $this->class,
            'source' => $this->source,
            'props' => $this->props,
        ];

        if (!empty($this->userId)) {
            $event['user'] = $this->userId;
        }

        if (!empty($this->timestamp)) {
            $event['timestamp'] = $this->timestamp;
        }

        return $event;
    }
}
