<?php

namespace test\unit\EchoClient;

use InvalidArgumentException;
use test\TestBase;

/**
 * Unit tests for EchoClient.
 * @runTestsInSeparateProcesses
 */
class EventTest extends TestBase
{
    /**
     * @before
     */
    protected function definePrefix()
    {
        define('ECHO_CLASS_PREFIX', 'test.');
    }

    /**
     * @test Verifies that the constructor rejects bad args
     * @dataProvider badConstructorArgsProvider
     */
    public function testEchoEventValidatesConstructorArgs(
        $class,
        $source,
        $props,
        $userId,
        $timestamp,
        $expectedErrorMessage
    ) {
        $this->setExpectedException(InvalidArgumentException::class, $expectedErrorMessage);
        $e = new \Talis\EchoClient\Event($class, $source, $props, $userId, $timestamp);
    }

    public function badConstructorArgsProvider()
    {
        return [
            [123, 'source', [], 'some-user', '123456789', '`class` must be a string'],
            ['class', 123, [], 'some-user', '123456789', '`source` must be a string'],
            ['class', 'source', [], 123, '123456789', '`userId` must be a string'],
            ['class', 'source', [], 'some-user', 12345, '`timestamp` must be a string'],
        ];
    }

    /**
     * @test Verifies that the event is serialized correctly into json
     */
    public function testEchoEventSerialization()
    {

        $class = 'some-class';
        $source = 'some-source';
        $props = ['foo' => 'bar'];
        $userId = 'some-user';
        $timestamp = '1234567890';

        $expected = json_encode([
            'class' => ECHO_CLASS_PREFIX . $class,
            'source' => $source,
            'props' => $props,
            'user' => $userId,
            'timestamp' => $timestamp,
        ]);

        $event = new \Talis\EchoClient\Event($class, $source, $props, $userId, $timestamp);
        $this->assertEquals($expected, json_encode($event, true));
    }

    /**
     * @test Verifies that the event is serialized correctly into json but does
     * not include a user if no user is specified
     */
    public function testEchoEventSerializationShouldNotIncludeUserIfNotSpecified()
    {

        $class = 'some-class';
        $source = 'some-source';
        $props = ['foo' => 'bar'];
        $timestamp = '1234567890';

        $expected = json_encode([
            'class' => ECHO_CLASS_PREFIX . $class,
            'source' => $source,
            'props' => $props,
            'timestamp' => $timestamp,
        ]);

        $event = new \Talis\EchoClient\Event($class, $source, $props, null, $timestamp);
        $this->assertEquals($expected, json_encode($event, true));
    }

    /**
     * @test Verifies that the event is serialized correctly into json but does
     * not include a timestamp if no timestamp is specified
     */
    public function testEchoEventSerializationShouldNotIncludTimestampIfNotSpecified()
    {

        $class = 'some-class';
        $source = 'some-source';
        $props = ['foo' => 'bar'];
        $userId = 'some-user';

        $expected = json_encode([
            'class' => ECHO_CLASS_PREFIX . $class,
            'source' => $source,
            'props' => $props,
            'user' => $userId,
        ]);

        $event = new \Talis\EchoClient\Event($class, $source, $props, $userId, null);
        $this->assertEquals($expected, json_encode($event, true));
    }
}
