<?php

declare(strict_types=1);

namespace Xakki\Emailer\Helper;

/**
 * Geometric backoff schedule for queue retries.
 *
 * With $maxAttempts total attempts (the first send plus retries), there are
 * ($maxAttempts - 1) waiting intervals between attempts. The first interval is
 * exactly $firstDelay seconds and the last is exactly $maxDelay seconds; the
 * intervals in between grow by a constant ratio (geometric progression), so
 * changing any of the three parameters is a one-value config change.
 */
final class RetrySchedule
{
    /**
     * @param int $attemptsDone Number of attempts already made (Queue::$retry after
     *     the failed attempt was counted), >= 1.
     * @param int $maxAttempts Total attempts allowed (first send + retries), >= 1.
     * @param int $firstDelay Seconds to wait before the 2nd attempt.
     * @param int $maxDelay Seconds to wait before the last attempt.
     * @return int|null Seconds to wait before the next attempt, or null when
     *     $attemptsDone has reached $maxAttempts (no attempts remain — terminal).
     */
    public static function delaySeconds(int $attemptsDone, int $maxAttempts, int $firstDelay, int $maxDelay): ?int
    {
        if ($attemptsDone >= $maxAttempts) {
            return null;
        }

        $intervals = $maxAttempts - 1;
        if ($intervals <= 1) {
            return $firstDelay;
        }

        // Force the last interval to be exactly $maxDelay — deriving it from the
        // ratio too would leave it exposed to float rounding drift.
        if ($attemptsDone >= $intervals) {
            return $maxDelay;
        }

        $ratio = ($maxDelay / $firstDelay) ** (1 / ($intervals - 1));
        return (int) round($firstDelay * ($ratio ** ($attemptsDone - 1)));
    }
}
