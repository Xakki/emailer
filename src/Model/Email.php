<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Cqrs\Domain\GetDomainIdByEmail;
use Xakki\Emailer\Repository;

class Email extends AbstractModel
{
    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    public int $id;
    public ?int $domain_id;
    public string $email;
    public string $name;
    public string $status;
    public string $created;
    public int $cnt_send;
    public int $cnt_read;
    public int $project_id;
    protected ?Domain $domain = null;

    public static function getEmail(string $email, string $name, int $projectId): self
    {
        $model = static::findOrSave([
            'email' => $email,
            'name' => $name,
            'project_id' => $projectId,
        ], ['email' => $email]);
        if ($model->name != $name) {
            $model->name = $name;
            $model->update(['name']);
        }
        return $model;
    }

    protected static function repositoryClass(): string
    {
        return Repository\Email::class;
    }

    public function insert(): static
    {
        if (empty($this->domain_id) && $this->email) {
            $this->domain_id = (new GetDomainIdByEmail($this->email))->handler();
        }
        return parent::insert();
    }

    public function getDomain(): Domain
    {
        if (!$this->domain && $this->domain_id) {
            $this->domain = Domain::findOneById($this->domain_id);
        }

        if (!$this->domain) {
            throw new \Exception('No domain');
        }

        return $this->domain;
    }

    public function incCntSend(): self
    {
        Repository\Email::inc($this->id, 'cnt_send');
        return $this;
    }

    public function incCntRead(): self
    {
        Repository\Email::inc($this->id, 'cnt_read');
        return $this;
    }
}
