<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller;

use Xakki\Emailer\Cqrs\Helper\Migration;
use Xakki\Emailer\Cqrs\Queue\ExecuteQueue;
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
        $info = [];
        for ($i = 0; $i < $repeat; $i++) {
//            $db = Emailer::i()->getDb();
//            $db->setAutoCommit(false);
//            $db->beginTransaction();
            try {
                $status = (new ExecuteQueue($this->emailer))->handler();
                $mess = Queue::TITLE_QUEUE_STATUS[$status];
//                $db->commit();
            } catch (DataNotFound $e) {
                if ($e->httpCode === 0) {
                    break;
                }
                throw $e;
            } catch (\Throwable $e) {
                $this->logger->error($e);
//                $db->rollBack();
                $mess = $e->getMessage();
                break;
            }
            if (!isset($info[$mess])) {
                $info[$mess] = 0;
            }
            $info[$mess]++;
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
