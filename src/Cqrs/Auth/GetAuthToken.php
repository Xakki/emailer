<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Auth;

use Xakki\Emailer\Cqrs\AbstractCqrs;
use Xakki\Emailer\Cqrs\CqrsInterface;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception\AccessFail;
use Xakki\Emailer\Exception\Validations;

class GetAuthToken extends AbstractCqrs implements CqrsInterface
{
    public const MEM_KEY = 'auth';
    public const LIFETIME = 86400 * 30;

    protected string $login;
    protected string $pass;

    /**
     * @param array<string,string> $data
     * @throws Validations
     */
    public function __construct(array $data)
    {
        $noValid = [];
        if (empty($data['login'])) {
            $noValid[] = ['login', Validations::CODE_REQUIRE];
        }
        if (empty($data['pass'])) {
            $noValid[] = ['pass', Validations::CODE_REQUIRE];
        }
        if ($noValid) {
            throw new Validations($noValid);
        }
        $this->login = (string) $data['login'];
        $this->pass = (string) $data['pass'];
    }

    /**
     * @return array<string, mixed>
     * @throws AccessFail
     * @throws \Xakki\Emailer\Exception\Exception
     */
    public function handler(): array
    {
        if ($this->login === 'admin' && $this->pass === 'todo') {
            $mem = Emailer::i()->getCache();
            $key = self::MEM_KEY . ':' . $this->login;
            $token = md5($this->login . time() . $this->pass . microtime());
            $hasOldAuth = $mem->get($key);
            $result = [
                'lifetime' => date('c', time() + self::LIFETIME),
                'xToken' => $token,
                'hasOldAuth' => (bool) $hasOldAuth,
            ];
            $mem->set($key, $result + ['login' => $this->login, 'role' => 'root'], self::LIFETIME);
        } else {
            throw new AccessFail('Wrong pass or login.');
        }
        return $result;
    }
}
