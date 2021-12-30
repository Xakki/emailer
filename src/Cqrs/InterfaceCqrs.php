<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs;

interface InterfaceCqrs
{
    public function handler(): mixed;
}
