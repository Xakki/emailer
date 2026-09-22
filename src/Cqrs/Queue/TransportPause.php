<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Queue;

use Xakki\Emailer\Model;

/**
 * Per-run transport breaker for Console send/reSend. Once a transport fails to
 * connect or log in (AbstractTransport::isConnectionFailure()), the rest of its
 * rows are left untouched (keeping their NEW / TEMP_ERROR + retry_at state)
 * until the next run: one AUTH attempt per transport per tick instead of one
 * per row, which would otherwise hammer the provider and risk an account
 * lockout.
 */
final class TransportPause
{
    /** @var array<int, true> */
    private array $pausedTransportIds = [];
    /** @var list<int> */
    private array $skipIds = [];

    public function isActive(): bool
    {
        return $this->pausedTransportIds !== [];
    }

    public function isPaused(Model\Transport $transport): bool
    {
        return isset($this->pausedTransportIds[$transport->id]);
    }

    public function pause(Model\Transport $transport): void
    {
        $this->pausedTransportIds[$transport->id] = true;
    }

    /**
     * Backstop for a row the selection SQL routed differently from PHP
     * (Repository\Transport::routedIdSubquery() vs find()): set it aside by id.
     */
    public function skip(Model\Queue $queue): void
    {
        // Selection must exclude skipped rows; seeing one again would mean the
        // run could spin forever on rows it will never attempt.
        if (in_array($queue->id, $this->skipIds, true)) {
            throw new \LogicException(sprintf('Queue row #%d was selected again after being skipped', $queue->id));
        }
        $this->skipIds[] = $queue->id;
    }

    /**
     * @return list<int>
     */
    public function skipIds(): array
    {
        return $this->skipIds;
    }

    /**
     * Excluded in the selection SQL itself (Repository\Queue::skip()), so a
     * paused transport's backlog costs no query per row.
     *
     * @return list<int>
     */
    public function pausedTransportIds(): array
    {
        return array_keys($this->pausedTransportIds);
    }
}
