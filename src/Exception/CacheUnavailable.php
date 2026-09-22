<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception;

/**
 * The cache backend (Redis) could not be reached. Transient: queue processing
 * retries the row with backoff instead of failing it terminally.
 */
class CacheUnavailable extends Exception
{
}
