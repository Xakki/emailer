<?php

namespace Xakki\Emailer\Helper;

use Xakki\Emailer\Emailer;

class HandlerResolverRoute implements \Phroute\Phroute\HandlerResolverInterface
{
    protected Emailer $emailer;
    public function __construct(Emailer $emailer)
    {
        $this->emailer = $emailer;
    }

    public function resolve(mixed $handler): callable
    {
        if (is_array($handler) && is_string($handler[0])) {
            $handler[0] = new $handler[0]($this->emailer);
        }

        return $handler;
    }
}
