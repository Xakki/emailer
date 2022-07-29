<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Queue;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Model\Queue;

class ExecuteQueue
{
    protected Queue $queue;
    protected Emailer $emailer;

    public function __construct(Emailer $emailer)
    {
        $this->emailer = $emailer;
        try {
            $this->queue = Queue::findOne(['status' => Queue::QUEUE_STATUS_NEW], true);
        } catch (DataNotFound $e) {
            $e->httpCode = 0;
            throw $e;
        }
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
            $log->debug('Send queue', $logParam);

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
            } else {
                $this->queue->status = $status;
                $this->queue->update(['status']);
                $this->queue->updateLastError($transport->getError());
            }
        } catch (\Throwable $e) {
            $this->queue->status = Queue::QUEUE_STATUS_TEMP_ERROR;
            $this->queue->update(['status']);
            $this->queue->updateLastError($e->getMessage());
            $log->error($e, $logParam);
        }
        return $this->queue->status;
    }
}
