<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Project;

use Xakki\Emailer\Model;

class GetProject
{
    /**
     * @var Model\Project[]
     */
    protected static array $projects = [];
    protected int $projectId;
    protected bool $useCache;

    public function __construct(int $projectId, bool $useCache = true)
    {
        $this->projectId = $projectId;
        $this->useCache = $useCache;
    }

    /**
     * @return Model\Project
     * @throws \Xakki\Emailer\Exception\DataNotFound
     */
    public function handler(): Model\Project
    {
        if (!$this->useCache || !isset(static::$projects[$this->projectId])) {
            static::$projects[$this->projectId] = Model\Project::findOne(['id' => $this->projectId, 'status' => Model\Project::STATUS_ON]);
        }
        return static::$projects[$this->projectId];
    }
}
