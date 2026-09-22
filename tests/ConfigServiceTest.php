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
        yield 'negative int' => [['first_delay' => -5], 'retry.first_delay must be >= 1'];
        yield 'negative numeric string' => [['first_delay' => '-5'], 'retry.first_delay must be >= 1'];
        yield 'negative max_attempts string' => [['max_attempts' => '-5'], 'retry.max_attempts must be >= 1'];
        yield 'inner whitespace' => [['first_delay' => '9 00'], 'retry.first_delay must be an integer'];
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

    /**
     * FILTER_VALIDATE_INT trims surrounding whitespace, so a padded env value
     * (" 900", "900 ") is accepted as the integer it spells.
     */
    #[DataProvider('whitespacePaddedValues')]
    public function testWhitespacePaddedNumericStringsAreTrimmed(string $value): void
    {
        $config = new ConfigService(['retry' => ['first_delay' => $value]]);

        self::assertSame(['max_attempts' => 5, 'first_delay' => 900, 'max_delay' => 86400], $config->retry);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function whitespacePaddedValues(): iterable
    {
        yield 'leading space' => [' 900'];
        yield 'trailing space' => ['900 '];
        yield 'tab and newline' => ["\t900\n"];
    }

    public function testPartialRetryOverrideKeepsTheOtherDefaults(): void
    {
        $config = new ConfigService(['retry' => ['max_attempts' => 3]]);

        self::assertSame(['max_attempts' => 3, 'first_delay' => 900, 'max_delay' => 86400], $config->retry);
    }

    public function testSqlLogDefaultsToDebugWithoutParams(): void
    {
        self::assertSame(['level' => 'debug', 'params' => false], (new ConfigService())->sql_log);
    }

    public function testPartialSqlLogOverrideKeepsTheOtherDefault(): void
    {
        $config = new ConfigService(['sql_log' => ['params' => true]]);

        self::assertSame(['level' => 'debug', 'params' => true], $config->sql_log);
    }

    /**
     * @param array<string, mixed> $sqlLog
     */
    #[DataProvider('invalidSqlLogConfigs')]
    public function testInvalidSqlLogConfigFailsAtConstruction(array $sqlLog, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new ConfigService(['sql_log' => $sqlLog]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidSqlLogConfigs(): iterable
    {
        yield 'unknown level' => [['level' => 'verbose'], 'sql_log.level must be one of'];
        yield 'upper-case level' => [['level' => 'INFO'], 'sql_log.level must be one of'];
        yield 'non-string level' => [['level' => 7], 'sql_log.level must be one of'];
        yield 'non-bool string' => [['params' => 'maybe'], 'sql_log.params must be a boolean, string given'];
        yield 'numeric string other than 0/1' => [['params' => '2'], 'sql_log.params must be a boolean, string given'];
        yield 'int' => [['params' => 1], 'sql_log.params must be a boolean, int given'];
        yield 'null' => [['params' => null], 'sql_log.params must be a boolean, null given'];
    }

    /**
     * Env-sourced config arrives as strings: boolean-like ones (per
     * FILTER_VALIDATE_BOOL) are stored as real bools.
     */
    #[DataProvider('sqlLogBoolStrings')]
    public function testSqlLogParamsAcceptsBoolStrings(string $value, bool $expected): void
    {
        $config = new ConfigService(['sql_log' => ['level' => 'info', 'params' => $value]]);

        self::assertSame(['level' => 'info', 'params' => $expected], $config->sql_log);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function sqlLogBoolStrings(): iterable
    {
        yield '"1"' => ['1', true];
        yield '"true"' => ['true', true];
        yield '"0"' => ['0', false];
        yield '"false"' => ['false', false];
        yield '"off"' => ['off', false];
        yield 'padded "true"' => [' true ', true];
        yield 'empty string' => ['', false];
    }
}
