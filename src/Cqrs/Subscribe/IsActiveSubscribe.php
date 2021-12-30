<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Subscribe;

use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;

class IsActiveSubscribe
{
    protected Model\Queue $queue;
    protected string $name;
    protected string $html;
    protected int $type;

    public function __construct(Model\Queue $queue)
    {
        if (empty($queue->project_id) || empty($queue->notify_id) || empty($queue->email_id)) {
            throw new Validation('Queue must have project_id, notify_id and email_id');
        }
        $this->queue = $queue;
    }

    public function handler(): bool
    {
        $where = [
            'project_id' => $this->queue->project_id,
            'notify_id' => $this->queue->notify_id,
            'email_id' => $this->queue->email_id,
        ];
        $row = Repository\Subscribe::findOne($where);
        if (!$row) {
            return true;
        }
        $subscribe = new Model\Subscribe($row);

        return $subscribe->status == Model\Subscribe::STATUS_ON;
    }
}
