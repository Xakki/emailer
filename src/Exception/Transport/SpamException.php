<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception\Transport;

class SpamException extends AbstractTransportException
{
    public string $title = 'Spam detected';
}
