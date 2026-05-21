<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Integration;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Exception\Validations;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Tests\Support\IntegrationCase;
use Xakki\Emailer\Transports\Smtp;

class CqrsExtraTest extends IntegrationCase
{
    public function testCreateProjectErrorsWhenRequiredParamsMissing(): void
    {
        $this->expectException(Validation::class);
        $this->expectExceptionMessage('NAME_HOST');
        new Cqrs\Project\CreateProject('demo', []);
    }

    public function testCreateProjectErrorsWhenRouteMissing(): void
    {
        $this->expectException(Validation::class);
        $this->expectExceptionMessage('NAME_ROUTE');
        new Cqrs\Project\CreateProject('demo', [Model\Template::NAME_HOST => 'x']);
    }

    public function testCreateProjectErrorsWhenLogoMissing(): void
    {
        $this->expectException(Validation::class);
        $this->expectExceptionMessage('NAME_LOGO');
        new Cqrs\Project\CreateProject('demo', [
            Model\Template::NAME_HOST => 'x',
            Model\Template::NAME_ROUTE => 'r',
        ]);
    }

    public function testCreateProjectErrorsWhenLogoFileMissing(): void
    {
        $this->expectException(Validation::class);
        $this->expectExceptionMessage('not exist');
        new Cqrs\Project\CreateProject('demo', [
            Model\Template::NAME_HOST => 'x',
            Model\Template::NAME_ROUTE => 'r',
            Model\Template::NAME_URL_LOGO => '/no/such/path/logo.png',
        ]);
    }

    public function testGetBrowserIdInsertsThenReuses(): void
    {
        $id1 = (new Cqrs\Browser\GetBrowserId('curl/8'))->handler();
        self::assertGreaterThan(0, $id1);
        // Second lookup finds the existing row via findId.
        $id2 = (new Cqrs\Browser\GetBrowserId('curl/8'))->handler();
        self::assertSame($id1, $id2);
    }

    public function testNewDayTransportResetsCntDay(): void
    {
        Repository\Transport::insert([
            'params' => '{}', 'project_id' => 1, 'cnt_day' => 5,
        ]);
        $affected = (new Cqrs\Transport\NewDayTransport())->handler();
        self::assertSame(1, $affected);
        self::assertSame(0, Repository\Transport::findOne(['id' => 1])['cnt_day']);
    }

    public function testCreateTransportPersistsViaProject(): void
    {
        $project = (new Cqrs\Project\CreateProject('Demo', [
            Model\Template::NAME_HOST => 'demo.test',
            Model\Template::NAME_ROUTE => 'rdr',
            Model\Template::NAME_URL_LOGO => __DIR__ . '/../logo.png',
        ]))->handler();

        $smtp = new Smtp($this->emailer);
        $smtp->fromEmail = 'sender@example.com';
        $smtp->fromName = 'Sender';
        $smtp->host = 'smtp.example.com';
        $smtp->port = 465;

        $model = $project->createTransport($smtp);
        self::assertGreaterThan(0, $model->id);
        $reloaded = Model\Transport::findOneById($model->id);
        self::assertStringContainsString('sender@example.com', $reloaded->params);

        // Round-trip back to a transport object.
        self::assertSame('sender@example.com', $reloaded->getSmtpTransport($this->emailer)->fromEmail);

        $reloaded->incCntDay();
        self::assertSame(1, Repository\Transport::findOne(['id' => $reloaded->id])['cnt_day']);
    }

    public function testGetAuthTokenRejectsEmptyCredentials(): void
    {
        $this->expectException(Validations::class);
        new Cqrs\Auth\GetAuthToken([]);
    }
}
