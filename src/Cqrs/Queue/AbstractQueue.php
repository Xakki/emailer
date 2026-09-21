<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Queue;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Helper\RetrySchedule;
use Xakki\Emailer\Model\Queue;

abstract class AbstractQueue
{
    protected Queue $queue;
    protected Emailer $emailer;

    abstract public function __construct(Emailer $emailer);

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
        try {
            $log->info('Run queue', $logParam);

            if (!$this->queue->isActiveSubscribe()) {
                $this->writeResult(function (): void {
                    $this->queue->status = Queue::QUEUE_STATUS_UNSUBSCRIBE;
                    $this->queue->update(['status']);
                });
                return $this->queue->status;
            }
            if ($this->queue->status !== Queue::QUEUE_STATUS_RUN) {
                $this->claim();
            }

            $transportModel = (new Cqrs\Transport\GetTransportByQueue($this->queue))
                ->handler();
            $log->debug('Transport: ' . $transportModel->id, $logParam);
            $this->queue->updateTransportId($transportModel->id);

            $transport = $transportModel->getSmtpTransport($this->emailer);
            $status = $transport->send($this->queue);

            if (!$status) {
                $delivered = true;
                $this->writeResult(function () use ($transportModel): void {
                    $this->queue->setSended();
                    $transportModel->incCntDay();
                });
            } elseif ($status === Queue::QUEUE_STATUS_TEMP_ERROR) {
                $this->writeResult(function () use ($transport): void {
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
                    $this->queue->updateLastError($transport->getError());
                });
            } else {
                $this->writeResult(function () use ($status, $transport): void {
                    $this->queue->status = $status;
                    $this->queue->update(['status']);
                    $this->queue->updateLastError($transport->getError());
                });
            }

            $log->debug('Run queue', $logParam);
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
        return $this->queue->status;
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
