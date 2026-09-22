<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that keeps every record in memory so tests can assert the
 * exact level / message / context the library emitted.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
