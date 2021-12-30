<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception\Transport;

use Xakki\Emailer\Exception\Exception;

class AbstractTransportException extends Exception
{
    public string $title = 'Transport Error';
}
