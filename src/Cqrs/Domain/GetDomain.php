<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Domain;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;

class GetDomain
{
    protected string $host;
    protected bool $getMx = false;

    public function __construct(string $host, bool $getMx = false)
    {
        $this->host = $host;
        $this->getMx = $getMx;
    }

    public function handler(): Model\Domain
    {
        $data = ['name' => $this->host];
        $row = Repository\Domain::findOne($data);
        if ($row) {
            return new Model\Domain($row);
        }

        if ($this->getMx) {
            $mx = (new GetMxRecord($this->host))->handler();
            if (!$mx) {
                throw new Validation('Wrong email domain', Validation::CODE_MX_ERROR);
            } elseif ($mx[0] != $this->host) {
                $tmp = explode('.', $mx[0]);
                $tmp = array_slice($tmp, -2);
                $this->host = implode('.', $tmp);
                $this->getMx = false;
                $parentDomain = self::handler();
                $data['parent'] = $parentDomain->id;
            }
        }

        try {
            $id = Repository\Domain::insert($data);
        } catch (UniqueConstraintViolationException $e) {
            return Model\Domain::findOne($data);
        }

        return Model\Domain::findOne(['id' => $id]);
    }
}
