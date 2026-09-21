<?php

declare(strict_types=1);

namespace Xakki\Emailer\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops ix_queue_status (status): the leftmost prefix of
 * ix_queue_status_retry_at (status, retry_at) from Version20260921140000
 * already serves every status lookup, so keeping both only doubles the index
 * writes on each queue status change.
 */
final class Version20260922090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop redundant ix_queue_status (covered by ix_queue_status_retry_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX ix_queue_status ON queue');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX ix_queue_status ON queue (status)');
    }
}
