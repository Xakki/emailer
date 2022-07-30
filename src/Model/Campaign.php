<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Campaign extends AbstractModel
{
    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    public int $id;
    public string $created;
    public ?string $finished;

    public string $name; // Subject
    public string $status;
    public string $params;
    public string $replacers;
    public int $limit_day;
    public int $cnt_send;
    public int $cnt_queue;
    public ?int $transport_id;
    public int $notify_id;
    public int $tpl_wrapper_id;
    public int $tpl_content_id;
    public int $project_id;

    protected static function repositoryClass(): string
    {
        return Repository\Campaign::class;
    }

    public function getNotify(): Notify
    {
        return Notify::findOneById($this->notify_id);
    }

    public function getTplWrapper(): Template
    {
        return Template::findOneById($this->tpl_wrapper_id);
    }

    public function getTplContent(): Template
    {
        return Template::findOneById($this->tpl_content_id);
    }

    /**
     * @return Template[]
     */
    public function getTplBlocks(): array
    {
        return Template::findAll(['project_id' => $this->project_id, 'type' => Template::TYPE_BLOCK]);
    }

    public function incCntQueue(): self
    {
        $this->cnt_queue++;
        Repository\Campaign::inc($this->id, 'cnt_queue');
        return $this;
    }

    public function incCntSend(): self
    {
        $this->cnt_send++;
        Repository\Campaign::inc($this->id, 'cnt_send');
        return $this;
    }

    /**
     * @return array<string,string>
     */
    public function getParams(): array
    {
        $r = $this->params ? (array) json_decode($this->params) : [];
        $r[Template::NAME_NOTIFY] = $this->getNotify()->name;
        return $r;
    }

    /**
     * @return string[]
     */
    public function getRequiredParams(): array
    {
        return $this->replacers ? (array) json_decode($this->replacers) : [];
    }
}
