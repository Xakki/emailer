<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Transports;

use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * The authentication phrases are a fallback after the classification table:
     * a recipient-side rejection that merely mentions "authentication failed"
     * (DMARC/SPF) keeps its SPAM / INVALID_* verdict. Text alone never marks
     * the transport as down: only a failed connect / login does (Smtp::send()).
     */
    #[DataProvider('authenticationPhraseMessages')]
    public function testAuthenticationPhraseDoesNotOverrideTheClassificationTable(string $message, int $status): void
    {
        $transport = new Transports\Smtp($this->mockEmailerSuccess());

        self::assertSame($status, $transport->getSmtpErrorStatus($message));
        self::assertFalse($transport->isConnectionFailure());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function authenticationPhraseMessages(): iterable
    {
        yield 'DMARC rejection stays SPAM' => [
            'SMTP Error: data not accepted. SMTP server error: 550 5.7.1 Message rejected as SPAM: DMARC authentication failed',
            Queue::QUEUE_STATUS_SPAM,
        ];
        yield 'unknown recipient stays INVALID_MAIL' => [
            'SMTP Error: The following recipients failed: foo@example.com: 550 5.1.1 User unknown (SPF authentication failed)',
            Queue::QUEUE_STATUS_INVALID_MAIL,
        ];
        yield 'PHPMailer AUTH failure with server detail' => [
            'SMTP Error: Could not authenticate. SMTP server error: AUTH command failed Detail: Authentication failed SMTP code: 535',
            Queue::QUEUE_STATUS_TEMP_ERROR,
        ];
        yield 'bare PHPMailer AUTH failure' => [
            'SMTP Error: Could not authenticate.',
            Queue::QUEUE_STATUS_TEMP_ERROR,
        ];
    }
}
