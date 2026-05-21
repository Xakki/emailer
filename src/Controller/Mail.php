<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller;

use Xakki\Emailer\Cqrs\Stats\AppendStats;
use Xakki\Emailer\Cqrs\Subscribe\UpdateSubscribe;
use Xakki\Emailer\Exception;
use Xakki\Emailer\Helper;
use Xakki\Emailer\Model;

/**
 * @method index()
 * @method home(string $key)
 * @method goto(string $key, string $url)
 * @method logoimg(string $key)
 * @method unsubscribe(string $key)
 * @method subscribe(string $key)
 * @method status(string $key)
 * @method get(string $key, string $secret)
 */
class Mail extends AbstractController
{
    /**
     * @param string $name
     * @param array<string,mixed> $arguments
     * @return string
     */
    protected function run(string $name, array $arguments): string
    {
        $this->headerSend('Content-Type: text/html; charset=UTF-8');
        return parent::run($name, $arguments);
    }

    protected function getQueueById(int $id): Model\Queue
    {
        return Model\Queue::findOne(['id' => $id]);
    }

    protected function actionIndex(): string
    {
        return 'HELLO WORLD';
    }

    protected function actionHome(string $key): string
    {
        try {
            $this->initQueue($key, Model\Stats::ACTION_HOME);
        } catch (Exception\Validation $e) {
            $this->logger->warning($e, ['validation']);
        }
        // TODO: to poject url
        $url = '//' . $_SERVER['HTTP_HOST'];
        //$url .= 'utm_source=email&utm_medium=' . $aTplVars['user.enotify'] . '&utm_compaign=' . $aTplVars['user.type'] . '&utm_term=' . $aTplVars['tpl'];
        $this->redirect($url);
        return '';
    }

    protected function actionGoto(string $key, string $url): string
    {
        try {
            $this->initQueue($key, Model\Stats::ACTION_GOTO);
        } catch (Exception\Validation $e) {
            $this->logger->warning($e, ['validation']);
        }
        $url = Helper\Tools::base64UrlDecode($url);
        //$url .= 'utm_source=email&utm_medium=' . $aTplVars['user.enotify'] . '&utm_compaign=' . $aTplVars['user.type'] . '&utm_term=' . $aTplVars['tpl'];
        $this->redirect($url);
        return '';
    }

    protected function actionLogoimg(string $key): string
    {
        try {
            $queue = $this->initQueue($key, Model\Stats::ACTION_READ);
        } catch (Exception\Validation $e) {
            $this->logger->warning($e, ['validation']);
            return $this->renderImage(self::DEFAULT_IMAGE);
        }
        return $this->renderImage($queue->getProject()->getParam(Model\Template::NAME_URL_LOGO));
    }

    protected function actionUnsubscribe(string $key): string
    {
        $queue = $this->initQueue($key, Model\Stats::ACTION_UNSUB);
        $vars = $queue->initReplacer();
        return $this->renderView('unsubscribe.html', $vars);
    }

    protected function actionSubscribe(string $key): string
    {
        $queue = $this->initQueue($key, Model\Stats::ACTION_SUBS);
        $vars = $queue->initReplacer();
        return $this->renderView('subscribe.html', $vars);
    }

    protected function initQueue(string $key, int $action): Model\Queue
    {
        $key = Helper\Tools::base64UrlDecode($key);
        if (!$key || !str_contains($key, '-')) {
            throw new Exception\Validation('Bad request #1', Exception\Validation::CODE_REQUEST_BAD);
        }
        [$hash, $id] = explode('-', $key, 2);
        $id = (int) $id;
        if (!$hash || !$id) {
            throw new Exception\Validation('Bad request #2', Exception\Validation::CODE_REQUEST_BAD);
        }

        $queue = $this->getQueueById($id);

        if ($queue->getHash() != $hash) {
            throw new Exception\Validation('Bad request #3', Exception\Validation::CODE_REQUEST_BAD);
        }

        $queue->setReaded();

        if ($action == Model\Stats::ACTION_SUBS) {
            (new UpdateSubscribe($queue, Model\Subscribe::STATUS_ON))
                ->handler();
        } elseif ($action == Model\Stats::ACTION_UNSUB) {
            (new UpdateSubscribe($queue, Model\Subscribe::STATUS_OFF))
                ->handler();
        }

        (new AppendStats($queue, $action))
            ->handler();

        return $queue;
    }

    protected function actionStatus(string $key): string
    {
        $queue = $this->initQueue($key, Model\Stats::ACTION_STATUS);

        $vars = $queue->initReplacer();
        $vars['{{queueStatus}}'] = $queue::TITLE_QUEUE_STATUS[$queue->status] ?? 'unknown';

        return $this->renderView('queueStatus.html', $vars);
    }

    /**
     * Read-only e2e/test accessor: returns the rendered HTML body of one
     * queued email. SECURITY: opaque 404 on ANY failure (empty/missing
     * secret, mismatch, malformed key, unknown queue, render error) so the
     * route is not probeable and never reveals why. The secret compare is
     * constant-time; an unset SECRET_EMAILER_KEY ('') keeps the route dead.
     * Deliberately does NOT call initQueue(): must not mutate readed/stats
     * (do not "helpfully" refactor it to reuse initQueue).
     */
    protected function actionGet(string $key, string $secret): string
    {
        $configured = (string) $this->emailer->getConfig()->secret_key;
        if ($configured === '' || !hash_equals($configured, $secret)) {
            return $this->notFound();
        }

        $decoded = Helper\Tools::base64UrlDecode($key);
        if (!$decoded || !str_contains($decoded, '-')) {
            return $this->notFound();
        }
        [$hash, $id] = explode('-', $decoded, 2);
        $id = (int) $id;
        if ($hash === '' || $id <= 0) {
            return $this->notFound();
        }

        try {
            $queue = $this->getQueueById($id);
            if (!hash_equals((string) $queue->getHash(), $hash)) {
                return $this->notFound();
            }
            return $queue->getBody();
        } catch (\Throwable $e) {
            // Post-auth failure only: log queue id (never the secret/key).
            $this->logger->warning('emailer get failed', ['id' => $id, 'msg' => $e->getMessage()]);
            return $this->notFound();
        }
    }

    private function notFound(): string
    {
        http_response_code(404);
        return 'Not Found';
    }
}
