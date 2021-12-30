<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Repository;

class Campany extends AbstractModel
{
    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    public int $id;
    public string $created;
    public ?string $finished;

    public string $name; // Subject
    public string $status;
    public string $replacers;
    public int $limit_day;
    public int $cnt_send;
    public int $cnt_queue;
    public ?int $transport_id;
    public int $notify_id;
    public int $tpl_wraper_id;
    public int $tpl_content_id;
    public int $project_id;

    protected static function repositoryClass(): string
    {
        return Repository\Campany::class;
    }

    public function getNotify(): Notify
    {
        return Notify::findOneById($this->notify_id);
    }

    public function getTplWraper(): Template
    {
        return Template::findOneById($this->tpl_wraper_id);
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
        Repository\Campany::inc($this->id, 'cnt_queue');
        return $this;
    }

    public function incCntSend(): self
    {
        $this->cnt_send++;
        Repository\Campany::inc($this->id, 'cnt_send');
        return $this;
    }

    /**
     * @return array<string,string>
     */
    public function getParams(): array
    {
        $r = (array) json_decode($this->replacers);
        $r[Template::NAME_NOTIFY] = $this->getNotify()->name;
        return $r;
    }
}
