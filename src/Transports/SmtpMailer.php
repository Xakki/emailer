<?php

declare(strict_types=1);

namespace Xakki\Emailer\Transports;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * PHPMailer whose SMTP session is opened as a step of its own, so Smtp knows
 * structurally where a failure happened: while connecting / logging in
 * (transport-wide) or while handing a message over (per message).
 */
class SmtpMailer extends PHPMailer
{
    /**
     * Connect, EHLO, STARTTLS and AUTH — everything before MAIL FROM. The
     * following postSend() reuses the open session. A failure is recorded in
     * ErrorInfo exactly as postSend() records it, so the text classification
     * of the error does not depend on which step reported it.
     *
     * @throws Exception
     */
    public function connect(): void
    {
        try {
            if (!$this->smtpConnect($this->SMTPOptions)) {
                throw new Exception($this->lang('smtp_connect_failed'), self::STOP_CRITICAL);
            }
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug($exc->getMessage());
            throw $exc;
        }
    }
}
