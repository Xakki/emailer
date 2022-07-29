<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Campaign;

use Xakki\Emailer\Model;

class GetCampaign
{
    /**
     * @var Model\Campaign[]
     */
    protected static array $campanies;
    protected int $projectId;
    protected int $campaignId;
    protected bool $useCache;

    public function __construct(int $projectId, int $campaignId, bool $useCache = true)
    {
        $this->projectId = $projectId;
        $this->campaignId = $campaignId;
        $this->useCache = $useCache;
    }

    public function handler(): Model\Campaign
    {
        $key = $this->projectId . '/' . $this->campaignId;
        if (!$this->useCache || !isset(static::$campanies[$key])) {
            static::$campanies[$key] = Model\Campaign::findOne(['id' => $this->campaignId, 'project_id' => $this->projectId, 'status' => Model\Campaign::STATUS_ON]);
        }
        return static::$campanies[$key];
    }
}
