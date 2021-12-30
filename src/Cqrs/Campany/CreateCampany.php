<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Campany;

use Xakki\Emailer\Cqrs\Template\GetTemplateNames;
use Xakki\Emailer\Helper\Tools;
use Xakki\Emailer\Model;
use Xakki\Emailer\Model\Template;

class CreateCampany
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

    public function handler(): Model\Campany
    {
        // Add campany
        $campany = new Model\Campany();
        $campany->project_id = $this->project->id;
        $campany->tpl_wraper_id = $this->tplWraper->id;
        $campany->tpl_content_id = $this->tplContent->id;
        $campany->notify_id = $this->notify->id;
        $campany->limit_day = $this->limitDay;
        $campany->status = Model\Campany::STATUS_ON;
        $campany->name = $this->subject;
        if ($this->transport) {
            $campany->transport_id = $this->transport->id;
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

        $blocks = (new GetTemplateNames($campany->project_id, Template::TYPE_BLOCK))
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

        $campany->replacers = json_encode(array_values($rpl));

        return $campany->insert();
    }
}
