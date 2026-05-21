<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Transports;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Tests\Logger;
use Xakki\Emailer\Transports\Smtp;

class SmtpValidateTest extends TestCase
{
    private Emailer $emailer;

    protected function setUp(): void
    {
        $this->emailer = new Emailer(new ConfigService(['db' => ['password' => 'x']]), new Logger('test'));
    }

    public function testValidateOkForRemoteHost(): void
    {
        $smtp = new Smtp($this->emailer);
        $smtp->fromEmail = 'sender@example.com';
        $smtp->fromName = 'Sender';
        $smtp->host = 'smtp.example.com';
        $smtp->port = 587;

        $smtp->validate();
        self::assertSame('', $smtp->getError());
    }

    public function testValidateOkForLocalWithDkim(): void
    {
        $smtp = new Smtp($this->emailer);
        $smtp->fromEmail = 'sender@example.com';
        $smtp->fromName = 'Sender';
        $smtp->host = Smtp::HOST_LOCAL;
        $smtp->dkim = '/tmp/dkim.key';

        $smtp->validate();
        self::assertSame('', $smtp->getError()); // reached without throwing
    }

    public function testValidateFailsWithoutCredentialsForAuth(): void
    {
        $smtp = new Smtp($this->emailer);
        $smtp->fromEmail = 'sender@example.com';
        $smtp->fromName = 'Sender';
        $smtp->host = 'smtp.example.com';
        $smtp->port = 587;
        $smtp->isAuth = true;

        $this->expectException(Validation::class);
        $this->expectExceptionMessage('user');
        $smtp->validate();
    }

    public function testValidateFailsForLocalWithoutDkim(): void
    {
        $smtp = new Smtp($this->emailer);
        $smtp->fromEmail = 'sender@example.com';
        $smtp->fromName = 'Sender';

        $this->expectException(Validation::class);
        $this->expectExceptionMessage('dkim');
        $smtp->validate();
    }

    public function testValidateAccumulatesAllErrors(): void
    {
        $smtp = new Smtp($this->emailer);
        // no fromEmail, no fromName, no host (default localhost), no dkim
        try {
            $smtp->validate();
            self::fail('expected Validation exception');
        } catch (Validation $e) {
            self::assertStringContainsString('fromEmail', $e->getMessage());
            self::assertStringContainsString('fromName', $e->getMessage());
            self::assertStringContainsString('dkim', $e->getMessage());
        }
    }
}
