<?php

declare(strict_types=1);

namespace Xakki\Emailer\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Geometric-backoff retry scheduling for the queue: adds a nullable
 * "next attempt at" timestamp. Additive only — no data changes, no drops.
 *
 * NULL is the load-bearing default: every row created before this migration
 * (the historical backlog, `email_service` is shared by project_id 4 and 5)
 * keeps retry_at = NULL and is therefore never selected by
 * Repository\Queue::findOneForRepeat() (see Cqrs\Queue\RepeatQueue), which
 * requires `retry_at IS NOT NULL`. Only rows that fail *after* this migration
 * — via the new Cqrs\Queue\AbstractQueue backoff logic — ever get a non-NULL
 * retry_at and become eligible for a scheduled retry.
 */
final class Version20260921140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable queue.retry_at for geometric-backoff retry scheduling (NULL = legacy row, never retried automatically)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE queue ADD COLUMN retry_at datetime NULL AFTER retry');
        $this->addSql('CREATE INDEX ix_queue_status_retry_at ON queue (status, retry_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ix_queue_status_retry_at ON queue');
        $this->addSql('ALTER TABLE queue DROP COLUMN retry_at');
    }
}
