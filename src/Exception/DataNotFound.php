<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception;

class DataNotFound extends Exception
{
    public int $httpCode = 404;
    public string $title = 'Not Found';
}
