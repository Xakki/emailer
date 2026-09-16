<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Queue;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Model\Queue;

class RepeatQueue extends AbstractQueue
{
    public function __construct(Emailer $emailer)
    {
        $this->emailer = $emailer;
        try {
            $this->queue = Queue::findOne(
                ['status' => array_keys(Queue::REPEATABLE_STATUS)],
                true,
            );
        } catch (DataNotFound $e) {
            $e->httpCode = 0;
            throw $e;
        }
    }

}
