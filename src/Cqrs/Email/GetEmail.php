<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Email;

use Xakki\Emailer\Model;

class GetEmail
{
    /**
     * @var Model\Email[]
     */
    protected static array $emails = [];
    protected int $emailId;
    protected bool $useCache;

    public function __construct(int $emailId, bool $useCache = true)
    {
        $this->emailId = $emailId;
        $this->useCache = $useCache;
    }

    /**
     * @return Model\Email
     * @throws \Xakki\Emailer\Exception\DataNotFound
     */
    public function handler(): Model\Email
    {
        $key = $this->emailId;
        if (!$this->useCache || !isset(static::$emails[$key])) {
            static::$emails[$key] = Model\Email::findOneById($this->emailId);
        }
        return static::$emails[$key];
    }
}
