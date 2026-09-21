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
        yield 'string from env' => [['first_delay' => '900'], 'retry.first_delay must be an integer'];
    }

    public function testPartialRetryOverrideKeepsTheOtherDefaults(): void
    {
        $config = new ConfigService(['retry' => ['max_attempts' => 3]]);

        self::assertSame(['max_attempts' => 3, 'first_delay' => 900, 'max_delay' => 86400], $config->retry);
    }
}
