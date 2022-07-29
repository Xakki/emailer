<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit\Transports;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\test\phpunit\Mocks;
use Xakki\Emailer\Transports\AbstractTransport;

class AbstractTransportTest extends TestCase
{
    use Mocks;

    public function testSuccess(): void
    {
        $mock = $this->mockAbstractTransport();
        $mock->fromEmail = 'test@example.com';
        $mock->fromName = 'test';
        $mock->replyEmail = 'test2@example.com';
        $mock->replyName = 'test2';
        $json = (string) $mock;
        $mock2 = AbstractTransport::fromString($json, $this->mockEmailerSuccess());
        self::assertEquals($json, (string) $mock2);
    }

    public function mockAbstractTransport(): AbstractTransport
    {
        return $this->getMockForAbstractClass(AbstractTransport::class);
    }
}
