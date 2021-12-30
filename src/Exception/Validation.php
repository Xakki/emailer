<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception;

class Validation extends Exception
{
    public int $httpCode = 450;
    public string $title = 'Validation';

    public const CODE_WRONG_VALUE = 10;
    public const CODE_REQUIRE = 11;
    public const CODE_EMAIL_BAD = 12;
    public const CODE_DATA_MISS = 13;

    public const CODE_NOT_SUBSCRIBE = 20;
    public const CODE_REQUEST_BAD = 30;
    public const CODE_MX_ERROR = 40;
    public const CODE_SMTP = 50;
}
