<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Transports\AbstractTransport as TransportBase;

class Project extends AbstractModel
{
    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    public int $id;
    public string $name;
    public string $token;
    public string $params;
    /** @var array<string, string>|null  */
    protected ?array $paramsArray = [];
    public string $status;
    public string $created;

    protected static function repositoryClass(): string
    {
        return Repository\Project::class;
    }

    /**
     * @return array<string,string>
     */
    public function getParams(): array
    {
        $r = (array) json_decode($this->params);
        $r[Template::NAME_PROJECT] = $this->name;
        return $r;
    }

    public function getParam(string $name): mixed
    {
        if (!$this->params) {
            return null;
        }
        if (!$this->paramsArray) {
            $this->paramsArray = json_decode($this->params, true);
        }
        return $this->paramsArray[$name] ?? null;
    }

    public function createTplWraper(string $name, string $html): Template
    {
        return (new Cqrs\Template\CreateTemplate($this->id, $name, $html, Template::TYPE_WRAPER))->handler();
    }

    public function updateTplWraper(string $name, string $html): Template
    {
        return (new Cqrs\Template\UpdateTemplate($this->id, $name, $html, Template::TYPE_WRAPER))->handler();
    }

    public function createTplContent(string $name, string $html): Template
    {
        return (new Cqrs\Template\CreateTemplate($this->id, $name, $html, Template::TYPE_CONTENT))->handler();
    }

    public function createTplBlock(string $key, string $html): Template
    {
        return (new Cqrs\Template\CreateTemplate($this->id, $key, $html, Template::TYPE_BLOCK))->handler();
    }

    public function createNotify(string $name): Notify
    {
        $model = new Notify();
        $model->name = $name;
        $model->project_id = $this->id;
        return $model->insert();
    }

    public function createCampaign(string $subject, Template $wraper, Template $content, Notify $notify): Campaign
    {
        return (new Cqrs\Campaign\CreateCampaign($this, $subject, $wraper, $content, $notify))
            ->handler();
    }

    public function createTransport(TransportBase $transport): Transport
    {
        return (new Cqrs\Transport\CreateTransport($this->id, $transport))
            ->handler();
    }

    public function getCampaign(int $campaignId): Campaign
    {
        return (new Cqrs\Campaign\GetCampaign($this->id, $campaignId))->handler();
    }
}
