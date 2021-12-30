<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Domain;

use Xakki\Emailer\Emailer;

class GetDomainIdByEmail
{
    public const MEM_KEY_ID = 'domainsId';
    protected string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function handler(): int
    {
        $pos = strpos($this->email, '@');
        $domain = substr($this->email, $pos + 1);

        $mem = Emailer::i()->getCache();
        $key = self::MEM_KEY_ID . ':' . $domain;
        $val = $mem->get($key);
        if (!$val) {
            $domain = (new GetDomain($domain))->handler();
            $val = $domain->id;
            if ($domain->created && time() - strtotime($domain->created) > 10) {
                $mem->set($key, $val, 86400 * 2);
            }
        }
        return $val;
    }
}
