<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xakki\Emailer\ConfigService;

class ConfigServiceTest extends TestCase
{
    /**
     * A bad retry config fails when the config is built, not later on every
     * temporary failure (where it used to turn into a terminal Division by zero).
     *
     * @param array<string, mixed> $retry
     */
    #[DataProvider('invalidRetryConfigs')]
    public function testInvalidRetryConfigFailsAtConstruction(array $retry, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new ConfigService(['retry' => $retry]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidRetryConfigs(): iterable
    {
        yield 'first_delay 0' => [['first_delay' => 0], 'first_delay'];
        yield 'max_attempts 0' => [['max_attempts' => 0], 'max_attempts'];
        yield 'max_delay below first_delay' => [['max_delay' => 60], 'max_delay'];
        yield 'fractional string' => [['first_delay' => '900.5'], 'retry.first_delay must be an integer'];
        yield 'non-numeric string' => [['max_attempts' => 'five'], 'retry.max_attempts must be an integer'];
        yield 'exponent string' => [['max_delay' => '9e4'], 'retry.max_delay must be an integer'];
        yield 'empty string' => [['first_delay' => ''], 'retry.first_delay must be an integer'];
        yield 'float' => [['first_delay' => 900.0], 'retry.first_delay must be an integer'];
        yield 'numeric string out of range' => [['first_delay' => '0'], 'retry.first_delay must be >= 1'];
        yield 'numeric string beyond int' => [['max_delay' => '99999999999999999999'], 'retry.max_delay must be an integer'];
    }

    /**
     * Env-sourced config arrives as strings: integer-valued ones are accepted
     * and stored as real ints (RetrySchedule and the queue code are int-typed).
     */
    public function testIntegerNumericStringsFromEnvAreCastToInt(): void
    {
        $config = new ConfigService(['retry' => ['max_attempts' => '3', 'first_delay' => '600', 'max_delay' => '86400']]);

        self::assertSame(['max_attempts' => 3, 'first_delay' => 600, 'max_delay' => 86400], $config->retry);
    }

    public function testPartialRetryOverrideKeepsTheOtherDefaults(): void
    {
        $config = new ConfigService(['retry' => ['max_attempts' => 3]]);

        self::assertSame(['max_attempts' => 3, 'first_delay' => 900, 'max_delay' => 86400], $config->retry);
    }
}
