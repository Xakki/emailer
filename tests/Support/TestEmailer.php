<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Support;

use Doctrine\DBAL\Connection;
use Xakki\Emailer\Emailer;

/**
 * Emailer wired to an injected (in-memory SQLite) connection so the
 * repository / model / CQRS layers can be exercised against a real database
 * without MySQL or Redis.
 */
final class TestEmailer extends Emailer
{
    public function setDb(Connection $db): void
    {
        $this->db = $db;
    }

    public function getDb(): Connection
    {
        return $this->db;
    }
}
