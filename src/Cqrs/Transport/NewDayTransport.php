<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Transport;

use Xakki\Emailer\Repository\Transport;

class NewDayTransport
{
    public function __construct()
    {
    }

    public function handler(): int
    {
        return Transport::update(['cnt_day' => 0], [1 => 1]);
    }
}
