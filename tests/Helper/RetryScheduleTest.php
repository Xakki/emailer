<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Helper\RetrySchedule;

/**
 * Pure-math coverage of the geometric backoff formula, independent of the
 * queue/DB machinery. The task spec: max 5 attempts total (first send + 4
 * retries), first interval 900s (the cron tick), last interval exactly 86400s
 * (24h), growing geometrically in between: ~15min -> ~69min -> ~5.2h -> 24h.
 */
class RetryScheduleTest extends TestCase
{
    public function testGeometricScheduleForFiveAttempts(): void
    {
        self::assertSame(900, RetrySchedule::delaySeconds(1, 5, 900, 86400));
        self::assertSame(4121, RetrySchedule::delaySeconds(2, 5, 900, 86400));
        self::assertSame(18869, RetrySchedule::delaySeconds(3, 5, 900, 86400));
        self::assertSame(86400, RetrySchedule::delaySeconds(4, 5, 900, 86400));
    }

    public function testNoAttemptsRemainAfterTheConfiguredMaximum(): void
    {
        self::assertNull(RetrySchedule::delaySeconds(5, 5, 900, 86400));
        // Also guard against ever being called past the max (defensive: a caller
        // bug should not produce a negative/garbage delay instead of terminal).
        self::assertNull(RetrySchedule::delaySeconds(6, 5, 900, 86400));
    }

    public function testMaxAttemptsIsAOneValueChange(): void
    {
        // 2 retries instead of 4 (maxAttempts=3) must not require touching the
        // formula: 2 intervals, first forced to $firstDelay, last forced to
        // $maxDelay — no middle geometric step needed.
        self::assertSame(900, RetrySchedule::delaySeconds(1, 3, 900, 86400));
        self::assertSame(86400, RetrySchedule::delaySeconds(2, 3, 900, 86400));
        self::assertNull(RetrySchedule::delaySeconds(3, 3, 900, 86400));
    }

    /**
     * A schedule that would divide by zero (first_delay 0) or collapse the
     * waits (max_delay < first_delay) is rejected, never computed.
     */
    #[DataProvider('invalidSchedules')]
    public function testInvalidScheduleIsRejected(int $maxAttempts, int $firstDelay, int $maxDelay): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RetrySchedule::delaySeconds(1, $maxAttempts, $firstDelay, $maxDelay);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function invalidSchedules(): iterable
    {
        yield 'first_delay 0 (division by zero)' => [5, 0, 86400];
        yield 'negative first_delay' => [5, -1, 86400];
        yield 'max_delay below first_delay' => [5, 900, 0];
        yield 'no attempts at all' => [0, 900, 86400];
    }
}
