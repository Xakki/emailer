<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception;

class Exception extends \Exception
{
    public int $httpCode = 500;
    public string $title = 'Error';
}
