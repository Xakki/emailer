<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Subscribe;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;

class UpdateSubscribe
{
    protected Model\Queue $queue;
    protected string $status;
    protected int $period;

    public function __construct(Model\Queue $queue, string $status, int $period = 600)
    {
        $this->queue = $queue;
        $this->status = $status;
        $this->period = $period;
    }

    public function handler(): Model\Subscribe
    {
        $where = [
            'project_id' => $this->queue->project_id,
            'notify_id' => $this->queue->notify_id,
            'email_id' => $this->queue->email_id,
        ];
        $row = Repository\Subscribe::findOne($where);

        if ($row) {
            $subscribe = new Model\Subscribe($row);
            $upd = ['status' => $this->status];
            $subscribe->status = $this->status;
            if ($this->status == Model\Subscribe::STATUS_ON && $this->period !== $subscribe->period) {
                $upd['period'] = $this->period;
                $subscribe->period = $this->period;
            }
            Repository\Subscribe::updateById($subscribe->id, $upd);
        } else {
            $where['status'] = $this->status;
            $where['period'] = $this->period;
            try {
                $subscribe = new Model\Subscribe($where);
                $subscribe->id = Repository\Subscribe::insert($subscribe->getProperties());
            } catch (UniqueConstraintViolationException $e) {
                $subscribe = Model\Subscribe::findOne($where);
            }
        }
        return $subscribe;
    }
}
