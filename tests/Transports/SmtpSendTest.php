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
     * The authentication rows deliberately avoid every classification-table key
     * ('temporary', 'SMTP server error', ...): they must reach TEMP_ERROR through
     * the authentication fallback alone, one row per phrase.
     *
     * @return array<string, array{string, int, string, bool}>
     */
    public static function deliveryErrors(): array
    {
        return [
            'could not authenticate goes through backoff' => [
                'SMTP Error: Could not authenticate.',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Could not authenticate.',
                true,
            ],
            'authentication failed goes through backoff' => [
                'SMTP Error: 535 5.7.8 Authentication failed',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: 535 5.7.8 Authentication failed',
                true,
            ],
            'authentication failure goes through backoff' => [
                'SMTP Error: 535 Authentication failure',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: 535 Authentication failure',
                true,
            ],
            'generic temporary connection error is temporary' => [
                'SMTP Error: temporary connection failure',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: temporary connection failure',
                false,
            ],
            'connection refused is temporary' => [
                'SMTP Error: Connection refused',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Connection refused',
                false,
            ],
            'connection timeout is temporary' => [
                'SMTP Error: Connection timed out',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Connection timed out',
                false,
            ],
            'unknown delivery error remains terminal' => [
                'SMTP Error: delivery rejected',
                Queue::QUEUE_STATUS_ERROR,
                'SMTP Error: delivery rejected',
                false,
            ],
            'generic exception becomes a safe terminal error' => [
                '',
                Queue::QUEUE_STATUS_ERROR,
                'Generic delivery failure',
                false,
            ],
        ];
    }

    #[DataProvider('deliveryErrors')]
    public function testDeliveryExceptionUsesErrorInfoForStatus(
        string $errorInfo,
        int $expectedStatus,
        string $expectedError,
        bool $authenticationFailure,
    ): void {
        $mailer = new DeliveryExceptionMailer($errorInfo);
        $smtp = $this->smtp($mailer);
        $outerBufferLevel = ob_get_level();
        ob_start();

        try {
            self::assertSame($expectedStatus, $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1])));
            self::assertSame($expectedError, $smtp->getError());
            self::assertSame($authenticationFailure, $smtp->isAuthenticationFailure());
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

    /**
     * A message that cannot be prepared (preSend() fails) propagates as an
     * exception and never reaches the SMTP delivery step.
     */
    public function testPreDeliveryPhpMailerExceptionPropagates(): void
    {
        $mailer = new PreSendFailureMailer();
        $smtp = $this->smtp($mailer);

        try {
            $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1]));
            self::fail('A preSend() failure must propagate.');
        } catch (PHPMailerException $e) {
            self::assertSame('Message body empty', $e->getMessage());
        }
        self::assertTrue($mailer->preSendCalled);
        self::assertFalse($mailer->postSendCalled);
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

class PreSendFailureMailer extends PHPMailer
{
    public bool $preSendCalled = false;
    public bool $postSendCalled = false;

    public function __construct()
    {
        parent::__construct(true);
    }

    public function preSend()
    {
        $this->preSendCalled = true;
        $this->ErrorInfo = 'Message body empty';
        return false;
    }

    public function postSend()
    {
        $this->postSendCalled = true;
        return true;
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
