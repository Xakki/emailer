<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Browser;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Xakki\Emailer\Cqrs\CqrsInterface;
use Xakki\Emailer\Repository;

class GetBrowserId implements CqrsInterface
{
    protected string $ua;

    public function __construct(string $ua)
    {
        $this->ua = $ua;
    }

    public function handler(): int
    {
        $data = ['ua' => $this->ua];
        $browserId = Repository\Browser::findId($data);
        if (!$browserId) {
            try {
                $browserId = Repository\Browser::insert($data);
            } catch (UniqueConstraintViolationException $e) {
                $browserId = Repository\Browser::findId($data);
            }
        }
        return $browserId;
    }
}
