<?php

declare(strict_types=1);

namespace Xakki\Emailer;

use Throwable;
use Xakki\Emailer\Model\Campaign;
use Xakki\Emailer\Model\Project;
use Xakki\Emailer\Model\Template;

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
        $params = $this->getParams($mail->getData());
        $mail->validate($params);
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

    public function getParams(Mail $mail): array
    {
        $r = $mail->getData();
        $r += $this->campaign->getParams();
        $r += $this->project->getParams();
        $this->setRouteUrl($r);

        $this->replaceUrlInData($r);

        $r[Template::NAME_YEAR] = date('Y');
        $r[Template::NAME_URL] = $this->getHomeUrl();
        $r[Template::NAME_URL_LOGO] = $this->getLogoUrl();
        $r[Template::NAME_URL_UNSUBSCRIBE] = $this->getUrlUnsubscribe();
        $r[Template::NAME_URL_SUBSCRIBE] = $this->getUrlSubscribe();

        if (!isset($r[Template::NAME_TITLE])) {
            $r[Template::NAME_TITLE] = $this->getSubject();
        }

        if (!isset($r[Template::NAME_DESCR])) {
            $r[Template::NAME_DESCR] = $this->getDescr();
        }

        $lang = 'ru';
        if (isset($r[Template::NAME_LANG])) {
            $lang = $r[Template::NAME_LANG];
        }
        $r = $r + Helper\Tools::getLocale($lang, 'view');
        return $r;
    }
    
    
    /**
     * @param array<string, string> $data
     * @return $this
     */
    protected function setRouteUrl(array $data): self
    {
        $url = $data[Template::NAME_ROUTE];
        if (str_contains($url, '://') === false) {
            $url = 'https://' . $data[Template::NAME_HOST] . '/' . ltrim($url, '/');
        }

        $this->urlRoute = $url;
        if (str_contains($url, '?') === false) {
            $this->routeModeIsPath = true;
        }
        return $this;
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    public function replaceUrlInData(array &$data): void
    {
        $url = $this->getRouteUrl('goto');
        foreach ($data as &$r) {
            if (!is_string($r)) {
                continue;
            }
            $r = Helper\Tools::redirectLink($r, $url);
        }
    }

}
