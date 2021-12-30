<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Template;

use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;

class UpdateTemplate
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
        $template = Model\Template::findOne(['project_id' => $this->projectId, 'type' => $this->type, 'name' => $this->name]);

        if ($this->html == $template->html && $this->name == $template->name) {
            return $template;
        }

        $template->html = $this->html;
        $template->name = $this->name;

        $template->id = Repository\Template::updateById($template->id, $template->getProperties());

        return $template;
    }
}
