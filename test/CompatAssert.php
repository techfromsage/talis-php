<?php

namespace test;

use PHPUnit\Framework\Assert;

/**
 * Comprises assertions that have changed between since PHPUnit 4,
 * and that, due to incompatible definitions (final, type hints),
 * could not be overridden in TestBase.
 */
class CompatAssert
{
    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     * @return void
     */
    public static function assertStringContainsString($needle, $haystack, $message = '')
    {
        if (method_exists(Assert::class, __FUNCTION__)) {
            Assert::assertStringContainsString($needle, $haystack, $message);
        } else {
            Assert::assertContains($needle, $haystack, $message);
        }
    }

    /**
     * Asserts that a variable is of type array.
     *
     * @param mixed $actual
     * @param string $message
     * @return void
     */
    public static function assertIsArray($actual, $message = '')
    {
        if (method_exists(Assert::class, __FUNCTION__)) {
            Assert::assertIsArray($actual, $message);
        } else {
            Assert::assertInternalType('array', $actual, $message);
        }
    }
}
