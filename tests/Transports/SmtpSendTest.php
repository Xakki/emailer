<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Transports;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\SMTP as SmtpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Mail;
use Xakki\Emailer\Model\Queue;
use Xakki\Emailer\Transports\Smtp;
use Xakki\Emailer\Transports\SmtpMailer;

class SmtpSendTest extends TestCase
{
    /**
     * The authentication rows deliberately avoid every classification-table key
     * ('temporary', 'SMTP server error', ...): they must reach TEMP_ERROR through
     * the authentication fallback alone, one row per phrase. Every row fails in
     * postSend(), i.e. after login: none of them marks the transport as down.
     *
     * @return array<string, array{string, int, string}>
     */
    public static function deliveryErrors(): array
    {
        return [
            'could not authenticate goes through backoff' => [
                'SMTP Error: Could not authenticate.',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: Could not authenticate.',
            ],
            'authentication failed goes through backoff' => [
                'SMTP Error: 535 5.7.8 Authentication failed',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: 535 5.7.8 Authentication failed',
            ],
            'authentication failure goes through backoff' => [
                'SMTP Error: 535 Authentication failure',
                Queue::QUEUE_STATUS_TEMP_ERROR,
                'SMTP Error: 535 Authentication failure',
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
            self::assertFalse($smtp->isConnectionFailure());
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

    /**
     * The pause signal comes from WHERE the SMTP dialogue failed, not from the
     * reply text: a login failure is transport-wide, a later rejection is not.
     * The row's status keeps its text classification either way.
     */
    public function testAuthenticationFailureAtLoginIsAConnectionFailure(): void
    {
        $server = new ScriptedSmtp('auth');
        $smtp = $this->smtp($this->mailerWith($server));
        $smtp->isAuth = true;
        $smtp->user = 'user';
        $smtp->pass = 'wrong';

        self::assertSame(Queue::QUEUE_STATUS_TEMP_ERROR, $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1])));
        self::assertSame('SMTP Error: Could not authenticate.', $smtp->getError());
        self::assertTrue($smtp->isConnectionFailure());
        self::assertSame(['connect', 'hello', 'authenticate'], array_slice($server->calls, 0, 3));
        self::assertNotContains('mail', $server->calls, 'nothing is handed over after a failed login');
    }

    public function testRejectionAfterLoginMentioningAuthenticationIsNotAConnectionFailure(): void
    {
        $server = new ScriptedSmtp('data', '550 5.7.26 Unauthenticated email: DMARC authentication failed');
        $smtp = $this->smtp($this->mailerWith($server));
        $smtp->isAuth = true;
        $smtp->user = 'user';
        $smtp->pass = 'secret';

        self::assertSame(Queue::QUEUE_STATUS_TEMP_ERROR, $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1])));
        self::assertSame(
            'SMTP Error: data not accepted. SMTP server error: DATA END command failed'
            . ' Detail: 550 5.7.26 Unauthenticated email: DMARC authentication failed SMTP code: 550',
            $smtp->getError(),
        );
        self::assertFalse($smtp->isConnectionFailure());
        self::assertSame(1, array_count_values($server->calls)['connect'], 'one SMTP session per message');
    }

    /**
     * A real PHPMailer against a closed local port: connection refused.
     */
    public function testConnectFailureIsAConnectionFailure(): void
    {
        $smtp = $this->smtp(new SmtpMailer(true));

        self::assertSame(Queue::QUEUE_STATUS_TEMP_ERROR, $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1])));
        self::assertStringStartsWith('SMTP Error: Could not connect to SMTP host.', $smtp->getError());
        self::assertStringContainsString('Connection refused', $smtp->getError());
        self::assertTrue($smtp->isConnectionFailure());
    }

    /**
     * Direct delivery (HOST_LOCAL) follows the same rule as a relay: a failed
     * connect pauses the transport for the rest of the run.
     */
    public function testDirectMxConnectFailureIsAConnectionFailure(): void
    {
        $server = new ScriptedSmtp('connect');
        $smtp = $this->smtp($this->mailerWith($server));
        $smtp->host = Smtp::HOST_LOCAL;
        $smtp->dkim = __FILE__;

        self::assertSame(Queue::QUEUE_STATUS_TEMP_ERROR, $smtp->send(new SmtpSendQueue(['id' => 1, 'email_id' => 1])));
        self::assertStringContainsString('Connection refused', $smtp->getError());
        self::assertSame(['connect'], $server->calls);
        self::assertTrue($smtp->isConnectionFailure());
    }

    private function mailerWith(SmtpClient $server): SmtpMailer
    {
        $mailer = new SmtpMailer(true);
        $mailer->setSMTPInstance($server);
        return $mailer;
    }

    private function smtp(SmtpMailer $mailer): TestSmtp
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
    public function __construct(Emailer $emailer, private SmtpMailer $mailer)
    {
        parent::__construct($emailer);
    }

    protected function createPhpMailer(): SmtpMailer
    {
        return $this->mailer;
    }

    protected function findMx(string $domain): array
    {
        return ['mx.' . $domain];
    }
}

/**
 * The SMTP session opens fine (connect / login succeed without a network), so
 * the mailer's postSend() override is what the test exercises.
 */
trait OpenSession
{
    /**
     * @param array<mixed>|null $options
     */
    public function smtpConnect($options = null): bool
    {
        return true;
    }
}

class DeliveryExceptionMailer extends SmtpMailer
{
    use OpenSession;

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

class PreSendFailureMailer extends SmtpMailer
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

class DeliverySuccessMailer extends SmtpMailer
{
    use OpenSession;

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

/**
 * Scripted SMTP server: every step succeeds except $failAt ('connect', 'auth',
 * 'mail', 'data'), which fails with $reply as the server's detail — so the real
 * PHPMailer smtpConnect()/postSend() code runs without a network.
 */
class ScriptedSmtp extends SmtpClient
{
    /** @var list<string> */
    public array $calls = [];
    private bool $open = false;

    public function __construct(private string $failAt, private string $reply = '')
    {
    }

    /**
     * @param array<mixed> $options
     */
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->calls[] = 'connect';
        if ($this->failAt === 'connect') {
            $this->setError('Failed to connect to server', '', '111', 'Connection refused');
            return false;
        }
        $this->open = true;
        return true;
    }

    public function connected()
    {
        return $this->open;
    }

    public function hello($host = '')
    {
        $this->calls[] = 'hello';
        return true;
    }

    public function getServerExt($name)
    {
        return false;
    }

    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        $this->calls[] = 'authenticate';
        return $this->step('auth', 'User & Password command failed');
    }

    public function mail($from)
    {
        $this->calls[] = 'mail';
        return $this->step('mail', 'MAIL FROM command failed');
    }

    public function recipient($address, $dsn = '')
    {
        $this->calls[] = 'recipient';
        return true;
    }

    public function data($msg_data)
    {
        $this->calls[] = 'data';
        return $this->step('data', 'DATA END command failed');
    }

    public function reset()
    {
        return true;
    }

    public function quit($close_on_error = true)
    {
        // A successful QUIT clears the last error, as the real SMTP::quit() does.
        $this->setError('');
        $this->close();
        return true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    private function step(string $name, string $error): bool
    {
        if ($this->failAt !== $name) {
            return true;
        }
        $this->setError($error, $this->reply ?: '535 5.7.8 Authentication credentials invalid', substr($this->reply ?: '535', 0, 3));
        return false;
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
