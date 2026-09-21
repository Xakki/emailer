<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Transports;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Mail;
use Xakki\Emailer\Model\Queue;
use Xakki\Emailer\Transports\Smtp;

class SmtpSendTest extends TestCase
{
    /**
     * @return array<string, array{string, int, string}>
     */
    public static function deliveryErrors(): array
    {
        return [
            'authentication goes through backoff' => [
                'SMTP Error: Could not authenticate.',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Could not authenticate.',
            ],
            'could not authenticate with temporary goes through backoff' => [
                'SMTP Error: Could not authenticate because of a temporary authentication failure.',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Could not authenticate because of a temporary authentication failure.',
            ],
            'authentication failure with temporary goes through backoff' => [
                'SMTP Error: Authentication failed because of a temporary credential failure.',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Authentication failed because of a temporary credential failure.',
            ],
            'generic temporary connection error is temporary' => [
                'SMTP Error: temporary connection failure',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: temporary connection failure',
            ],
            'connection refused is temporary' => [
                'SMTP Error: Connection refused',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Connection refused',
            ],
            'connection timeout is temporary' => [
                'SMTP Error: Connection timed out',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Connection timed out',
            ],
            'unknown delivery error remains terminal' => [
                'SMTP Error: delivery rejected',
                Queue::QUEUE_STATUS_ERROR,
                'SMTP Error: delivery rejected',
            ],
            'generic exception becomes a safe terminal error' => [
                '',
                Queue::QUEUE_STATUS_ERROR,
                'Generic delivery failure',
            ],
        ];
    }

    #[DataProvider('deliveryErrors')]
    public function testDeliveryExceptionUsesErrorInfoForStatus(
        string $errorInfo,
        int $expectedStatus,
        string $expectedError,
    ): void {
        $mailer = new DeliveryExceptionMailer($errorInfo);
        $smtp = $this->smtp($mailer);
        $outerBufferLevel = ob_get_level();
        ob_start();

        try {
            self::assertSame($expectedStatus, $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1])));
            self::assertSame($expectedError, $smtp->getError());
            self::assertTrue($mailer->closed);
            self::assertSame($outerBufferLevel + 1, ob_get_level());
            self::assertSame('', ob_get_contents());
        } finally {
            while (ob_get_level() > $outerBufferLevel) {
                ob_end_clean();
            }
        }
    }

    public function testDeliverySuccessClosesSmtpAndCleansOnlyDeliveryBuffer(): void
    {
        $mailer = new DeliverySuccessMailer();
        $smtp = $this->smtp($mailer);
        $outerBufferLevel = ob_get_level();
        ob_start();

        try {
            self::assertSame(0, $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1])));
            self::assertTrue($mailer->closed);
            self::assertSame($outerBufferLevel + 1, ob_get_level());
            self::assertSame('', ob_get_contents());
        } finally {
            while (ob_get_level() > $outerBufferLevel) {
                ob_end_clean();
            }
        }
    }

    public function testPreDeliveryPhpMailerExceptionPropagates(): void
    {
        $smtp = $this->smtp(new DeliveryExceptionMailer(''));
        $smtp->fromEmail = 'not an email address';

        $this->expectException(PHPMailerException::class);
        $this->expectExceptionMessage('Invalid address');

        $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1]));
    }

    private function smtp(PHPMailer $mailer): TestSmtp
    {
        $smtp = new TestSmtp(
            new Emailer(new ConfigService(['db' => ['password' => 'x']]), new NullLogger()),
            $mailer,
        );
        $smtp->fromEmail = 'sender@example.com';
        $smtp->fromName = 'Sender';
        $smtp->host = '127.0.0.1';
        $smtp->port = 1;
        $smtp->secure = '';

        return $smtp;
    }
}

class TestSmtp extends Smtp
{
    public function __construct(Emailer $emailer, private PHPMailer $mailer)
    {
        parent::__construct($emailer);
    }

    protected function createPhpMailer(): PHPMailer
    {
        return $this->mailer;
    }
}

class DeliveryExceptionMailer extends PHPMailer
{
    public bool $closed = false;

    public function __construct(private string $deliveryError)
    {
        parent::__construct(true);
    }

    public function postSend()
    {
        $this->ErrorInfo = $this->deliveryError;
        echo 'SMTP delivery debug';
        throw new PHPMailerException('Generic delivery failure');
    }

    public function smtpClose(): void
    {
        $this->closed = true;
    }
}

class DeliverySuccessMailer extends PHPMailer
{
    public bool $closed = false;

    public function __construct()
    {
        parent::__construct(true);
    }

    public function postSend()
    {
        echo 'SMTP delivery debug';
        return true;
    }

    public function smtpClose(): void
    {
        $this->closed = true;
    }
}

class SmtpSendQueue extends Queue
{
    public function getMail(): Mail
    {
        return (new Mail())
            ->setEmail('recipient@example.com')
            ->setEmailName('Recipient');
    }

    public function getSubject(): string
    {
        return 'SMTP exception behavior';
    }

    public function getBody(): string
    {
        return '<p>SMTP exception behavior</p>';
    }

    public function allowBodyAlt(): bool
    {
        return false;
    }

    public function getMessageID(): string
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    public function getCustomHeaders(): array
    {
        return [];
    }
}
