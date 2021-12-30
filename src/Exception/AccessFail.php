<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception;

class AccessFail extends Exception
{
    public int $httpCode = 401;
    public string $title = 'Auth failed';
}
