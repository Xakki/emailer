<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Mail;

class MailExtraTest extends TestCase
{
    public function testInitFromJsonAndJsonSerialize(): void
    {
        $mail = (new Mail())
            ->setEmail('a@example.com')
            ->setEmailName('Mr A')
            ->setBody('<p>hello</p>')
            ->setLocale('en');
        $json = json_encode($mail);
        self::assertIsString($json);

        $restored = Mail::initFromJson($json);
        self::assertSame('a@example.com', $restored->getEmail());
        self::assertSame('Mr A', $restored->getEmailName());
        self::assertSame('<p>hello</p>', $restored->getBody());
        self::assertSame('en', $restored->getLocale());
    }

    public function testGettersDefaultValues(): void
    {
        $mail = new Mail();
        self::assertSame('', $mail->getEmail());
        self::assertSame('', $mail->getEmailName());
        self::assertSame('', $mail->getSubject());
        self::assertSame('', $mail->getDescr());
        self::assertSame('', $mail->getBody());
        self::assertSame('ru', $mail->getLocale());
        self::assertSame([], $mail->getReplyTo());
        self::assertSame([], $mail->getData());
    }

    public function testSetReplyToRoundTrip(): void
    {
        $mail = (new Mail())->setReplyTo(['reply@example.com' => 'Mr Reply']);
        self::assertSame(['reply@example.com' => 'Mr Reply'], $mail->getReplyTo());
    }

    public function testValidateEmailRejectsBlank(): void
    {
        $this->expectException(Validation::class);
        (new Mail())->validate([]);
    }

    public function testSetDataRejectsTryingToReplaceExisting(): void
    {
        $mail = (new Mail())->setEmail('a@example.com');
        $this->expectException(Validation::class);
        $mail->setData(['email' => 'b@example.com']);
    }
}
