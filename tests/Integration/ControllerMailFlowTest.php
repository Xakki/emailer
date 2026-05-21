<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Integration;

use Xakki\Emailer\Controller;
use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Helper\Tools;
use Xakki\Emailer\Mail;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Tests\Support\IntegrationCase;

/**
 * Exercises the public mail-tracking endpoints end-to-end against the real
 * (in-memory) database. Subclasses Controller\Mail to no-op headerSend so
 * the CLI runner stays quiet under failOnWarning="true".
 */
class ControllerMailFlowTest extends IntegrationCase
{
    private string $hashRoute;
    private Controller\Mail $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $project = (new Cqrs\Project\CreateProject('Demo', [
            Model\Template::NAME_HOST => 'demo.test',
            Model\Template::NAME_ROUTE => 'rdr',
            Model\Template::NAME_URL_LOGO => __DIR__ . '/../logo.png',
        ]))->handler();
        $notify = $project->createNotify('News');
        $wrapper = $project->createTplWrapper('wrap', '{{content}}');
        $content = $project->createTplContent('cont', 'Hi');
        $campaign = $project->createCampaign('Mail subject line', $wrapper, $content, $notify, []);

        Repository\Domain::insert(['name' => 'example.com']);
        Repository\Email::insert([
            'email' => 'foo@example.com', 'name' => 'Foo',
            'project_id' => $project->id, 'domain_id' => 1,
        ]);

        $_SERVER['HTTP_HOST'] = 'demo.test';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $this->hashRoute = $this->emailer->getNewSender($project->id, $campaign->id)
            ->send((new Mail())->setEmail('foo@example.com'));

        $this->controller = new class ($this->emailer) extends Controller\Mail {
            // phpcs:disable
            protected function headerSend(string $header, bool $replace = true, int $responseCode = 0): void
            {
            }
            // phpcs:enable
        };
        // Anonymous subclasses pollute get_called_class() so setViewDir's default
        // points at the wrong directory; pin it to the real view templates.
        $this->controller->setViewDir(dirname(__DIR__, 2) . '/src/view/Mail/');
    }

    public function testActionHome(): void
    {
        self::assertSame('', $this->controller->home($this->hashRoute));
        self::assertSame(1, Repository\Stats::findOne(['action' => Model\Stats::ACTION_HOME])['queue_id']);
    }

    public function testActionGoto(): void
    {
        $url = Tools::base64UrlEncode('https://example.com');
        self::assertSame('', $this->controller->goto($this->hashRoute, $url));
    }

    public function testActionLogoimgFallsBackOnValidationError(): void
    {
        $img = $this->controller->logoimg('bad-key');
        self::assertNotEmpty($img); // default image bytes
    }

    public function testActionUnsubscribeFlipsSubscriptionThenSubscribeFlipsBack(): void
    {
        $html = $this->controller->unsubscribe($this->hashRoute);
        self::assertStringContainsString('</body>', $html);
        self::assertStringNotContainsString('{{unsubscribe.title}}', $html);
        self::assertSame(Model\Subscribe::STATUS_OFF, Repository\Subscribe::findOne(['email_id' => 1])['status']);

        $html = $this->controller->subscribe($this->hashRoute);
        self::assertStringContainsString('</body>', $html);
        self::assertSame(Model\Subscribe::STATUS_ON, Repository\Subscribe::findOne(['email_id' => 1])['status']);
    }

    public function testActionStatus(): void
    {
        $html = $this->controller->status($this->hashRoute);
        self::assertStringContainsString('</body>', $html);
        // queueStatus.html embeds {{queueStatus}} via initReplacer + initial NEW status.
        self::assertStringNotContainsString('{{queueStatus}}', $html);
    }

    public function testRunDispatchesUnknownActionToErrorAction(): void
    {
        $this->expectException(\Xakki\Emailer\Exception\DataNotFound::class);
        // __call -> run -> errorAction (Wrong action) -> throws DataNotFound.
        // @phpstan-ignore-next-line - dynamic call by design
        $this->controller->nosuch();
    }

    public function testInitQueueRejectsMalformedKey(): void
    {
        // Use Status path which calls initQueue; the controller surfaces the Validation
        // error from the run() pipeline (no try/catch around initQueue in actionStatus).
        $this->expectException(\Xakki\Emailer\Exception\Validation::class);
        $this->controller->status('nodash');
    }

    public function testRenderImageWithExistingFile(): void
    {
        // Pass a real PNG path so renderImage exercises the file_exists branch.
        $controller = new class ($this->emailer) extends Controller\Mail {
            // phpcs:disable
            protected function headerSend(string $header, bool $replace = true, int $responseCode = 0): void
            {
            }
            // phpcs:enable
            public function renderImageProxy(string $file): string
            {
                return $this->renderImage($file);
            }
        };
        $bytes = $controller->renderImageProxy(__DIR__ . '/../logo.png');
        self::assertNotEmpty($bytes);
    }

    public function testRenderViewThrowsForUnknownTemplate(): void
    {
        $controller = new class ($this->emailer) extends Controller\Mail {
            // phpcs:disable
            protected function headerSend(string $header, bool $replace = true, int $responseCode = 0): void
            {
            }
            // phpcs:enable
            public function renderViewProxy(string $view): string
            {
                return $this->renderView($view, []);
            }
        };
        $this->expectException(\Xakki\Emailer\Exception\Exception::class);
        $controller->renderViewProxy('nope.html');
    }
}
