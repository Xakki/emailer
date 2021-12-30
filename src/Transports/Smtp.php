<?php

declare(strict_types=1);

namespace Xakki\Emailer\Transports;

use PHPMailer\PHPMailer\PHPMailer;
use Xakki\Emailer\Cqrs\Domain\GetMxRecord;
use Xakki\Emailer\Emailer;
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
        $mail = $queue->getMail();

        $phpMailer = new PHPMailer(true);
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
                $phpMailer->DKIM_private_string = file_get_contents($this->dkim); //       or  $mail->DKIM_private;
            } else {
                throw new Exception\Exception('No dkim file');
            }

            $domain = explode('@', $mail->getEmail());
            $mx = (new GetMxRecord($domain[1]))->handler();
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
            $phpMailer->Debugoutput = Emailer::getLoggerOld();
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

        ob_start();
        $result = $phpMailer->send();
        $phpMailer->smtpClose();
        $html = ob_get_clean();

        if ($html) {
            $html = preg_replace('/<br\/?>(\r\n|\n\r|\n|\r)?/ui', PHP_EOL, $html);
        }

        $logContext = ['transport', 'queue_id' => $queue->id, 'email_id' => $queue->email_id];

        if ($phpMailer->ErrorInfo) {
            $this->errorMessage = $phpMailer->ErrorInfo;
            Emailer::getLoggerOld()->error('ErrorInfo : ' . $phpMailer->ErrorInfo . PHP_EOL . $html, $logContext);
        } elseif ($this->debug && $html) {
            Emailer::getLoggerOld()->debug($html, $logContext);
        }

        $startTime = time() - $startTime;
        if ($startTime > $this->slowTime) {
            $logContext['duration'] = $startTime;
            Emailer::getLoggerOld()->notice('Slow', $logContext);
        }
        if (!$result) {
            return $this->getSmtpErrorStatus($phpMailer->ErrorInfo);
        }

        unset($phpMailer);
        return 0;
    }
}
