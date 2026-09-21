<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Integration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use Xakki\Emailer\Controller\Console;
use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Cqrs\Queue\ExecuteQueue;
use Xakki\Emailer\Cqrs\Queue\RepeatQueue;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Exception\Exception as EmailerException;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Mail;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Tests\Support\IntegrationCase;
use Xakki\Emailer\Transports\AbstractTransport;

class QueueProcessingRegressionTest extends IntegrationCase
{
    protected function createConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
            'wrapperClass' => QueueRegressionConnection::class,
        ]);
    }

    public function testSendWithEmptyQueueLeavesNoActiveTransactionAfterOneRollback(): void
    {
        $this->assertEmptyQueueActionClosesTransaction('send');
    }

    public function testReSendWithEmptyQueueLeavesNoActiveTransactionAfterOneRollback(): void
    {
        $this->assertEmptyQueueActionClosesTransaction('reSend');
    }

    /**
     * The primary spec test: walk one row through all 5 attempts (first send +
     * 4 retries) with a controllable clock, assert the wait grows geometrically
     * to exactly 86400s, and assert the row lands in terminal QUEUE_STATUS_ERROR
     * (-20) — never back in TEMP_ERROR (-21) — after the 5th failure.
     */
    public function testGeometricBackoffWalksFiveAttemptsThenBecomesTerminal(): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, Model\Queue::QUEUE_STATUS_TEMP_ERROR);

        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        ManualClock::$now = $now;

        // Attempt 1 (the first send, via ExecuteQueue): fails, schedules retry #1.
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedExecuteQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1);
        $retryAt1 = $this->assertRetryAtDelta($queue->id, $now, 900);

        // Not yet due: RepeatQueue must not select it one second early. A plain
        // try/catch (not expectException()) — expectException() would terminate
        // this whole test method at this point instead of letting the walk continue.
        ManualClock::$now = $retryAt1->modify('-1 second');
        $this->assertRepeatQueueEmpty();

        // Attempt 2: due now, fails again, schedules retry #2.
        ManualClock::$now = $retryAt1;
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 2);
        $retryAt2 = $this->assertRetryAtDelta($queue->id, $retryAt1, 4121);

        // Attempt 3.
        ManualClock::$now = $retryAt2;
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 3);
        $retryAt3 = $this->assertRetryAtDelta($queue->id, $retryAt2, 18869);

        // Attempt 4: the last interval must be exactly 86400s (24h) — no float drift.
        ManualClock::$now = $retryAt3;
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 4);
        $retryAt4 = $this->assertRetryAtDelta($queue->id, $retryAt3, 86400);

        // Attempt 5 (the last allowed attempt): fails, attempts exhausted ->
        // terminal QUEUE_STATUS_ERROR. Must NOT loop back to TEMP_ERROR.
        ManualClock::$now = $retryAt4;
        self::assertSame(Model\Queue::QUEUE_STATUS_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_ERROR, 5);

        // Terminal: RepeatQueue (status=TEMP_ERROR only) must never pick it up again.
        ManualClock::$now = $retryAt4->modify('+10 years');
        $this->assertRepeatQueueEmpty();
    }

    #[DataProvider('backlogRowsThatMustNeverBeAutoRetried')]
    public function testRepeatQueueNeverSelectsLegacyOrNotYetDueRows(
        int $status,
        int $retry,
        ?string $retryAt,
    ): void {
        $this->insertQueue($status, $retry, $retryAt);

        ManualClock::$now = new \DateTimeImmutable('2026-09-21 12:00:00');
        $this->assertRepeatQueueEmpty();
    }

    /**
     * @return iterable<string, array{int, int, string|null}>
     */
    public static function backlogRowsThatMustNeverBeAutoRetried(): iterable
    {
        // The actual shape of the 81-row historical backlog: retry=0, never scheduled.
        yield 'legacy backlog: retry=0, never scheduled' => [Model\Queue::QUEUE_STATUS_TEMP_ERROR, 0, null];
        // Defensive: the guard is retry_at IS NULL, not retry=0 — a row that somehow
        // has a nonzero retry count but was never scheduled must still be excluded.
        yield 'legacy-shaped: retry>0 but never scheduled' => [Model\Queue::QUEUE_STATUS_TEMP_ERROR, 3, null];
        // Scheduled, but the wait has not elapsed yet.
        yield 'scheduled but not yet due' => [Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1, '2026-09-21 12:00:01'];
        // Terminal status must never be reselected even if it carried a retry_at.
        yield 'terminal error, must never be reselected' => [Model\Queue::QUEUE_STATUS_ERROR, 5, '2026-09-21 11:00:00'];
        // Other REPEATABLE_STATUS-era codes (quota/spam) are out of scope for the
        // automatic backoff loop — only TEMP_ERROR is auto-retried.
        yield 'quota error, not auto-retried' => [Model\Queue::QUEUE_STATUS_QUOTA, 0, '2026-09-21 11:00:00'];
    }

    #[DataProvider('terminalFailures')]
    public function testThrowableFromQueueProcessingBecomesTerminalErrorWithoutRetryChange(string $failure): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 7, 0, $failure);

        self::assertSame(Model\Queue::QUEUE_STATUS_ERROR, (new ExecuteQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_ERROR, 7);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function terminalFailures(): iterable
    {
        yield 'authentication' => ['authentication'];
        yield 'configuration' => ['configuration'];
        yield 'rendering' => ['rendering'];
        yield 'unknown throwable' => ['unknown'];
    }

    private function assertEmptyQueueActionClosesTransaction(string $action): void
    {
        $connection = $this->getQueueRegressionConnection();
        $console = new Console($this->emailer);

        self::assertSame("Statuses: array (\n)", $console->{$action}());
        self::assertSame(1, $connection->beginTransactionCalls);
        self::assertSame(1, $connection->rollBackCalls);
        self::assertFalse($connection->isTransactionActive());
    }

    private function getQueueRegressionConnection(): QueueRegressionConnection
    {
        self::assertInstanceOf(QueueRegressionConnection::class, $this->db);
        return $this->db;
    }

    /**
     * Asserts RepeatQueue finds nothing at the current ManualClock::$now. Uses a
     * plain try/catch rather than expectException() ON PURPOSE: expectException()
     * terminates the whole test method the instant the exception is thrown, which
     * would silently skip the rest of a multi-step walk (a real bug caught while
     * writing this test — an early expectException() call made every assertion
     * after it dead code, and the test still reported green).
     */
    private function assertRepeatQueueEmpty(): void
    {
        try {
            new ClockedRepeatQueue($this->emailer);
            self::fail('RepeatQueue unexpectedly found a row to retry.');
        } catch (DataNotFound $e) {
            self::assertSame(0, $e->httpCode);
        }
    }

    private function createQueue(int $status, int $retry, int $result, string $failure = ''): Model\Queue
    {
        $project = (new Cqrs\Project\CreateProject('Demo', [
            Model\Template::NAME_HOST => 'demo.test',
            Model\Template::NAME_ROUTE => 'rdr',
            Model\Template::NAME_URL_LOGO => __DIR__ . '/../logo.png',
        ]))->handler();
        $notify = $project->createNotify('News');
        $wrapper = $project->createTplWrapper('wrap', '{{content}}');
        $content = $project->createTplContent('cont', 'Hi');
        $campaign = $project->createCampaign('Subject', $wrapper, $content, $notify, []);

        Repository\Domain::insert(['name' => 'example.com']);
        Repository\Email::insert([
            'email' => 'foo@example.com',
            'name' => 'Foo',
            'project_id' => $project->id,
            'domain_id' => 1,
        ]);

        $transport = new QueueRegressionTransport($this->emailer);
        $transport->result = $result;
        $transport->failure = $failure;
        Repository\Transport::insert([
            'params' => (string) $transport,
            'project_id' => $project->id,
            'limit_day' => 0,
            'cnt_day' => 0,
        ]);

        $this->emailer->getNewSender($project->id, $campaign->id)
            ->send((new Mail())->setEmail('foo@example.com')->setEmailName('Foo'));
        $queue = Model\Queue::findOne(['status' => Model\Queue::QUEUE_STATUS_NEW]);
        $queue->status = $status;
        $queue->retry = $retry;
        $queue->update(['status', 'retry']);

        return $queue;
    }

    private function insertQueue(int $status, int $retry, ?string $retryAt = null): void
    {
        Repository\Queue::insert([
            'status' => $status,
            'retry' => $retry,
            'retry_at' => $retryAt,
            'project_id' => 1,
            'campaign_id' => 1,
            'notify_id' => 1,
            'email_id' => 1,
        ]);
    }

    private function assertQueueState(int $queueId, int $status, int $retry): void
    {
        $row = Repository\Queue::findOne(['id' => $queueId]);
        self::assertSame($status, $row['status']);
        self::assertSame($retry, $row['retry']);
    }

    /**
     * Asserts queue.retry_at == $from + $expectedSeconds, and returns it (so the
     * caller can chain the next step's "now" off the real stored value rather
     * than off a value recomputed in the test).
     */
    private function assertRetryAtDelta(int $queueId, \DateTimeImmutable $from, int $expectedSeconds): \DateTimeImmutable
    {
        $row = Repository\Queue::findOne(['id' => $queueId]);
        self::assertNotNull($row['retry_at']);
        $retryAt = new \DateTimeImmutable($row['retry_at']);
        self::assertSame($expectedSeconds, $retryAt->getTimestamp() - $from->getTimestamp());
        return $retryAt;
    }
}

final class ManualClock
{
    public static \DateTimeImmutable $now;
}

final class ClockedExecuteQueue extends ExecuteQueue
{
    protected function now(): \DateTimeImmutable
    {
        return ManualClock::$now;
    }
}

final class ClockedRepeatQueue extends RepeatQueue
{
    protected function now(): \DateTimeImmutable
    {
        return ManualClock::$now;
    }
}

final class QueueRegressionConnection extends Connection
{
    public int $beginTransactionCalls = 0;
    public int $rollBackCalls = 0;

    public function beginTransaction(): void
    {
        $this->beginTransactionCalls++;
        parent::beginTransaction();
    }

    public function rollBack(): void
    {
        $this->rollBackCalls++;
        parent::rollBack();
    }

    /**
     * SQLite does not support MySQL's IF function or FOR UPDATE. This keeps the
     * queue regression tests on the production repository path without a live
     * MySQL database.
     *
     * @param list<mixed>|array<string, mixed> $params
     * @param array<int<0, max>|string, ArrayParameterType|ParameterType|Type|string> $types
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $query = str_replace(' FOR UPDATE', '', $query);
        $query = str_replace('IF(', 'IIF(', $query);
        return parent::fetchAssociative($query, $params, $types);
    }
}

final class QueueRegressionTransport extends AbstractTransport
{
    public int $result = 0;
    public string $failure = '';

    public function send(Model\Queue $queue): int
    {
        return match ($this->failure) {
            'authentication' => throw new Validation('Authentication failed', Validation::CODE_SMTP),
            'configuration' => throw new EmailerException('Configuration failed'),
            'rendering' => $this->render($queue),
            'unknown' => throw new \RuntimeException('Unexpected failure'),
            default => $this->result,
        };
    }

    public function validate(): void
    {
    }

    private function render(Model\Queue $queue): int
    {
        $queue->getBody();
        throw new Validation('Rendering failed');
    }
}
