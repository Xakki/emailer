<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Integration;

use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Tests\Support\IntegrationCase;

class SmallTargetsTest extends IntegrationCase
{
    public function testConfigServiceMergesNestedArrays(): void
    {
        $cfg = new ConfigService([
            'db' => ['password' => 'x', 'host' => 'h'],
            'redis' => ['port' => 9999],
            'secret_key' => 'k',
        ]);
        self::assertSame('x', $cfg->db['password']);
        self::assertSame('h', $cfg->db['host']);
        // Default driver preserved when overriding only some keys.
        self::assertSame('pdo_mysql', $cfg->db['driver']);
        self::assertSame(9999, $cfg->redis['port']);
        self::assertSame('emailer-redis', $cfg->redis['host']);
        self::assertSame('k', $cfg->secret_key);
    }

    public function testGetEmailCqrsUsesAndBypassesCache(): void
    {
        Repository\Domain::insert(['name' => 'example.com']);
        $emailId = Repository\Email::insert([
            'email' => 'a@example.com', 'name' => 'A',
            'project_id' => 1, 'domain_id' => 1,
        ]);

        $first = (new Cqrs\Email\GetEmail($emailId))->handler();
        $second = (new Cqrs\Email\GetEmail($emailId))->handler();
        self::assertSame($first, $second);

        $fresh = (new Cqrs\Email\GetEmail($emailId, false))->handler();
        self::assertSame($emailId, $fresh->id);
    }

    public function testModelEmailGetEmailAndDomain(): void
    {
        Repository\Domain::insert(['name' => 'example.com']);
        Repository\Email::insert([
            'email' => 'foo@example.com', 'name' => 'Foo',
            'project_id' => 1, 'domain_id' => 1,
        ]);

        // Existing row -> findOrSave returns it; name mismatch triggers an update.
        $email = Model\Email::getEmail('foo@example.com', 'Foo Renamed', 1);
        self::assertSame('Foo Renamed', $email->name);
        self::assertSame('Foo Renamed', Repository\Email::findOne(['id' => $email->id])['name']);

        // Email::getDomain lazily loads the related Domain.
        $domain = $email->getDomain();
        self::assertSame('example.com', $domain->name);
    }

    public function testTemplateUpdateGoesThroughAbstractRevision(): void
    {
        $project = (new Cqrs\Project\CreateProject('Demo', [
            Model\Template::NAME_HOST => 'demo.test',
            Model\Template::NAME_ROUTE => 'rdr',
            Model\Template::NAME_URL_LOGO => __DIR__ . '/../logo.png',
        ]))->handler();
        $project->createTplWrapper('w', '<html>v1</html>');

        // Updating via the CQRS handler writes a tpl_rev row (history) AND
        // updates the main row — that's what AbstractRevision::updateById does.
        $updated = $project->updateTplWrapper('w', '<html>v3</html>');
        self::assertSame('<html>v3</html>', $updated->html);
        self::assertSame('<html>v3</html>', Repository\Template::findOne(['id' => $updated->id])['html']);

        // The revision table now has a snapshot of the previous version.
        $rev = $this->db->fetchAssociative('SELECT html FROM tpl_rev WHERE base_id = ?', [$updated->id]);
        self::assertIsArray($rev);

        // A no-op update returns the model unchanged (short-circuit branch).
        $sameAgain = $project->updateTplWrapper('w', '<html>v3</html>');
        self::assertSame($updated->id, $sameAgain->id);
    }
}
