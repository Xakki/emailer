<?php

declare(strict_types=1);

namespace Xakki\Emailer\Transports;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Xakki\Emailer\Cqrs\Domain\GetMxRecord;
use Xakki\Emailer\Exception;
use Xakki\Emailer\Model;

class Smtp extends AbstractTransport
{
    public const HOST_LOCAL = 'localhost';

    public int $slowTime = 15;
    public int $port = 25;
    public string $host = self::HOST_LOCAL;
    public string $user = '';
    public string $pass = '';
    public bool $isAuth = false;
    public string $secure = PHPMailer::ENCRYPTION_SMTPS;
    public string $dkim = '';
    public string $encoding = PHPMailer::ENCODING_BASE64;
    public string $charSet = PHPMailer::CHARSET_UTF8;
    /** @var array<mixed> */
    public array $options = [];
    public int $debug = 0;
    /** @var array<mixed>  */
    public array $smtpOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    public function validate(): void
    {
        $err = [];
        if (!$this->fromEmail) {
            $err[] = 'fromEmail';
        }
        if (!$this->fromName) {
            $err[] = 'fromName';
        }

        if ($this->host == self::HOST_LOCAL) {
            if (!$this->dkim) {
                $err[] = 'dkim';
            }
        } else {
            if (!$this->port) {
                $err[] = 'port';
            }
            if (!$this->host) {
                $err[] = 'host';
            }
            if ($this->isAuth) {
                if (!$this->user) {
                    $err[] = 'user';
                }
                if (!$this->pass) {
                    $err[] = 'pass';
                }
            }
        }

        if ($err) {
            throw new Exception\Validation('Properties ' . implode(', ', $err) . '  is requare', Exception\Validation::CODE_SMTP);
        }
    }

    protected function createPhpMailer(): SmtpMailer
    {
        return new SmtpMailer(true);
    }

    /**
     * MX hosts of the recipient's domain for direct delivery (HOST_LOCAL);
     * overridable by tests, like createPhpMailer().
     *
     * @return array<int, string>
     */
    protected function findMx(string $domain): array
    {
        return (new GetMxRecord($domain))->handler();
    }

    /**
     * @param Model\Queue $queue
     * @return int
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws Exception\Exception
     * @throws Exception\Validation
     */
    public function send(Model\Queue $queue): int
    {
        $this->errorMessage = '';
        $this->connectionFailure = false;
        $mail = $queue->getMail();

        $phpMailer = $this->createPhpMailer();
        $phpMailer->XMailer = 'EmailService';
        $phpMailer->Timeout = 30;
        $startTime = time();

        $phpMailer->isSMTP();
        $phpMailer->Encoding = $this->encoding;
        $phpMailer->CharSet = $this->charSet;

        $phpMailer->setFrom($this->fromEmail, $this->fromName);

        if ($this->host == self::HOST_LOCAL) {
//            $from = explode('@', $this->fromEmail);
//            $mail->Hostname = $from[1];
            $phpMailer->SMTPOptions = $this->smtpOptions;
            if (file_exists($this->dkim)) {
                //$mail->DKIM_identity ;
                //$mail->DKIM_passphrase ;
                $phpMailer->DKIM_domain = $phpMailer->Hostname;
                $phpMailer->DKIM_selector = 'mail'; // эта фигня именно такой должна быть
                $phpMailer->DKIM_private_string = (string) file_get_contents($this->dkim); //       or  $mail->DKIM_private;
            } else {
                throw new Exception\Exception('No dkim file');
            }

            $domain = explode('@', $mail->getEmail());
            $mx = $this->findMx($domain[1]);
            if ($mx) {
                $phpMailer->Host = implode(';', $mx);
            }

            //
            //            if ($Config->Hostname != $mail->Hostname) {
            //                if (!empty($setting['customHeaders']['List-Unsubscribe'])) {
            //                    $setting['customHeaders']['List-Unsubscribe'] = str_replace($Config->Hostname, $mail->Hostname, $setting['customHeaders']['List-Unsubscribe']);
            //  print)r();              }
            //                if (!empty($setting['customHeaders']['MessageID'])) {
            //                    $setting['customHeaders']['MessageID'] = str_replace($Config->Hostname, $mail->Hostname, $setting['customHeaders']['MessageID']);
            //                }
            //                $aTplData['body'] = str_replace($Config->Hostname, $from[1], $aTplData['body']);
            //            }
        } else {
            $phpMailer->Port = $this->port;
            $phpMailer->Host = $this->host;
//            $mail->Hostname = $Project->name;
            $phpMailer->SMTPOptions = $this->smtpOptions;
            $phpMailer->SMTPSecure = $this->secure;
            //$mail->SMTPKeepAlive = true;

            if ($this->isAuth) {
                $phpMailer->SMTPAuth = true;
                $phpMailer->Username = $this->user;
                $phpMailer->Password = $this->pass;
            }
        }

        if ($this->debug) {
            $phpMailer->SMTPDebug = $this->debug;
            $phpMailer->Debugoutput = $this->emailer->getLogger();
        }

        if (!empty($mail->getReplyTo())) {
            foreach ($mail->getReplyTo() as $k => $v) {
                $phpMailer->addReplyTo($k, $v);
            }
        }

        $phpMailer->addAddress($mail->getEmail(), $mail->getEmailName());

        $phpMailer->Subject = $queue->getSubject();
        $phpMailer->isHTML();
        $phpMailer->msgHTML($queue->getBody());

        if ($queue->allowBodyAlt()) {
            $phpMailer->AltBody = $queue->getBodyAlt();
        }

        if ($queue->getMessageID()) {
            $phpMailer->MessageID = $queue->getMessageID();
        }

        foreach ($queue->getCustomHeaders() as $k => $r) {
            $phpMailer->addCustomHeader($k, $r);
        }

        if (!$phpMailer->preSend()) {
            throw new PHPMailerException($phpMailer->ErrorInfo ?: 'SMTP message preparation failed');
        }

        $html = '';
        $deliveryBufferLevel = ob_get_level();
        ob_start();
        try {
            try {
                $phpMailer->connect();
            } catch (PHPMailerException $exception) {
                // Connect / TLS / AUTH failed before anything was handed over:
                // the relay or its credentials are unusable for every message of
                // this transport. Direct delivery (HOST_LOCAL) connects to the
                // recipient's own MX, whose failure says nothing about the rest.
                $this->connectionFailure = $this->host !== self::HOST_LOCAL;
                throw $exception;
            }
            $result = $phpMailer->postSend();
        } catch (PHPMailerException $exception) {
            $this->errorMessage = $phpMailer->ErrorInfo ?: $exception->getMessage() ?: 'SMTP delivery failed';
            $result = false;
        } finally {
            $phpMailer->smtpClose();
            if (ob_get_level() === $deliveryBufferLevel + 1) {
                $html = (string) ob_get_clean();
            }
        }

        if ($html) {
            $html = preg_replace('/<br\/?>(\r\n|\n\r|\n|\r)?/ui', PHP_EOL, $html);
        }

        $logContext = ['transport', 'queue_id' => $queue->id, 'email_id' => $queue->email_id];

        if ($phpMailer->ErrorInfo) {
            $this->errorMessage = $phpMailer->ErrorInfo;
            $this->emailer->getLogger()->error('ErrorInfo : ' . $phpMailer->ErrorInfo . PHP_EOL . $html, $logContext);
        } elseif ($this->debug && $html) {
            $this->emailer->getLogger()->debug($html, $logContext);
        }

        $startTime = time() - $startTime;
        if ($startTime > $this->slowTime) {
            $logContext['duration'] = $startTime;
            $this->emailer->getLogger()->notice('Slow', $logContext);
        }
        if (!$result) {
            return $this->getSmtpErrorStatus($this->errorMessage);
        }

        unset($phpMailer);
        return 0;
    }
}
