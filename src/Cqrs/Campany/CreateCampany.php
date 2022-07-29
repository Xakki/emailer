<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Campaign;

use Xakki\Emailer\Cqrs\Template\GetTemplateNames;
use Xakki\Emailer\Helper\Tools;
use Xakki\Emailer\Model;
use Xakki\Emailer\Model\Template;

class CreateCampaign
{
    protected Model\Project $project;
    protected string $subject;
    protected Template $tplWraper;
    protected Template $tplContent;
    protected Model\Notify $notify;
    protected ?Model\Transport $transport;
    protected int $limitDay;

    public function __construct(
        Model\Project $project,
        string $subject,
        Template $tplWraper,
        Template $tplContent,
        Model\Notify $notify,
        ?Model\Transport $transport = null,
        int $limitDay = 500
    ) {
        $this->project = $project;
        $this->subject = $subject;
        $this->tplWraper = $tplWraper;
        $this->tplContent = $tplContent;
        $this->notify = $notify;
        $this->transport = $transport;
        $this->limitDay = $limitDay;
    }

    public function handler(): Model\Campaign
    {
        // Add campaign
        $campaign = new Model\Campaign();
        $campaign->project_id = $this->project->id;
        $campaign->tpl_wraper_id = $this->tplWraper->id;
        $campaign->tpl_content_id = $this->tplContent->id;
        $campaign->notify_id = $this->notify->id;
        $campaign->limit_day = $this->limitDay;
        $campaign->status = Model\Campaign::STATUS_ON;
        $campaign->name = $this->subject;
        if ($this->transport) {
            $campaign->transport_id = $this->transport->id;
        }

        $rpl = $m = [];
        if (Tools::hasReplacer($this->subject, $m)) {
            $rpl = array_combine($m, $m);
        }

        if (Tools::hasReplacer($this->tplContent->html, $m)) {
            $rpl += array_combine($m, $m);
        }

        if (Tools::hasReplacer($this->tplWraper->html, $m)) {
            $rpl += array_combine($m, $m);
        }
        unset($rpl[Template::TYPE_CONTENT]);
        unset($rpl[Template::NAME_YEAR]);
        unset($rpl[Template::NAME_TIMEZONE]);
        unset($rpl[Template::NAME_TITLE]);
        unset($rpl[Template::NAME_DESCR]);

        $blocks = (new GetTemplateNames($campaign->project_id, Template::TYPE_BLOCK))
            ->handler();

        foreach ($blocks as $blockName) {
            if (isset($rpl[$blockName])) {
                unset($rpl[$blockName]);
            }
        }
        foreach (json_decode($this->project->params, true) as $k => $val) {
            if (isset($rpl[$k])) {
                unset($rpl[$k]);
            }
        }

        $campaign->replacers = json_encode(array_values($rpl));

        return $campaign->insert();
    }
}
