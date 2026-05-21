<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Transports;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Model\Queue;
use Xakki\Emailer\Tests\Mocks;
use Xakki\Emailer\Transports;

class AbstractTransportTest extends TestCase
{
    use Mocks;

    public function testToStringRoundTrip(): void
    {
        $emailer = $this->mockEmailerSuccess();

        $transport = new Transports\Smtp($emailer);
        $transport->fromEmail = 'test@example.com';
        $transport->fromName = 'test';
        $transport->replyEmail = 'test2@example.com';
        $transport->replyName = 'test2';

        $json = (string) $transport;
        $restored = Transports\AbstractTransport::fromString($json, $emailer);

        self::assertInstanceOf(Transports\Smtp::class, $restored);
        self::assertSame($json, (string) $restored);
        self::assertSame('test@example.com', $restored->fromEmail);
    }

    public function testGetSmtpErrorStatusMapping(): void
    {
        $emailer = $this->mockEmailerSuccess();
        $transport = new Transports\Smtp($emailer);

        self::assertSame(Queue::QUEUE_STATUS_SPAM, $transport->getSmtpErrorStatus('550 classified as SPAM'));
        self::assertSame(Queue::QUEUE_STATUS_INVALID_MAIL, $transport->getSmtpErrorStatus('550 No such user here'));
        self::assertSame(Queue::QUEUE_STATUS_ERROR, $transport->getSmtpErrorStatus('completely unrelated text'));
    }
}
