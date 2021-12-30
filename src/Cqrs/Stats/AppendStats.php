<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Stats;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;

class AppendStats
{
    protected Model\Queue $queue;
    protected int $action;

    public function __construct(Model\Queue $queue, int $action)
    {
        $this->queue = $queue;
        $this->action = $action;
    }

    public function handler(): void
    {
        // удалять Unsubscribe если action=Subscribe, и наоброт
        if ($this->action == Model\Stats::ACTION_SUBS) {
            Repository\Stats::delete(['project_id' => $this->queue->project_id, 'queue_id' => $this->queue->id, 'action' => Model\Stats::ACTION_UNSUB]);
        } elseif ($this->action == Model\Stats::ACTION_UNSUB) {
            Repository\Stats::delete(['project_id' => $this->queue->project_id, 'queue_id' => $this->queue->id, 'action' => Model\Stats::ACTION_SUBS]);
        }

        $model = new Model\Stats();
        $model->action = $this->action;
        $model->project_id = $this->queue->project_id;
        $model->queue_id = $this->queue->id;
        if (!empty($_SERVER['HTTP_REFERER']) && $ref = parse_url($_SERVER['HTTP_REFERER'])) {
            if ($ref['host'] !== $_SERVER['HTTP_HOST']) {
                $model->uri_ref = $ref['path'] . (!empty($ref['query']) ? '?' . $ref['query'] : '');
                $model->domain_id = (new Cqrs\Domain\GetDomain($ref['host']))->handler()->id;
            }
        }
        $model->browser_id = (new Cqrs\Browser\GetBrowserId($_SERVER['HTTP_USER_AGENT'] ?? ''))->handler();

        $model->id = Repository\Stats::insert($model->getProperties());
    }
}
