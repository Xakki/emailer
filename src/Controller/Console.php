<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller;

use Xakki\Emailer\Cqrs\Helper\Migration;
use Xakki\Emailer\Cqrs\Queue\AbstractQueue;
use Xakki\Emailer\Cqrs\Queue\ExecuteQueue;
use Xakki\Emailer\Cqrs\Queue\RepeatQueue;
use Xakki\Emailer\Cqrs\Queue\TransportPause;
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
        return $this->processQueue(
            fn(TransportPause $pause): ExecuteQueue => new ExecuteQueue(
                $this->emailer,
                $pause->skipIds(),
                $pause->pausedTransportIds(),
            ),
            $repeat,
        );
    }

    protected function actionReSend(int $repeat = 1): string
    {
        return $this->processQueue(
            fn(TransportPause $pause): RepeatQueue => new RepeatQueue(
                $this->emailer,
                $pause->skipIds(),
                $pause->pausedTransportIds(),
            ),
            $repeat,
        );
    }

    /**
     * Shared send/reSend loop. Each row is claimed (marked RUN) in its own
     * short transaction, then processed by handler() outside any transaction:
     * SMTP I/O never runs while a DB transaction or row lock is held.
     *
     * After a failure to connect to / log in to the SMTP server, the row's
     * transport is paused for the rest of this run; skipped rows do not count
     * against $repeat.
     *
     * @param callable(TransportPause): AbstractQueue $factory Selects the next
     *     row (FOR UPDATE); throws DataNotFound with httpCode 0 when drained.
     */
    private function processQueue(callable $factory, int $repeat): string
    {
        $info = [];
        $pause = new TransportPause();
        for ($i = 0; $i < $repeat; $i++) {
            $stopFlag = false;
            try {
                $job = $this->claim($factory, $pause);
                $status = $job->handler();
                $mess = Queue::TITLE_QUEUE_STATUS[$status] ?? 'unknown';
                $transport = $job->findTransport();
                if ($transport && $job->isTransportConnectionFailure()) {
                    $pause->pause($transport);
                }
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
     * Claims the next row whose transport is not paused. The selection SQL
     * already excludes rows routed to a paused transport (one query per claim,
     * whatever the paused backlog); a row that still resolves to a paused
     * transport in PHP is released unchanged and excluded by id, so this
     * terminates once only paused rows remain (DataNotFound).
     *
     * @param callable(TransportPause): AbstractQueue $factory
     */
    private function claim(callable $factory, TransportPause $pause): AbstractQueue
    {
        $db = $this->emailer->getDb();
        while (true) {
            $db->beginTransaction();
            try {
                $job = $factory($pause);
                $transport = $pause->isActive() ? $job->findTransport() : null;
                if ($transport && $pause->isPaused($transport)) {
                    $db->commit();
                    $pause->skip($job->getQueue());
                    continue;
                }
                $job->claim();
                $db->commit();
                return $job;
            } catch (\Throwable $e) {
                // A failed commit() has already closed the transaction; an
                // unguarded rollBack() would throw and mask the real error.
                if ($db->isTransactionActive()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }
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
