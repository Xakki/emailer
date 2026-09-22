<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Integration;

use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Tests\Support\IntegrationCase;
use Xakki\Emailer\Tests\Support\RecordingLogger;
use Xakki\Emailer\Tests\Support\TestEmailer;

/**
 * Every SQL statement the repository layer logs goes through one helper, so
 * the `sql_log` config (level, bound parameters) applies to all of them.
 */
class SqlLogTest extends IntegrationCase
{
    private RecordingLogger $logger;

    /**
     * @param array<string, mixed> $sqlLog
     */
    private function wire(array $sqlLog = []): void
    {
        $this->logger = new RecordingLogger();
        $input = ['db' => ['password' => 'x']];
        if ($sqlLog !== []) {
            $input['sql_log'] = $sqlLog;
        }
        // Emailer::__construct() registers itself for the static repository layer.
        $emailer = new TestEmailer(new ConfigService($input), $this->logger);
        $emailer->setDb($this->db);
    }

    /**
     * @return list<array{string, string, array<mixed>}>
     */
    private function sqlRecords(): array
    {
        $out = [];
        foreach ($this->logger->records as $r) {
            if (in_array($r['context'], [['query'], ['delete']], true)) {
                $out[] = [$r['level'], $r['message'], $r['context']];
            }
        }
        return $out;
    }

    /**
     * Pins today's output: debug level, the bare SQL, no bound values.
     */
    public function testDefaultLogsBareQueryAtDebug(): void
    {
        $this->wire();

        Repository\Email::findOne(['email' => 'user@example.com', 'project_id' => 1]);
        iterator_to_array(Repository\Email::findAll(['project_id' => 1]));
        Repository\Email::delete(['email' => 'user@example.com']);

        self::assertSame([
            ['debug', 'SELECT * FROM email WHERE (email=:email) AND (project_id=:project_id) LIMIT 1', ['query']],
            ['debug', 'SELECT * FROM email WHERE project_id=:project_id', ['query']],
            ['debug', 'DELETE FROM email WHERE email=:email', ['delete']],
        ], $this->sqlRecords());
    }

    /**
     * The consumer's former vendor patch, now a setting:
     * `<sql> | <json params>` at info, context unchanged.
     */
    public function testConfiguredLevelAndParamsApplyToEverySqlLog(): void
    {
        $this->wire(['level' => 'info', 'params' => true]);

        Repository\Email::findOne(['email' => 'user@example.com', 'project_id' => 1]);
        iterator_to_array(Repository\Email::findAll(['project_id' => 1]));
        Repository\Email::delete(['email' => 'user@example.com']);

        self::assertSame([
            [
                'info',
                'SELECT * FROM email WHERE (email=:email) AND (project_id=:project_id) LIMIT 1'
                    . ' | {"email":"user@example.com","project_id":1}',
                ['query'],
            ],
            ['info', 'SELECT * FROM email WHERE project_id=:project_id | {"project_id":1}', ['query']],
            ['info', 'DELETE FROM email WHERE email=:email | {"email":"user@example.com"}', ['delete']],
        ], $this->sqlRecords());
    }

    /**
     * The INSERT / UPDATE log lines, context included (their context is not a
     * bare tag, so sqlRecords() never sees them).
     *
     * @return list<array{string, string, array<mixed>}>
     */
    private function writeRecords(): array
    {
        $out = [];
        foreach ($this->logger->records as $r) {
            if (in_array($r['context'][0] ?? null, ['insert', 'update'], true)) {
                $out[] = [$r['level'], $r['message'], $r['context']];
            }
        }
        return $out;
    }

    private function writeRows(): void
    {
        $id = Repository\Email::insert(['email' => 'user@example.com', 'name' => 'Jane', 'project_id' => 1]);
        Repository\Email::updateById($id, ['name' => 'Joan']);
        Repository\Email::update(['name' => 'Jill'], ['email' => 'user@example.com']);
    }

    /**
     * Row values are personal data: without `params` the INSERT / UPDATE
     * context names the columns only.
     */
    public function testInsertUpdateContextCarriesColumnNamesOnlyByDefault(): void
    {
        $this->wire();

        $this->writeRows();

        self::assertSame([
            ['debug', 'INSERT INTO `email` => #1.', ['insert', 'data' => ['email', 'name', 'project_id']]],
            ['debug', 'UPDATE `email` => affected rows 1.', ['update', 'data' => ['name']]],
            ['debug', 'UPDATE `email` => affected rows 1.', ['update', 'data' => ['name'], 'criteria' => ['email']]],
        ], $this->writeRecords());
    }

    /**
     * `params` brings the values back; `level` does not apply to these lines,
     * they stay at debug.
     */
    public function testInsertUpdateContextCarriesValuesWithParams(): void
    {
        $this->wire(['level' => 'info', 'params' => true]);

        $this->writeRows();

        self::assertSame([
            [
                'debug',
                'INSERT INTO `email` => #1.',
                ['insert', 'data' => ['email' => 'user@example.com', 'name' => 'Jane', 'project_id' => 1]],
            ],
            ['debug', 'UPDATE `email` => affected rows 1.', ['update', 'data' => ['name' => 'Joan']]],
            [
                'debug',
                'UPDATE `email` => affected rows 1.',
                ['update', 'data' => ['name' => 'Jill'], 'criteria' => ['email' => 'user@example.com']],
            ],
        ], $this->writeRecords());
    }

    /**
     * A non-UTF-8 bound value must not break logging (plain json_encode would
     * return false and drop every parameter).
     */
    public function testNonUtf8ParamsAreSubstitutedNotDropped(): void
    {
        $this->wire(['params' => '1']);

        Repository\Email::findOne(['email' => "bad\xff", 'project_id' => 1]);

        self::assertSame([[
            'debug',
            'SELECT * FROM email WHERE (email=:email) AND (project_id=:project_id) LIMIT 1'
                . ' | {"email":"bad\ufffd","project_id":1}',
            ['query'],
        ]], $this->sqlRecords());
    }
}
