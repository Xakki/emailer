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
        try {
            $log->info('Run queue', $logParam);

            if (!$this->queue->isActiveSubscribe()) {
                $this->queue->status = Queue::QUEUE_STATUS_UNSUBSCRIBE;
                $this->queue->update(['status']);
                return $this->queue->status;
            }
            $this->queue->status = Queue::QUEUE_STATUS_RUN;
            $this->queue->update(['status']);

            $transportModel = (new Cqrs\Transport\GetTransportByQueue($this->queue))
                ->handler();
            $log->debug('Transport: ' . $transportModel->id, $logParam);
            $this->queue->updateTransportId($transportModel->id);

            $transport = $transportModel->getSmtpTransport($this->emailer);
            $status = $transport->send($this->queue);

            if (!$status) {
                $this->queue->setSended();
                $transportModel->incCntDay();
            } elseif ($status === Queue::QUEUE_STATUS_TEMP_ERROR) {
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
            } else {
                $this->queue->status = $status;
                $this->queue->update(['status']);
                $this->queue->updateLastError($transport->getError());
            }

            $log->debug('Run queue', $logParam);
        } catch (\Throwable $e) {
            $this->queue->status = Queue::QUEUE_STATUS_ERROR;
            $this->queue->update(['status']);
            $this->queue->updateLastError($e->getMessage());
            $log->error($e, $logParam);
        }
        return $this->queue->status;
    }
}
