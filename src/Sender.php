<?php

declare(strict_types=1);

namespace Xakki\Emailer;

use Throwable;
use Xakki\Emailer\Model\Campaign;
use Xakki\Emailer\Model\Project;

class Sender
{
    protected Emailer $emailer;
    protected Project $project;
    protected Campaign $campaign;

    /**
     * @param Emailer $emailer
     * @param int $projectId
     * @param int $campaignId
     * @throws Exception\DataNotFound
     */
    public function __construct(Emailer $emailer, int $projectId, int $campaignId)
    {
        if (!$projectId) {
            throw new Exception\DataNotFound('Empty ProjectId');
        }
        if (!$campaignId) {
            throw new Exception\DataNotFound('Empty CampaignId');
        }

        $this->emailer = $emailer;
        $this->project = $emailer->getProject($projectId);
        $this->campaign = $this->project->getCampaign($campaignId);
    }

    public function send(Mail $mail): string
    {
        $mail->validate($this->campaign->getRequiredParams());
        $queue = $this->initQueue($mail);
        return $queue->getHashRoute();
    }

    protected function initQueue(Mail $mail): Model\Queue
    {
        $needTransaction = !$this->emailer->getDb()->isTransactionActive();
        if ($needTransaction) {
            $this->emailer->getDb()->beginTransaction();
        }

        try {
            $queue = $this->createQueue($mail);
        } catch (Throwable $e) {
            if ($needTransaction) {
                $this->emailer->getDb()->rollBack();
            }
            throw $e;
        }

        if ($needTransaction) {
            $this->emailer->getDb()->commit();
        }

        return $queue;
    }

    protected function buildNewQueue(): Model\Queue
    {
        return new Model\Queue();
    }

    protected function createQueue(Mail $mail): Model\Queue
    {
        $queue = $this->buildNewQueue();
        $queue->status = Model\Queue::QUEUE_STATUS_NEW;
        $queue->campaign_id = $this->campaign->id;
        $queue->project_id = $this->project->id;
        $queue->notify_id = $this->campaign->notify_id;
        $queue->setMail($mail);

        if (!$queue->isActiveSubscribe()) {
            throw new Exception\Validation(
                sprintf('User `%s` is not subscrube on notify #%d', $mail->getEmail(), $queue->notify_id),
                Exception\Validation::CODE_NOT_SUBSCRIBE
            );
        }

        $queue->insert();
        $this->campaign->incCntQueue();
        return $queue;
    }
}
