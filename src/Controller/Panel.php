<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller;

class Panel extends AbstractController
{
    /**
     * @param string $name
     * @param array<string,mixed> $arguments
     * @return string
     */
    protected function run(string $name, array $arguments): string
    {
        $this->headerSend('Content-Type: text/html; charset=UTF-8');
        echo parent::run($name, $arguments);
        exit();
    }
}
