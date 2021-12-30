<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    private string $channel;

    public function __construct(string $name = 'app')
    {
        $this->channel = $name;
    }

    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<mixed> $context
     * @return void
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $message = sprintf(
            '%s.%s: %s' . PHP_EOL,
            $this->channel,
            is_string($level) ? strtoupper($level) : json_encode($level),
            $this->interpolate($message, $context)
        );
        fwrite(STDOUT, $message);
    }

    /**
     * @param string|\Stringable $message
     * @param array<mixed> $context
     * @return string
     */
    protected function interpolate(string|\Stringable $message, array $context = []): string
    {
        if ($message instanceof \Stringable) {
            return $message->__toString();
        }
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val)) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
