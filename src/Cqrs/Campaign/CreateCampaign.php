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
    protected Template $tplWrapper;
    protected Template $tplContent;
    protected Model\Notify $notify;
    protected ?Model\Transport $transport;
    protected int $limitDay;
    /**
     * @var string[]
     */
    protected array $params;

    /**
     * @param Model\Project $project
     * @param string $subject
     * @param Template $tplWrapper
     * @param Template $tplContent
     * @param Model\Notify $notify
     * @param Model\Transport|null $transport
     * @param string[] $params
     * @param int $limitDay
     */
    public function __construct(
        Model\Project $project,
        string $subject,
        Template $tplWrapper,
        Template $tplContent,
        Model\Notify $notify,
        ?Model\Transport $transport = null,
        array $params = [],
        int $limitDay = 500
    ) {
        $this->project = $project;
        $this->subject = $subject;
        $this->tplWrapper = $tplWrapper;
        $this->tplContent = $tplContent;
        $this->notify = $notify;
        $this->transport = $transport;
        $this->params = $params;
        $this->limitDay = $limitDay;
    }

    public function handler(): Model\Campaign
    {
        // Add campaign
        $campaign = new Model\Campaign();
        $campaign->project_id = $this->project->id;
        $campaign->tpl_wrapper_id = $this->tplWrapper->id;
        $campaign->tpl_content_id = $this->tplContent->id;
        $campaign->notify_id = $this->notify->id;
        $campaign->limit_day = $this->limitDay;
        $campaign->status = Model\Campaign::STATUS_ON;
        $campaign->name = $this->subject;
        if ($this->params) {
            $campaign->params = json_encode($this->params);
        }
        if ($this->transport) {
            $campaign->transport_id = $this->transport->id;
        }

        $campaign->replacers = json_encode($this->getRequiredParams($campaign));

        return $campaign->insert();
    }

    /**
     * @param Model\Campaign $campaign
     * @return string[]
     */
    protected function getRequiredParams(Model\Campaign $campaign): array
    {
        $rpl = $m = [];
        if (Tools::hasReplacer($this->subject, $m)) {
            $rpl = array_combine($m, $m);
        }

        if (Tools::hasReplacer($this->tplContent->html, $m)) {
            $rpl += array_combine($m, $m);
        }

        if (Tools::hasReplacer($this->tplWrapper->html, $m)) {
            $rpl += array_combine($m, $m);
        }

        unset($rpl[Template::TYPE_CONTENT]);

        $this->unSetKeys($rpl, Model\Queue::getParamsKeyGenerate());

        $this->unSetKeys($rpl, Tools::getLocale(Template::LOCALE_DEFAULT, 'view'));

        $blocks = (new GetTemplateNames($campaign->project_id, Template::TYPE_BLOCK))
            ->handler();
        $this->unSetKeys($rpl, $blocks);

        $this->unSetKeys($rpl, $this->project->getParams());

        return array_keys($rpl);
    }

    /**
     * @param array<string, string> $arr
     * @param array<string, string> $exclude
     * @return void
     */
    protected function unSetKeys(array &$arr, array $exclude): void
    {
        foreach ($exclude as $k => $val) {
            if (isset($arr[$k])) {
                unset($arr[$k]);
            }
        }
    }
}
