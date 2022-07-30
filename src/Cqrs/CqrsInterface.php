<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs;

interface CqrsInterface
{
    public function handler(): mixed;
}
