<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Queue;

use Doctrine\DBAL\Exception as DBALException;
use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception;
use Xakki\Emailer\Helper\RetrySchedule;
use Xakki\Emailer\Model;
use Xakki\Emailer\Model\Queue;

abstract class AbstractQueue
{
    /**
     * Exceptions retried with the TEMP_ERROR backoff instead of failing the row
     * terminally: Redis (ext-redis error, or Emailer::getCache() connect failure)
     * and transient DBAL errors (deadlock / lock wait timeout via
     * RetryableException; connection refused / lost via ConnectionException).
     */
    private const TRANSIENT_EXCEPTIONS = [
        \RedisException::class,
        Exception\CacheUnavailable::class,
        DBALException\RetryableException::class,
        DBALException\ConnectionException::class,
    ];

    protected Queue $queue;
    protected Emailer $emailer;
    private ?Model\Transport $transportModel = null;
    private bool $transportConnectionFailure = false;

    /**
     * Selects the next row to process (FOR UPDATE), skipping the given rows and
     * the rows routed to the given transports (see TransportPause); throws
     * DataNotFound with httpCode 0 when nothing is left.
     *
     * @param list<int> $skipIds
     * @param list<int> $skipTransportIds
     */
    abstract public function __construct(Emailer $emailer, array $skipIds = [], array $skipTransportIds = []);

    public function getQueue(): Queue
    {
        return $this->queue;
    }

    /**
     * The transport this row is routed to, or null when it cannot be resolved
     * (handler() then records that failure for the row).
     */
    public function findTransport(): ?Model\Transport
    {
        try {
            return $this->getTransport();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Whether handler() failed while connecting to / logging in to the
     * transport's SMTP relay (connect, TLS, AUTH) — a transport-wide failure,
     * not a per-message one (see AbstractTransport::isConnectionFailure()).
     */
    public function isTransportConnectionFailure(): bool
    {
        return $this->transportConnectionFailure;
    }

    /**
     * Clock hook, overridable by tests (see TestSmtp::createPhpMailer() for the
     * same pattern) so the geometric-backoff schedule can be walked deterministically
     * instead of sleeping real seconds/hours.
     */
    protected function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    /**
     * Marks the selected row RUN. Console::processQueue() calls this inside a
     * short transaction that also holds the row's FOR UPDATE lock and commits
     * it before handler() talks to SMTP — so no DB transaction spans network
     * I/O, and a crash after this point strands the row in RUN (at-most-once)
     * instead of rolling it back into the queue for a duplicate delivery.
     */
    public function claim(): void
    {
        $this->queue->status = Queue::QUEUE_STATUS_RUN;
        $this->queue->update(['status']);
    }

    public function handler(): int
    {
        $log = $this->emailer->getLogger();
        $logParam = [
            'queue',
            'queue_id' => $this->queue->id,
            'email_id' => $this->queue->email_id,
            'campaign_id' => $this->queue->campaign_id,
            'project_id' => $this->queue->project_id,
        ];
        $delivered = false;
        $log->debug('Run queue', $logParam);
        try {
            if (!$this->queue->isActiveSubscribe()) {
                $this->writeResult(function (): void {
                    $this->queue->status = Queue::QUEUE_STATUS_UNSUBSCRIBE;
                    $this->queue->update(['status']);
                });
                $this->logOutcome($logParam);
                return $this->queue->status;
            }
            if ($this->queue->status !== Queue::QUEUE_STATUS_RUN) {
                $this->claim();
            }

            $transportModel = $this->getTransport();
            $log->debug('Transport: ' . $transportModel->id, $logParam);
            $this->queue->updateTransportId($transportModel->id);

            $transport = $transportModel->getSmtpTransport($this->emailer);
            $status = $transport->send($this->queue);
            $this->transportConnectionFailure = $transport->isConnectionFailure();

            if (!$status) {
                $delivered = true;
                $this->writeResult(function () use ($transportModel): void {
                    $this->queue->setSended();
                    $transportModel->incCntDay();
                });
            } elseif ($status === Queue::QUEUE_STATUS_TEMP_ERROR) {
                $this->writeResult(fn() => $this->scheduleRetry($transport->getError()));
            } else {
                $this->writeResult(function () use ($status, $transport): void {
                    $this->queue->status = $status;
                    $this->queue->update(['status']);
                    $this->queue->updateLastError($transport->getError());
                });
            }
        } catch (\Throwable $e) {
            $log->error($e, $logParam);
            try {
                if ($delivered) {
                    // The SMTP server already accepted the message: never hand the
                    // row back to the queue. Record the delivery alone (without the
                    // counters whose write may be what failed); if even that fails,
                    // the row stays RUN.
                    $this->writeResult(function (): void {
                        $this->queue->sended = date('Y-m-d H:i:s');
                        $this->queue->status = Queue::QUEUE_STATUS_SUCCESS;
                        $this->queue->update(['sended', 'status']);
                    });
                } elseif (self::isTransient($e)) {
                    $this->writeResult(fn() => $this->scheduleRetry($e->getMessage()));
                } else {
                    $this->writeResult(function () use ($e): void {
                        $this->queue->status = Queue::QUEUE_STATUS_ERROR;
                        $this->queue->update(['status']);
                        $this->queue->updateLastError($e->getMessage());
                    });
                }
            } catch (\Throwable $writeError) {
                // The outcome could not be stored (e.g. the DB is gone): the row
                // stays RUN. Surface the original failure, not the secondary one.
                $log->error($writeError, $logParam);
                throw $e;
            }
        }
        $this->logOutcome($logParam);
        return $this->queue->status;
    }

    /**
     * @param array<int|string, mixed> $logParam
     */
    private function logOutcome(array $logParam): void
    {
        $this->emailer->getLogger()->info('Send queue', $logParam + [
            'status' => $this->queue->status,
            'retry' => $this->queue->retry,
            'retry_at' => $this->queue->retry_at,
        ]);
    }

    private function getTransport(): Model\Transport
    {
        return $this->transportModel ??= (new Cqrs\Transport\GetTransportByQueue($this->queue))->handler();
    }

    /**
     * Allowlist: only failures of the infrastructure around the delivery that
     * are expected to heal on their own. Everything else (Validation, rendering,
     * configuration, unknown errors) stays terminal.
     */
    private static function isTransient(\Throwable $e): bool
    {
        foreach (self::TRANSIENT_EXCEPTIONS as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Geometric backoff: count the failed attempt and schedule the next one,
     * or go terminal when the attempts are exhausted.
     */
    private function scheduleRetry(string $error): void
    {
        $this->queue->retry++;
        $retryConfig = $this->emailer->getConfig()->retry;
        $delay = RetrySchedule::delaySeconds(
            $this->queue->retry,
            $retryConfig['max_attempts'],
            $retryConfig['first_delay'],
            $retryConfig['max_delay'],
        );
        if ($delay === null) {
            // Attempts exhausted: terminal, and deliberately NOT re-armed —
            // must never loop back into QUEUE_STATUS_TEMP_ERROR (that would
            // just rebuild the stuck backlog on a slower clock).
            $this->queue->status = Queue::QUEUE_STATUS_ERROR;
            $this->queue->update(['status', 'retry']);
        } else {
            $this->queue->status = Queue::QUEUE_STATUS_TEMP_ERROR;
            $this->queue->retry_at = $this->now()
                ->modify('+' . $delay . ' seconds')
                ->format('Y-m-d H:i:s');
            $this->queue->update(['status', 'retry', 'retry_at']);
        }
        $this->queue->updateLastError($error);
    }

    /**
     * Stores one outcome atomically in a short transaction; on failure the
     * in-memory row is restored so a follow-up write starts from the stored
     * state (e.g. retry is not incremented twice).
     */
    private function writeResult(callable $write): void
    {
        $snapshot = [
            'status' => $this->queue->status,
            'retry' => $this->queue->retry,
            'retry_at' => $this->queue->retry_at,
            'sended' => $this->queue->sended ?? null,
        ];
        $db = $this->emailer->getDb();
        $db->beginTransaction();
        try {
            $write();
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($snapshot as $field => $value) {
                $this->queue->{$field} = $value;
            }
            throw $e;
        }
    }
}
