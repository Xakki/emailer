<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Queue;

use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Model\Queue;
use Xakki\Emailer\Repository;

class RepeatQueue extends AbstractQueue
{
    public function __construct(Emailer $emailer, array $skipIds = [], array $skipTransportIds = [])
    {
        $this->emailer = $emailer;
        // Repository\Queue::findOneForRepeat() (not Model\Queue::findOne(), which
        // only supports =/IN) so the retry_at <= now scheduling condition — the
        // guard that keeps the legacy backlog (retry_at IS NULL) unselected — can
        // be expressed at all.
        $row = Repository\Queue::findOneForRepeat(
            Queue::QUEUE_STATUS_TEMP_ERROR,
            $this->now(),
            true,
            $skipIds,
            $skipTransportIds,
        );
        if (!$row) {
            $e = new DataNotFound('Not found data');
            $e->httpCode = 0;
            throw $e;
        }
        $this->queue = new Queue($row);
    }
}
