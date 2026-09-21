<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller;

use Xakki\Emailer\Cqrs\Helper\Migration;
use Xakki\Emailer\Cqrs\Queue\AbstractQueue;
use Xakki\Emailer\Cqrs\Queue\ExecuteQueue;
use Xakki\Emailer\Cqrs\Queue\RepeatQueue;
use Xakki\Emailer\Cqrs\Transport\NewDayTransport;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Model\Queue;

/**
 * @method send(int $repeat = 1)
 * @method reSend(int $repeat = 1)
 * @method newDay()
 * @method migrations()
 */
class Console extends AbstractController
{
    protected function actionSend(int $repeat = 1): string
    {
        return $this->processQueue(fn(): ExecuteQueue => new ExecuteQueue($this->emailer), $repeat);
    }

    protected function actionReSend(int $repeat = 1): string
    {
        return $this->processQueue(fn(): RepeatQueue => new RepeatQueue($this->emailer), $repeat);
    }

    /**
     * Shared send/reSend loop. Each row is claimed (marked RUN) in its own
     * short transaction, then processed by handler() outside any transaction:
     * SMTP I/O never runs while a DB transaction or row lock is held.
     *
     * @param callable(): AbstractQueue $factory Selects the next row (FOR UPDATE);
     *     throws DataNotFound with httpCode 0 when the queue is drained.
     */
    private function processQueue(callable $factory, int $repeat): string
    {
        $info = [];
        for ($i = 0; $i < $repeat; $i++) {
            $stopFlag = false;
            try {
                $status = $this->claim($factory)->handler();
                $mess = Queue::TITLE_QUEUE_STATUS[$status] ?? 'unknown';
            } catch (DataNotFound $e) {
                if ($e->httpCode === 0) {
                    break;
                }
                throw $e;
            } catch (\Throwable $e) {
                $this->logger->error($e);
                $mess = $e->getMessage();
                $stopFlag = true;
            }
            if (!isset($info[$mess])) {
                $info[$mess] = 0;
            }
            $info[$mess]++;
            if ($stopFlag) {
                break;
            }
        }
        return 'Statuses: ' . var_export($info, true);
    }

    /**
     * @param callable(): AbstractQueue $factory
     */
    private function claim(callable $factory): AbstractQueue
    {
        $db = $this->emailer->getDb();
        $db->beginTransaction();
        try {
            $job = $factory();
            $job->claim();
            $db->commit();
        } catch (\Throwable $e) {
            // A failed commit() has already closed the transaction; an
            // unguarded rollBack() would throw and mask the real error.
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            throw $e;
        }
        return $job;
    }

    protected function actionNewDay(): string
    {
        $cnt = (new NewDayTransport())->handler();
        return 'Update transports count : ' . $cnt;
    }

    protected function actionMigrations(): void
    {
        array_shift($_SERVER['argv']);
        (new Migration())->handler();
    }
}
