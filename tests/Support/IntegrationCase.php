<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Tests\Logger;

/**
 * Base case that boots a real in-memory SQLite database with a schema
 * mirroring the production table/column names, so the repository, model and
 * CQRS layers run against an actual driver instead of mocks.
 */
abstract class IntegrationCase extends TestCase
{
    protected Connection $db;
    protected TestEmailer $emailer;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach ($this->schema() as $sql) {
            $this->db->executeStatement($sql);
        }

        $this->emailer = new TestEmailer(new ConfigService(['db' => ['password' => 'x']]), new Logger('test'));
        $this->emailer->setDb($this->db);

        // CQRS request-scoped caches are static; clear them between tests.
        $this->resetStatic(Cqrs\Project\GetProject::class, 'projects');
        $this->resetStatic(Cqrs\Campaign\GetCampaign::class, 'campanies');
        $this->resetStatic(Cqrs\Email\GetEmail::class, 'emails');
    }

    /**
     * @param class-string $class
     */
    private function resetStatic(string $class, string $prop): void
    {
        $ref = new \ReflectionProperty($class, $prop);
        $ref->setValue(null, []);
    }

    /**
     * @return string[]
     */
    private function schema(): array
    {
        return [
            "CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,
                token TEXT NOT NULL DEFAULT '', params TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'on', created TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE domain (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'good', parent INTEGER NOT NULL DEFAULT 0,
                created TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(name))",
            "CREATE TABLE email (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL,
                name TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'on',
                created TEXT DEFAULT CURRENT_TIMESTAMP, cnt_send INTEGER NOT NULL DEFAULT 0,
                cnt_read INTEGER NOT NULL DEFAULT 0, domain_id INTEGER, project_id INTEGER NOT NULL,
                UNIQUE(project_id, email))",
            "CREATE TABLE notify (id INTEGER PRIMARY KEY AUTOINCREMENT, created TEXT DEFAULT CURRENT_TIMESTAMP,
                name TEXT NOT NULL, project_id INTEGER NOT NULL, UNIQUE(project_id, name))",
            "CREATE TABLE subscribe (id INTEGER PRIMARY KEY AUTOINCREMENT, notify_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL, email_id INTEGER NOT NULL, period INTEGER DEFAULT 600,
                status TEXT NOT NULL DEFAULT 'on', created TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(project_id, email_id, notify_id))",
            "CREATE TABLE transport (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL DEFAULT 'on',
                created TEXT DEFAULT CURRENT_TIMESTAMP, params TEXT NOT NULL DEFAULT '',
                limit_day INTEGER NOT NULL DEFAULT 0, cnt_day INTEGER NOT NULL DEFAULT 0,
                domain_id INTEGER, project_id INTEGER NOT NULL)",
            "CREATE TABLE tpl (id INTEGER PRIMARY KEY AUTOINCREMENT, created TEXT DEFAULT CURRENT_TIMESTAMP,
                name TEXT NOT NULL, html TEXT, status TEXT NOT NULL DEFAULT 'on', type TEXT NOT NULL,
                project_id INTEGER NOT NULL)",
            "CREATE TABLE tpl_rev (id INTEGER PRIMARY KEY AUTOINCREMENT, created TEXT DEFAULT CURRENT_TIMESTAMP,
                name TEXT NOT NULL, html TEXT, type TEXT NOT NULL, base_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL)",
            "CREATE TABLE campaign (id INTEGER PRIMARY KEY AUTOINCREMENT, created TEXT DEFAULT CURRENT_TIMESTAMP,
                finished TEXT, status TEXT NOT NULL DEFAULT 'on', name TEXT NOT NULL,
                limit_day INTEGER NOT NULL DEFAULT 0, cnt_send INTEGER NOT NULL DEFAULT 0,
                cnt_queue INTEGER NOT NULL DEFAULT 0, replacers TEXT, params TEXT,
                transport_id INTEGER, notify_id INTEGER NOT NULL, tpl_wrapper_id INTEGER NOT NULL,
                tpl_content_id INTEGER NOT NULL, project_id INTEGER NOT NULL)",
            "CREATE TABLE queue (id INTEGER PRIMARY KEY AUTOINCREMENT, created TEXT DEFAULT CURRENT_TIMESTAMP,
                sended TEXT, readed TEXT, unsubs TEXT, status INTEGER NOT NULL DEFAULT 0,
                retry INTEGER NOT NULL DEFAULT 0, campaign_id INTEGER NOT NULL, email_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL, notify_id INTEGER NOT NULL)",
            "CREATE TABLE queue_data (id INTEGER PRIMARY KEY, data TEXT NOT NULL,
                last_error TEXT, transport_id INTEGER)",
            "CREATE TABLE browser (id INTEGER PRIMARY KEY AUTOINCREMENT, ua TEXT NOT NULL, UNIQUE(ua))",
            "CREATE TABLE stats (id INTEGER PRIMARY KEY AUTOINCREMENT, created TEXT DEFAULT CURRENT_TIMESTAMP,
                uri_ref TEXT, domain_id INTEGER, queue_id INTEGER NOT NULL, browser_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL, action INTEGER NOT NULL)",
        ];
    }
}
