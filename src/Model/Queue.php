<?php

declare(strict_types=1);

namespace Xakki\Emailer\Model;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Exception;
use Xakki\Emailer\Helper;
use Xakki\Emailer\Mail;
use Xakki\Emailer\Repository;

class Queue extends AbstractModel
{
    public const QUEUE_STATUS_SUCCESS = 2;
    public const QUEUE_STATUS_RUN = 1;
    public const QUEUE_STATUS_NEW = 0;
    public const QUEUE_STATUS_UNSUBSCRIBE = -1;
    public const QUEUE_STATUS_SKIP = -2;
    public const QUEUE_STATUS_QUOTA = -3;
    public const QUEUE_STATUS_SPAM = -4;
    public const QUEUE_STATUS_INVALID_MAIL = -10;
    public const QUEUE_STATUS_INVALID_SMTP = -11;
    public const QUEUE_STATUS_INVALID_DOMAIN = -12;
    public const QUEUE_STATUS_ERROR = -20;
    public const QUEUE_STATUS_TEMP_ERROR = -21;

    public const TITLE_QUEUE_STATUS = [
        self::QUEUE_STATUS_SUCCESS => 'success',
        self::QUEUE_STATUS_RUN => 'run',
        self::QUEUE_STATUS_NEW => 'new',
        self::QUEUE_STATUS_UNSUBSCRIBE => 'unsubscribe',
        self::QUEUE_STATUS_SKIP => 'skip',
        self::QUEUE_STATUS_QUOTA => 'quota',
        self::QUEUE_STATUS_INVALID_MAIL => 'invalid email',
        self::QUEUE_STATUS_INVALID_SMTP => 'invalid smtp',
        self::QUEUE_STATUS_INVALID_DOMAIN => 'invalid domain',
        self::QUEUE_STATUS_ERROR => 'default error',
        self::QUEUE_STATUS_TEMP_ERROR => 'temporary error',
    ];

    public int $id;
    public string $created;
    public ?string $sended;
    public ?string $readed;
    public int $status;
    public int $retry;
    public int $project_id;
    public int $campany_id;
    public int $notify_id;
    public int $email_id;
    protected ?Mail $mail = null;
    /** @var array<string, string> */
    protected array $tpl_blocks = [];
    protected string $urlRoute = '';
    protected bool $routeModeIsPath = false;

    protected static function repositoryClass(): string
    {
        return Repository\Queue::class;
    }

    public function insert(): static
    {
        parent::insert();
        $queueData = new QueueData(['id' => $this->getPk(), 'data' => json_encode($this->mail, JSON_UNESCAPED_UNICODE)]);
        $queueData->insert();
        return $this;
    }

    public function updateTransportId(int $transport_id): void
    {
        Repository\QueueData::updateById($this->getPk(), ['transport_id' => $transport_id]);
    }

    public function updateLastError(string $lastError): void
    {
        $lastError = substr($lastError, 0, 255);
        Repository\QueueData::updateById($this->getPk(), ['last_error' => $lastError]);
    }

    public function setReaded(): void
    {
        if (!$this->readed) {
            $this->readed = date('Y-m-d H:i:s');
            $this->update(['readed']);
        }
    }

    public function setSended(): self
    {
        $this->sended = date('Y-m-d H:i:s');
        $this->status = static::QUEUE_STATUS_SUCCESS;
        $this->update(['sended', 'status']);
//        $this->save(['sended' => new \DateTime(), 'status' => static::QUEUE_STATUS_SUCCESS], [\Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, \Doctrine\DBAL\Types\Types::INTEGER]);

        $this->getCampany()->incCntSend();
        return $this;
    }

    public function setMail(Mail $mail): self
    {
        $emailModel = $this->getEmailModel($mail->getEmail(), $mail->getEmailName());
        $this->email_id = $emailModel->id;
        $this->mail = $mail;

        $reply = $this->getProject()->getParam(Template::NAME_REPLY);
        if ($reply && !$mail->getReplyTo()) {
            if (is_string($reply)) {
                $reply = [$reply => $this->getProject()->name];
            }
            $mail->setReplyTo($reply);
        }
        return $this;
    }

    public function isActiveSubscribe(): bool
    {
        return (new Cqrs\Subscribe\IsActiveSubscribe($this))
                ->handler();
    }

    public function getMail(): Mail
    {
        if ($this->mail === null) {
            $row = Repository\QueueData::findOne(['id' => $this->id]);
            if ($row) {
                $this->mail = Mail::initFromJson($row['data']);
            } else {
                $this->mail = new Mail();
            }
        }
        return $this->mail;
    }

    public function getEmail(): Email
    {
        return (new Cqrs\Email\GetEmail($this->email_id))->handler();
    }

    public function getEmailModel(string $email, string $emailName): Email
    {
        return Email::getEmail($email, $emailName, $this->project_id);
    }

    public function getProject(): Project
    {
        return (new Cqrs\Project\GetProject($this->project_id))->handler();
    }

    public function getCampany(): Campany
    {
        return (new Cqrs\Campany\GetCampany($this->project_id, $this->campany_id))->handler();
    }

    public function getBody(): string
    {
        $blocks = $this->initReplacer();
        foreach ($this->getCampany()->getTplBlocks() as $tplBlock) {
            $blocks['{{' . $tplBlock->name . '}}'] = $tplBlock->html;
        }
        $blocks['{{content}}'] = $this->getCampany()->getTplContent()->html;
        $html = $this->getCampany()->getTplWraper()->html;
        $html = strtr($html, $blocks);
        $html = strtr($html, $blocks);
        $html = strtr($html, $blocks);
        $m = [];
        if (Helper\Tools::hasReplacer($html, $m)) {
            throw new Exception\Validation(sprintf(
                'Tpl #%s dont have replacer: %s',
                $this->getCampany()->getTplWraper()->id,
                implode(', ', $m)
            ), Exception\Validation::CODE_DATA_MISS);
        }

        return $html;
    }

    /**
     * @return array<string, string>
     * @throws Exception\Exception
     */
    public function initReplacer(): array
    {
        if (empty($this->tpl_blocks)) {
            //TODO
            $this->tpl_blocks = Helper\Tools::wrapReplacer($r);
        }
        return $this->tpl_blocks;
    }

    protected function getRouteUrl(string $action): string
    {
        $url = $this->urlRoute;

        if ($this->routeModeIsPath) {
            $url .= '/';
        } else {
            $url .= '&es=';
        }

        $url .= $action . '/' . $this->getHashRoute();

        return $url;
    }

    public function getHashRoute(): string
    {
        return Helper\Tools::base64UrlEncode($this->getHash() . '-' . $this->id);
    }

    public function getHash(): string
    {
        return md5($this->getProject()->token . '|' . $this->project_id . '|' . $this->campany_id . '|' . $this->email_id . '|' . $this->id);
    }

    protected function getHomeUrl(): string
    {
        return $this->getRouteUrl('home');
    }

    protected function getLogoUrl(): string
    {
        return $this->getRouteUrl('logoimg');
    }

    protected function getUrlUnsubscribe(): string
    {
        return $this->getRouteUrl('unsubscribe');
    }

    protected function getUrlSubscribe(): string
    {
        return $this->getRouteUrl('subscribe');
    }

    public function getSubject(): string
    {
        if (!empty($this->getMail()->getSubject())) {
            $txt = $this->getMail()->getSubject();
        } else {
            $txt = $this->getCampany()->name;
        }

        if (Helper\Tools::hasReplacer($txt)) {
            $txt = strtr($txt, $this->initReplacer());
        }

        return $txt;
    }

    public function getDescr(): string
    {
        if (empty($this->getMail()->getDescr())) {
            return '';
        }
        $txt = $this->getMail()->getDescr();

        if (Helper\Tools::hasReplacer($txt)) {
            $txt = strtr($txt, $this->initReplacer());
        }

        return $txt;
    }

    public function allowBodyAlt(): bool
    {
        return false;
    }

    public function getBodyAlt(): string
    {
        return '';
    }

    public function getMessageID(): string
    {
        return sprintf('<%s@%s>', $this->id, $this->tpl_blocks['{{' . Template::NAME_HOST . '}}']);
    }

    /**
     * @return array<string, string>
     */
    public function getCustomHeaders(): array
    {
        $url = $this->getUrlUnsubscribe();
        $customHeaders = [];
        $customHeaders['List-Unsubscribe'] = '<' . $url . '>';
        //TODO: <mailto:'.$this->urlRoute.'>
        $domain = parse_url($url, PHP_URL_HOST);
        $customHeaders['List-id'] = sprintf(
            '%s <list-%s-%s>',
            $this->getCampany()->getNotify()->name,
            $domain,
            $this->getCampany()->notify_id,
        );
        return $customHeaders;
    }

    public function isStatusPosibleRepeat(): bool
    {
        $list = [
            self::QUEUE_STATUS_QUOTA => 1,
            self::QUEUE_STATUS_SPAM => 1,
            self::QUEUE_STATUS_ERROR => 1,
            self::QUEUE_STATUS_TEMP_ERROR => 1,
        ];
        return isset($list[$this->status]);
    }
}
