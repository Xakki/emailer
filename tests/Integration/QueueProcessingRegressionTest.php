<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Integration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoDriverException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\AbstractLogger;
use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Controller\Console;
use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Cqrs\Queue\ExecuteQueue;
use Xakki\Emailer\Cqrs\Queue\RepeatQueue;
use Xakki\Emailer\Exception\CacheUnavailable;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Exception\Exception as EmailerException;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Mail;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Tests\Support\IntegrationCase;
use Xakki\Emailer\Tests\Support\TestEmailer;
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

    protected function setUp(): void
    {
        parent::setUp();
        QueueRegressionTransport::$deliveries = 0;
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

    /**
     * SMTP authentication failures (classified by the transport's own
     * getSmtpErrorStatus(), not a thrown exception) must enter the same
     * geometric backoff as any other temporary failure instead of going
     * straight to terminal on attempt 1 (a credentials outage must not turn
     * the whole backlog into terminal errors).
     * Walks the same 5-attempt schedule as the generic test above, but via
     * a transport whose send() returns getSmtpErrorStatus('Could not
     * authenticate') on every attempt.
     */
    public function testSmtpAuthenticationFailureEntersBackoffAndWalksToTerminal(): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, 0, 'smtpAuthentication');

        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        ManualClock::$now = $now;

        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedExecuteQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1);
        $retryAt1 = $this->assertRetryAtDelta($queue->id, $now, 900);

        ManualClock::$now = $retryAt1;
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 2);
        $retryAt2 = $this->assertRetryAtDelta($queue->id, $retryAt1, 4121);

        ManualClock::$now = $retryAt2;
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 3);
        $retryAt3 = $this->assertRetryAtDelta($queue->id, $retryAt2, 18869);

        ManualClock::$now = $retryAt3;
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 4);
        $retryAt4 = $this->assertRetryAtDelta($queue->id, $retryAt3, 86400);

        // Attempt 5: exhausted -> terminal QUEUE_STATUS_ERROR, same as any other
        // exhausted TEMP_ERROR backoff. Never a bare terminal on attempt 1.
        ManualClock::$now = $retryAt4;
        self::assertSame(Model\Queue::QUEUE_STATUS_ERROR, (new ClockedRepeatQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_ERROR, 5);
    }

    /**
     * SMTP I/O runs outside any DB transaction: the row is claimed (RUN,
     * committed) before send(), so a DB failure after the server accepted the
     * message can no longer roll the row back into the queue for a second
     * delivery. The price is at-most-once: the row is stranded in RUN.
     */
    #[DataProvider('queueActions')]
    public function testDeliveredMailIsNotSentAgainWhenTheResultWriteFails(string $action, int $status): void
    {
        $queue = $this->createQueue($status, 1, 0, 'countDelivery');
        $this->makeDue($queue);
        $this->db->executeStatement(
            'CREATE TRIGGER fail_result BEFORE UPDATE OF status ON queue WHEN NEW.status IN (2, -20) '
            . "BEGIN SELECT RAISE(ABORT, 'simulated DB outage after SMTP accepted the message'); END"
        );

        $first = (new Console($this->emailer))->{$action}();
        self::assertSame(1, QueueRegressionTransport::$deliveries, $first);
        self::assertStringContainsString('simulated DB outage', $first);
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_RUN, 1);

        $this->db->executeStatement('DROP TRIGGER fail_result');
        $second = (new Console($this->emailer))->{$action}();

        self::assertSame(1, QueueRegressionTransport::$deliveries, "run1: $first\nrun2: $second");
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_RUN, 1);
    }

    /**
     * Only the bookkeeping after a delivery failed (campaign counter): the
     * row is still recorded as sent, never re-queued and never left in RUN.
     */
    public function testDeliveredMailIsRecordedAsSentWhenOnlyTheCountersFail(): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, 0, 'countDelivery');
        $this->db->executeStatement(
            'CREATE TRIGGER fail_counter BEFORE UPDATE OF cnt_send ON campaign '
            . "BEGIN SELECT RAISE(ABORT, 'simulated lock wait on the campaign counter'); END"
        );

        $out = (new Console($this->emailer))->send();

        self::assertSame("Statuses: array (\n  'success' => 1,\n)", $out);
        self::assertSame(1, QueueRegressionTransport::$deliveries);
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_SUCCESS, 0);
    }

    /**
     * A failed COMMIT already ends the transaction in DBAL 4, so an unguarded
     * rollBack() would throw NoActiveTransaction and hide the real error.
     * Covers both short transactions: the claim (-> RUN) and the result write.
     */
    #[DataProvider('commitFailures')]
    public function testCommitFailureIsReportedNotMaskedByRollBack(int $failingStatus): void
    {
        $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, 0);
        $this->db->executeStatement('PRAGMA foreign_keys = ON');
        $this->db->executeStatement('CREATE TABLE fk_parent (id INTEGER PRIMARY KEY)');
        $this->db->executeStatement(
            'CREATE TABLE fk_child (pid INTEGER REFERENCES fk_parent(id) DEFERRABLE INITIALLY DEFERRED)'
        );
        $this->db->executeStatement(
            'CREATE TRIGGER commit_fails AFTER UPDATE OF status ON queue WHEN NEW.status = ' . $failingStatus
            . ' BEGIN INSERT INTO fk_child (pid) VALUES (999); END'
        );

        $out = (new Console($this->emailer))->send();

        self::assertStringContainsString('FOREIGN KEY', $out);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function commitFailures(): iterable
    {
        yield 'claim transaction' => [Model\Queue::QUEUE_STATUS_RUN];
        yield 'result transaction' => [Model\Queue::QUEUE_STATUS_SUCCESS];
    }

    /**
     * Every status a row can end in has a title (SPAM was missing: Undefined
     * array key -4 on the reSend path), and an unmapped one falls back.
     */
    #[DataProvider('resultTitles')]
    public function testConsoleReportsATitleForEveryResultStatus(int $result, string $title): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1, $result);
        $this->makeDue($queue);

        self::assertSame(
            "Statuses: array (\n  '" . $title . "' => 1,\n)",
            (new Console($this->emailer))->reSend(),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function resultTitles(): iterable
    {
        yield 'spam' => [Model\Queue::QUEUE_STATUS_SPAM, 'spam'];
        yield 'unmapped status' => [7, 'unknown'];
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function queueActions(): iterable
    {
        yield 'send (NEW row)' => ['send', Model\Queue::QUEUE_STATUS_NEW];
        yield 'reSend (due TEMP_ERROR row)' => ['reSend', Model\Queue::QUEUE_STATUS_TEMP_ERROR];
    }

    /**
     * SMTP authentication failure = the transport is down, not the message: the
     * rest of that transport's due rows are not attempted in this run (one AUTH
     * attempt per transport per tick, not one per row), they keep their state,
     * and the loop terminates instead of re-selecting them.
     */
    public function testReSendPausesATransportAfterAnAuthenticationFailure(): void
    {
        $campaign = $this->createProjectWithTransports('Down', ['example.com' => 'smtpAuthFailCounted']);
        $ids = [];
        foreach (['a1', 'a2', 'a3'] as $user) {
            $ids[] = $this->enqueue($campaign, $user . '@example.com', Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1, '2000-01-01 00:00:00');
        }

        $out = (new Console($this->emailer))->reSend(10);

        self::assertSame(1, QueueRegressionTransport::$deliveries, 'SMTP AUTH attempts in one reSend(10); ' . $out);
        self::assertSame("Statuses: array (\n  'temporary error' => 1,\n)", $out);
        $this->assertQueueState($ids[0], Model\Queue::QUEUE_STATUS_TEMP_ERROR, 2);
        self::assertNotSame('2000-01-01 00:00:00', Repository\Queue::findOne(['id' => $ids[0]])['retry_at']);
        foreach ([$ids[1], $ids[2]] as $id) {
            $this->assertQueueState($id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1);
            self::assertSame('2000-01-01 00:00:00', Repository\Queue::findOne(['id' => $id])['retry_at']);
        }
    }

    /**
     * Rows of other transports continue — both another project's transport and
     * another transport of the same project (domain-specific routing).
     */
    #[DataProvider('pausedTransportLayouts')]
    public function testSendPausesOnlyTheTransportThatFailedAuthentication(bool $sameProject): void
    {
        $down = $this->createProjectWithTransports('Down', $sameProject
            ? ['example.com' => 'smtpAuthFailCounted', 'other.test' => 'countDelivery']
            : ['example.com' => 'smtpAuthFailCounted']);
        $up = $sameProject ? $down : $this->createProjectWithTransports('Up', ['other.test' => 'countDelivery']);

        $pausedIds = [];
        $pausedIds[] = $this->enqueue($down, 'a1@example.com', Model\Queue::QUEUE_STATUS_NEW);
        $pausedIds[] = $this->enqueue($down, 'a2@example.com', Model\Queue::QUEUE_STATUS_NEW);
        $sentIds = [$this->enqueue($up, 'b1@other.test', Model\Queue::QUEUE_STATUS_NEW)];
        $pausedIds[] = $this->enqueue($down, 'a3@example.com', Model\Queue::QUEUE_STATUS_NEW);
        $sentIds[] = $this->enqueue($up, 'b2@other.test', Model\Queue::QUEUE_STATUS_NEW);

        $out = (new Console($this->emailer))->send(10);

        self::assertSame("Statuses: array (\n  'temporary error' => 1,\n  'success' => 2,\n)", $out);
        self::assertSame(3, QueueRegressionTransport::$deliveries, 'one AUTH attempt + two deliveries');
        $this->assertQueueState($pausedIds[0], Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1);
        foreach ([$pausedIds[1], $pausedIds[2]] as $id) {
            $this->assertQueueState($id, Model\Queue::QUEUE_STATUS_NEW, 0);
            self::assertNull(Repository\Queue::findOne(['id' => $id])['retry_at']);
        }
        foreach ($sentIds as $id) {
            $this->assertQueueState($id, Model\Queue::QUEUE_STATUS_SUCCESS, 0);
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function pausedTransportLayouts(): iterable
    {
        yield 'other project' => [false];
        yield 'same project, other transport' => [true];
    }

    /**
     * A paused transport's backlog is excluded by the selection SQL itself: it
     * costs no extra FOR UPDATE selection per row (the run used to lock, skip
     * and commit every one of them before reaching a live row). Two routings:
     * the paused transport bound to the recipient domain, and the paused one as
     * the project default (domain_id NULL, lowest id) next to a domain-bound
     * live one — the tie at rank 0 must resolve exactly as Transport::find().
     */
    #[DataProvider('pausedBacklogs')]
    public function testPausedTransportBacklogCostsNoSelectionPerRow(string $action, string $pausedRoute, int $backlog): void
    {
        $campaign = $this->createProjectWithTransports('Down', [
            $pausedRoute => 'smtpAuthFailCounted',
            'other.test' => 'countDelivery',
        ]);
        [$status, $retry, $retryAt] = $action === 'send'
            ? [Model\Queue::QUEUE_STATUS_NEW, 0, null]
            : [Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1, '2000-01-01 00:00:00'];
        for ($i = 0; $i < $backlog; $i++) {
            $this->enqueue($campaign, 'a' . $i . '@example.com', $status, $retry, $retryAt);
        }
        $liveId = $this->enqueue($campaign, 'b1@other.test', $status, $retry, $retryAt);
        $connection = $this->getQueueRegressionConnection();
        $connection->forUpdateSelects = 0;

        $out = (new Console($this->emailer))->{$action}(2);

        self::assertSame(2, $connection->forUpdateSelects, 'one FOR UPDATE selection per processed row; ' . $out);
        self::assertSame("Statuses: array (\n  'temporary error' => 1,\n  'success' => 1,\n)", $out);
        self::assertSame(2, QueueRegressionTransport::$deliveries, 'one AUTH attempt + one delivery');
        $this->assertQueueState($liveId, Model\Queue::QUEUE_STATUS_SUCCESS, $retry);
        $kept = $retryAt === null
            ? $this->db->fetchOne('SELECT COUNT(*) FROM queue WHERE status = ? AND retry = ? AND retry_at IS NULL', [$status, $retry])
            : $this->db->fetchOne('SELECT COUNT(*) FROM queue WHERE status = ? AND retry = ? AND retry_at = ?', [$status, $retry, $retryAt]);
        self::assertSame($backlog - 1, (int) $kept, 'the rest of the paused backlog keeps its state');
    }

    /**
     * Backstop: if the SQL routing ever disagreed with PHP's (the exclusion is
     * disabled here to simulate it), each row still resolving to the paused
     * transport is released unchanged and skipped by id — the run terminates.
     */
    public function testRowsTheSelectionFailsToExcludeAreSkippedAndTheRunTerminates(): void
    {
        $campaign = $this->createProjectWithTransports('Down', [
            'example.com' => 'smtpAuthFailCounted',
            'other.test' => 'countDelivery',
        ]);
        $pausedIds = [];
        foreach (['a1', 'a2', 'a3'] as $user) {
            $pausedIds[] = $this->enqueue($campaign, $user . '@example.com', Model\Queue::QUEUE_STATUS_NEW);
        }
        $liveId = $this->enqueue($campaign, 'b1@other.test', Model\Queue::QUEUE_STATUS_NEW);
        $connection = $this->getQueueRegressionConnection();
        $connection->ignoreTransportExclusion = true;
        $connection->forUpdateSelects = 0;

        $out = (new Console($this->emailer))->send(10);

        self::assertSame("Statuses: array (\n  'temporary error' => 1,\n  'success' => 1,\n)", $out);
        self::assertSame(5, $connection->forUpdateSelects, 'a1, a2 + a3 skipped, b1, then empty');
        $this->assertQueueState($pausedIds[0], Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1);
        foreach ([$pausedIds[1], $pausedIds[2]] as $id) {
            $this->assertQueueState($id, Model\Queue::QUEUE_STATUS_NEW, 0);
        }
        $this->assertQueueState($liveId, Model\Queue::QUEUE_STATUS_SUCCESS, 0);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function pausedBacklogs(): iterable
    {
        foreach (['send', 'reSend'] as $action) {
            foreach (['domain' => 'example.com', 'default' => '*'] as $route => $domain) {
                foreach ([20, 200] as $backlog) {
                    yield $action . ', ' . $route . ' transport paused, ' . $backlog . ' rows' => [$action, $domain, $backlog];
                }
            }
        }
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

    /**
     * Transient infrastructure failures that arrive as exceptions (Redis down,
     * DB deadlock / lock wait / lost connection) get the same geometric backoff
     * as a TEMP_ERROR delivery result instead of a terminal ERROR on attempt 1.
     */
    #[DataProvider('transientFailures')]
    public function testTransientExceptionEntersBackoff(string $failure, string $message): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, 0, $failure);
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        ManualClock::$now = $now;

        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, (new ClockedExecuteQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_TEMP_ERROR, 1);
        $this->assertRetryAtDelta($queue->id, $now, 900);
        self::assertStringContainsString($message, (string) Repository\QueueData::findOne(['id' => $queue->id])['last_error']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function transientFailures(): iterable
    {
        yield 'Redis extension error' => ['redis', 'Connection refused'];
        yield 'Redis connect failure (cache layer)' => ['cacheUnavailable', 'Cant connect to Redis'];
        yield 'DB deadlock' => ['deadlock', 'Deadlock found'];
        yield 'DB lock wait timeout' => ['lockWait', 'Lock wait timeout'];
        yield 'DB connection lost' => ['connectionLost', 'server has gone away'];
    }

    public function testTransientExceptionOnTheLastAttemptBecomesTerminal(): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 4, 0, 'redis');

        self::assertSame(Model\Queue::QUEUE_STATUS_ERROR, (new ExecuteQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_ERROR, 5);
    }

    /**
     * The DB is gone for writes too: the outcome cannot be stored, the row
     * stays RUN (claimed), and the original failure — not the secondary write
     * error — is what surfaces and stops the tick.
     */
    public function testTransientExceptionWhoseOutcomeCannotBeStoredSurfacesTheOriginalError(): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, 0, 'connectionLost');
        $this->db->executeStatement(
            'CREATE TRIGGER db_gone BEFORE UPDATE OF status ON queue WHEN NEW.status IN (-20, -21) '
            . "BEGIN SELECT RAISE(ABORT, 'secondary write failure'); END"
        );

        $out = (new Console($this->emailer))->send();

        self::assertStringContainsString('server has gone away', $out);
        self::assertStringNotContainsString('secondary write failure', $out);
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_RUN, 0);
    }

    /**
     * One start line and one outcome line per row; the outcome keeps the
     * searchable 'Send queue' message and carries the stored result.
     */
    public function testHandlerLogsOneStartLineAndOneOutcomeLine(): void
    {
        $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, Model\Queue::QUEUE_STATUS_TEMP_ERROR);
        $logger = new RecordingLogger();
        $emailer = new TestEmailer(new ConfigService(['db' => ['password' => 'x']]), $logger);
        $emailer->setDb($this->db);
        ManualClock::$now = new \DateTimeImmutable('2026-01-01 00:00:00');

        (new ClockedExecuteQueue($emailer))->handler();

        $lines = array_values(array_filter(
            $logger->records,
            static fn(array $r): bool => in_array($r['message'], ['Run queue', 'Send queue'], true),
        ));
        self::assertSame(
            [['debug', 'Run queue'], ['info', 'Send queue']],
            array_map(static fn(array $r): array => [$r['level'], $r['message']], $lines),
        );
        self::assertSame(Model\Queue::QUEUE_STATUS_TEMP_ERROR, $lines[1]['context']['status']);
        self::assertSame(1, $lines[1]['context']['retry']);
        self::assertSame('2026-01-01 00:15:00', $lines[1]['context']['retry_at']);
    }

    /**
     * Deploying the code before the queue.retry_at migration is unsupported
     * (README "Upgrading"): a temporary failure cannot be scheduled, so it must
     * fail loudly — terminal ERROR, retry unchanged, the schema error in
     * last_error — and never count as transient (a missing column does not heal
     * by retrying). Intentional contract, pinned here.
     */
    public function testTemporaryFailureWithoutTheRetryAtMigrationIsTerminalAndDiagnosable(): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 0, Model\Queue::QUEUE_STATUS_TEMP_ERROR);
        $this->db->executeStatement('ALTER TABLE queue DROP COLUMN retry_at');

        self::assertSame(Model\Queue::QUEUE_STATUS_ERROR, (new ExecuteQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_ERROR, 0);
        self::assertStringContainsString(
            'retry_at',
            (string) Repository\QueueData::findOne(['id' => $queue->id])['last_error'],
        );
    }

    #[DataProvider('terminalFailures')]
    public function testThrowableFromQueueProcessingBecomesTerminalErrorWithoutRetryChange(string $failure): void
    {
        $queue = $this->createQueue(Model\Queue::QUEUE_STATUS_NEW, 7, 0, $failure);

        self::assertSame(Model\Queue::QUEUE_STATUS_ERROR, (new ExecuteQueue($this->emailer))->handler());
        $this->assertQueueState($queue->id, Model\Queue::QUEUE_STATUS_ERROR, 7);
    }

    /**
     * NOT the SMTP-auth-failure case: this is a Validation exception thrown by
     * (escaping) the transport, caught by AbstractQueue's outer catch(\Throwable)
     * safety net, which is terminal for everything outside its transient
     * allowlist (see transientFailures()). Real SMTP auth failures
     * are caught inside Smtp::send() and classified by getSmtpErrorStatus() —
     * see SmtpSendTest and testSmtpAuthenticationFailureEntersBackoffAndWalksToTerminal
     * above, which now go through backoff instead.
     *
     * @return iterable<string, array{string}>
     */
    public static function terminalFailures(): iterable
    {
        yield 'transport throws Validation (authentication)' => ['authentication'];
        yield 'transport throws Validation (configuration)' => ['configuration'];
        yield 'transport throws Validation (rendering)' => ['rendering'];
        yield 'transport throws unknown throwable' => ['unknown'];
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

    /**
     * One project with one domain-routed transport per entry (domain => failure
     * mode), in order; '*' adds a transport bound to no domain.
     *
     * @param array<string, string> $transports
     */
    private function createProjectWithTransports(string $name, array $transports): Model\Campaign
    {
        $project = (new Cqrs\Project\CreateProject($name, [
            Model\Template::NAME_HOST => 'demo.test',
            Model\Template::NAME_ROUTE => 'rdr',
            Model\Template::NAME_URL_LOGO => __DIR__ . '/../logo.png',
        ]))->handler();
        $campaign = $project->createCampaign(
            'Subject',
            $project->createTplWrapper('wrap', '{{content}}'),
            $project->createTplContent('cont', 'Hi'),
            $project->createNotify('News'),
            [],
        );
        foreach ($transports as $domain => $failure) {
            $transport = new QueueRegressionTransport($this->emailer);
            $transport->failure = $failure;
            Repository\Transport::insert([
                'params' => (string) $transport,
                'project_id' => $project->id,
                'domain_id' => $domain === '*' ? null : $this->domainId($domain),
                'limit_day' => 0,
                'cnt_day' => 0,
            ]);
        }
        return $campaign;
    }

    private function domainId(string $domain): int
    {
        return Repository\Domain::findId(['name' => $domain]) ?: Repository\Domain::insert(['name' => $domain]);
    }

    private function enqueue(Model\Campaign $campaign, string $email, int $status, int $retry = 0, ?string $retryAt = null): int
    {
        Repository\Email::insert([
            'email' => $email,
            'name' => 'Foo',
            'project_id' => $campaign->project_id,
            'domain_id' => $this->domainId(explode('@', $email)[1]),
        ]);
        $this->emailer->getNewSender($campaign->project_id, $campaign->id)
            ->send((new Mail())->setEmail($email)->setEmailName('Foo'));
        $queue = Model\Queue::findOne(['status' => Model\Queue::QUEUE_STATUS_NEW, 'retry' => 0, 'email_id' => Repository\Email::findId(['email' => $email])]);
        $queue->status = $status;
        $queue->retry = $retry;
        $queue->retry_at = $retryAt;
        $queue->update(['status', 'retry', 'retry_at']);
        return $queue->id;
    }

    private function makeDue(Model\Queue $queue): void
    {
        $queue->retry_at = '2000-01-01 00:00:00';
        $queue->update(['retry_at']);
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
    public int $forUpdateSelects = 0;
    public bool $ignoreTransportExclusion = false;

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
        if (str_contains($query, ' FOR UPDATE')) {
            $this->forUpdateSelects++;
        }
        $query = str_replace(' FOR UPDATE', '', $query);
        $query = str_replace('IF(', 'IIF(', $query);
        if ($this->ignoreTransportExclusion) {
            $query = str_replace('NOT IN (:skip_transport_ids)', 'NOT IN (:skip_transport_ids) OR 1 = 1', $query);
        }
        return parent::fetchAssociative($query, $params, $types);
    }
}

final class QueueRegressionTransport extends AbstractTransport
{
    public static int $deliveries = 0;
    public int $result = 0;
    public string $failure = '';

    public function send(Model\Queue $queue): int
    {
        return match ($this->failure) {
            'authentication' => throw new Validation('Authentication failed', Validation::CODE_SMTP),
            'configuration' => throw new EmailerException('Configuration failed'),
            'rendering' => $this->render($queue),
            'unknown' => throw new \RuntimeException('Unexpected failure'),
            // Real production path: the transport's own getSmtpErrorStatus()
            // classifies the PHPMailer error message (not a thrown exception,
            // unlike the 'authentication' case above), and now returns
            // QUEUE_STATUS_TEMP_ERROR for SMTP auth failures.
            'smtpAuthentication' => $this->getSmtpErrorStatus('SMTP Error: Could not authenticate.'),
            'countDelivery' => $this->deliver(),
            'smtpAuthFailCounted' => $this->failAuthentication(),
            'redis' => throw new \RedisException('Connection refused'),
            'cacheUnavailable' => throw new CacheUnavailable('Cant connect to Redis'),
            'deadlock' => throw new DeadlockException(self::driverError('Deadlock found when trying to get lock'), null),
            'lockWait' => throw new LockWaitTimeoutException(self::driverError('Lock wait timeout exceeded'), null),
            'connectionLost' => throw new ConnectionLost(self::driverError('MySQL server has gone away'), null),
            default => $this->result,
        };
    }

    public function validate(): void
    {
    }

    private static function driverError(string $message): PdoDriverException
    {
        return PdoDriverException::new(new \PDOException($message));
    }

    /**
     * What Smtp::send() reports when AUTH fails at login: the text classifies
     * the row, the connect-phase flag pauses the transport.
     */
    private function failAuthentication(): int
    {
        self::$deliveries++;
        $this->connectionFailure = true;
        return $this->getSmtpErrorStatus('SMTP Error: Could not authenticate.');
    }

    private function deliver(): int
    {
        self::$deliveries++;
        return 0;
    }

    private function render(Model\Queue $queue): int
    {
        $queue->getBody();
        throw new Validation('Rendering failed');
    }
}
