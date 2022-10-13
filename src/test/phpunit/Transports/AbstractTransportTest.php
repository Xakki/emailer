<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit\Transports;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\test\phpunit\Mocks;
use Xakki\Emailer\Transports;

class AbstractTransportTest extends TestCase
{
    use Mocks;

    public function testSuccess(): void
    {
        $emailer = $this->mockEmailerSuccess();
        $mock = $this->mockAbstractTransport($emailer);
        $mock->fromEmail = 'test@example.com';
        $mock->fromName = 'test';
        $mock->replyEmail = 'test2@example.com';
        $mock->replyName = 'test2';
        $json = (string) $mock;
        $mock2 = Transports\AbstractTransport::fromString($json, $emailer);
        self::assertEquals($json, (string) $mock2);
    }

    public function mockAbstractTransport(MockObject|Emailer $emailer): Transports\Smtp
    {
        return $this->getMockBuilder(Transports\Smtp::class)
            ->setConstructorArgs([$emailer])
            ->enableProxyingToOriginalMethods()
//            ->onlyMethods(['__toString'])
            ->getMock();
    }
}
