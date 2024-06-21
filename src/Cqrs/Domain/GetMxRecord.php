<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Domain;

use Xakki\Emailer\Emailer;

class GetMxRecord
{
    public const MEM_KEY_MX = 'mx';
    protected string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return array<int,string>
     * @throws \Xakki\Emailer\Exception\Exception
     */
    public function handler(): array
    {
        $mem = Emailer::i()->getCache();
        $key = self::MEM_KEY_MX . ':' . $this->domain;
        $val = $mem->get($key);
        if (!$val) {
            $res = getmxrr($this->domain, $mx);
            if (!$res) {
                return [];
            }
            $val = $mx;
            /** @psalm-suppress InvalidArgument **/
            $mem->set($key, $val, 86400 * 2);
        }
        return (array) $val;
    }
}
