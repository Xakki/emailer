<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Campany;

use Xakki\Emailer\Model;

class GetCampany
{
    /**
     * @var Model\Campany[]
     */
    protected static array $campanies;
    protected int $projectId;
    protected int $campanyId;
    protected bool $useCache;

    public function __construct(int $projectId, int $campanyId, bool $useCache = true)
    {
        $this->projectId = $projectId;
        $this->campanyId = $campanyId;
        $this->useCache = $useCache;
    }

    public function handler(): Model\Campany
    {
        $key = $this->projectId . '/' . $this->campanyId;
        if (!$this->useCache || !isset(static::$campanies[$key])) {
            static::$campanies[$key] = Model\Campany::findOne(['id' => $this->campanyId, 'project_id' => $this->projectId, 'status' => Model\Campany::STATUS_ON]);
        }
        return static::$campanies[$key];
    }
}
