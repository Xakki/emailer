<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Template;

use Xakki\Emailer\Repository;

class GetTemplateNames
{
    protected int $projectId;
    protected string $type;

    public function __construct(int $projectId, string $type)
    {
        $this->projectId = $projectId;
        $this->type = $type;
    }

    /**
     * @return string[]
     */
    public function handler(): array
    {
        return Repository\Template::getTplNameBlocks($this->projectId, $this->type);
    }
}
