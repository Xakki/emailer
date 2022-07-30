<?php

declare(strict_types=1);

namespace Xakki\Emailer\Transports;

use Xakki\Emailer\Emailer;
use Xakki\Emailer\Model\Queue;

abstract class AbstractTransport implements \Stringable
{
    public string $fromEmail = '';
    public string $fromName = '';
    public string $replyEmail = '';
    public string $replyName = '';

    protected string $errorMessage = '';
    /** @var array<mixed> */
    protected static array $statusWordKey = [
        // YANDEX = Message rejected under suspicion of SPAM
        // YANDEX = blocked using spamsource.mail.yandex.net
        // YANDEX = Blocked by spam statistics
        'message sending for this account is disabled' => Queue::QUEUE_STATUS_SPAM,
        'SPAM' => Queue::QUEUE_STATUS_SPAM,
        'Blacklisted' => Queue::QUEUE_STATUS_SPAM, // 550 / qip.ru
        'support.proofpoint.com' => Queue::QUEUE_STATUS_SPAM, // https://support.proofpoint.com/dnsbl-lookup.cgi
        //'Policy rejection on the target address' => Queue::QUEUE_STATUS_SPAM,
        'sending quota exceeded' => Queue::QUEUE_STATUS_QUOTA,
        'Ratelimit exceeded for mailbox' => Queue::QUEUE_STATUS_QUOTA,

        //'Greylisting in action, please come back later' => Queue::QUEUE_STATUS_TEMP_ERROR, // Серые списки отклоняют только один раз, потом должны принять
        'Greylist' => Queue::QUEUE_STATUS_TEMP_ERROR, // Серые списки отклоняют только один раз, потом должны принять
        'please try later' => Queue::QUEUE_STATUS_TEMP_ERROR, // Greylisted, please try later.
        'Service currently unavailable' => Queue::QUEUE_STATUS_TEMP_ERROR, // Greylist
        'connect() failed' => Queue::QUEUE_STATUS_TEMP_ERROR,
        'yielded a deferred delivery' => Queue::QUEUE_STATUS_TEMP_ERROR,
        'try again later' => Queue::QUEUE_STATUS_TEMP_ERROR, // yandex = The recipient has exceeded their message rate limit. Try again later
        'Connection timed out' => Queue::QUEUE_STATUS_TEMP_ERROR,
        'Please come back later' => Queue::QUEUE_STATUS_TEMP_ERROR, // 451 | yandex.ru
        'temporarily deferred' => Queue::QUEUE_STATUS_TEMP_ERROR, // 421 | yahoo.com
        'Error: too many recipients' => Queue::QUEUE_STATUS_TEMP_ERROR, // yandex
        'Error: too many connections' => Queue::QUEUE_STATUS_TEMP_ERROR, // yandex
        'Error: timeout exceeded' => Queue::QUEUE_STATUS_TEMP_ERROR, // yandex
        'Connection refused' => Queue::QUEUE_STATUS_TEMP_ERROR,
        'Temporary lookup failure' => Queue::QUEUE_STATUS_TEMP_ERROR,
        'temporary' => Queue::QUEUE_STATUS_TEMP_ERROR,
        //'451' => Queue::QUEUE_STATUS_TEMP_ERROR,
        'Policy rejection on the target address' => Queue::QUEUE_STATUS_INVALID_MAIL, // учетная запись получателя вашего письма была заблокирована в связи с нарушением Пользовательского соглашения
        'No such user' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'invalid mailbox' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'User unknown' => Queue::QUEUE_STATUS_INVALID_MAIL, // mai.ru | gmail.ru
        'Unknown user' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | mail.7russia.ru
        'User inactive' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | udm.net
        'account disabled' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | udm.net
        'mailbox unavailable' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | hotmail.com
        'Invalid recipient' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'Unrouteable address' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'does not exist' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'Bad destination' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'Bad address syntax' => Queue::QUEUE_STATUS_INVALID_MAIL, // qmail.com
        'Address rejected' => Queue::QUEUE_STATUS_INVALID_MAIL, // #550 | mil.ru
        'locked due to inactivity' => Queue::QUEUE_STATUS_INVALID_MAIL, // #550 | meta.ua//'Syntax: MAIL FROM' => Queue::QUEUE_STATUS_INVALID_MAIL,// yandex
        'SC-001' => Queue::QUEUE_STATUS_INVALID_MAIL, // #550  / http://mail.live.com/mail/troubleshooting.aspx#errors
        '503 Valid RCPT' => Queue::QUEUE_STATUS_INVALID_MAIL, // #503  /  mail.ru
        'not found' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | rambler.ru
        'recipient rejected' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'Recipient unknown' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | mtu-net.ru
        'Addresses failed' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | pochta.ru
        'Recipient not in route list' => Queue::QUEUE_STATUS_INVALID_MAIL, // 550 | vtb-sz.ru
        //'552' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'inactive' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'You must provide at least one recipient email address' => Queue::QUEUE_STATUS_INVALID_MAIL,
        //        'reach is over quota' => Queue::QUEUE_STATUS_INVALID_MAIL,
        // 452 | gmail * The email account that you tried to reach is over quota
        'over quota' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'Mailbox size limit exceeded' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'nosuchuser' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'following recipients failed' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'Valid RCPT command must precede' => Queue::QUEUE_STATUS_INVALID_MAIL, // email error
        //        'access denied' => Queue::QUEUE_STATUS_INVALID_MAIL,
        'No route to host' => Queue::QUEUE_STATUS_INVALID_SMTP,
        'Relay access denied' => Queue::QUEUE_STATUS_INVALID_SMTP,
        'no such domain' => Queue::QUEUE_STATUS_INVALID_SMTP,
        'non-existent hosts' => Queue::QUEUE_STATUS_INVALID_SMTP,

        //        'Message rejected' => Queue::QUEUE_STATUS_SPAM,
        //        'Blocked' => Queue::QUEUE_STATUS_SPAM,
        'SMTP server error' => Queue::QUEUE_STATUS_TEMP_ERROR,
    ];

    protected Emailer $emailer;

    public function __construct(Emailer $emailer)
    {
        $this->emailer = $emailer;
    }

    public function getSmtpErrorStatus(string $mess): int
    {
        // https://yandex.ru/support/mail-new/web/letter/create.html
        // https://mail.qip.ru/support/
        // SPAM CHECK
        // http://mxtoolbox.com/
        foreach (self::$statusWordKey as $word => $status) {
            if (stripos($mess, (string) $word) !== false) {
                return $status;
            }
        }
        return Queue::QUEUE_STATUS_ERROR;
    }

    public static function fromString(string $json, Emailer $emailer): self
    {
        $json = json_decode($json, true);
        $class = new $json['class']($emailer);
        if (!$class instanceof self) {
            throw new \Exception('Must be Transport instance.');
        }
        foreach ($json['prop'] as $k => $v) {
            $class->{$k} = $v;
        }

        return $class;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return json_encode([
            'class' => get_class($this),
            'prop' => \Xakki\Emailer\Helper\Tools::getPublicProperty($this),
        ]);
    }

    public function getError(): string
    {
        return $this->errorMessage;
    }

    /**
     * @param Queue $queue
     * @return int Queue status
     */
    abstract public function send(Queue $queue): int;

    abstract public function validate(): void;
}
