<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Template;

use Xakki\Emailer\Exception;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;

class CreateTemplate
{
    protected int $projectId;
    protected string $name;
    protected string $html;
    protected string $type;

    public function __construct(int $projectId, string $name, string $html, string $type)
    {
        $this->projectId = $projectId;
        $this->name = $name;
        $this->html = $html;
        $this->type = $type;
    }

    public function handler(): Model\Template
    {
        $tplWraper = new Model\Template();
        $tplWraper->html = $this->html;
        $tplWraper->name = $this->name;
        $tplWraper->type = $this->type;
        $tplWraper->project_id = $this->projectId;
        $tplWraper->id = Repository\Template::insert($tplWraper->getProperties());
        if (!$tplWraper->id) {
            throw new Exception\Exception('Can`t insert template');
        }
        return $tplWraper;
    }
}
