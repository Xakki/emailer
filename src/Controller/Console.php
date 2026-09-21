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
     * Shared send/reSend loop: $factory selects the next row (throwing
     * DataNotFound with httpCode 0 when the queue is drained).
     *
     * @param callable(): AbstractQueue $factory
     */
    private function processQueue(callable $factory, int $repeat): string
    {
        $info = [];
        $stopFlag = false;
        for ($i = 0; $i < $repeat; $i++) {
            $db = $this->emailer->getDb();
            $db->beginTransaction();
            try {
                $status = $factory()->handler();
                $mess = Queue::TITLE_QUEUE_STATUS[$status];
                $db->commit();
            } catch (DataNotFound $e) {
                $db->rollBack();
                if ($e->httpCode === 0) {
                    break;
                }
                throw $e;
            } catch (\Throwable $e) {
                $this->logger->error($e);
                $db->rollBack();
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
