<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Exception\Exception;
use Xakki\Emailer\Helper\Tools;
use Xakki\Emailer\Mail;

class ToolsExtraTest extends TestCase
{
    public function testGetLocaleLoadsViewArray(): void
    {
        $locale = Tools::getLocale('en', 'view');
        self::assertArrayHasKey('unsubscribe.title', $locale);
    }

    public function testGetLocaleThrowsForUnknownFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Locale file not exist');
        Tools::getLocale('en', 'nope');
    }

    public function testGetBase64FileReturnsMimeAndPayload(): void
    {
        [$mime, $payload] = Tools::getBase64File(__DIR__ . '/../logo.png');
        self::assertStringContainsString('image/', (string) $mime);
        self::assertNotEmpty($payload);
    }

    public function testDumpAsStringHandlesObjectCycles(): void
    {
        $mail = new Mail();
        $mail->setEmail('a@example.com');
        // Repeat the same object so the in-internal "seen object" branch fires.
        $out = Tools::dumpAsString([$mail, $mail], 4);
        self::assertStringContainsString('Xakki\\Emailer\\Mail#', $out);
        self::assertStringContainsString('#1(...)', $out);
    }

    public function testDumpAsStringRespectsDepth(): void
    {
        $deep = ['a' => ['b' => ['c' => ['d' => 1]]]];
        self::assertStringContainsString('[...]', Tools::dumpAsString($deep, 2));
    }

    public function testDumpAsStringHandlesScalarsAndNull(): void
    {
        self::assertSame('true', Tools::dumpAsString(true));
        self::assertSame('false', Tools::dumpAsString(false));
        self::assertSame('null', Tools::dumpAsString(null));
        self::assertSame('3.14', Tools::dumpAsString(3.14));
        self::assertSame('[]', Tools::dumpAsString([]));
    }

    public function testRedirectLinkLeavesUnrelatedTextAlone(): void
    {
        $out = Tools::redirectLink('No links here at all', 'https://x.test/');
        self::assertSame('No links here at all', $out);
    }
}
