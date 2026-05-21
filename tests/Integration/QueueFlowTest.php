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

class QueueFlowTest extends IntegrationCase
{
    private Model\Project $project;
    private Model\Campaign $campaign;
    private Model\Notify $notify;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = (new Cqrs\Project\CreateProject('Demo', [
            Model\Template::NAME_HOST => 'demo.test',
            Model\Template::NAME_ROUTE => 'rdr',
            Model\Template::NAME_URL_LOGO => __DIR__ . '/../logo.png',
        ]))->handler();

        $this->notify = $this->project->createNotify('News');
        $wrapper = $this->project->createTplWrapper('wrap', '{{content}}');
        $content = $this->project->createTplContent('cont', 'Hi');
        $this->project->createTplBlock('foot', 'bye');
        $this->campaign = $this->project->createCampaign(
            'My Test Subject Line',
            $wrapper,
            $content,
            $this->notify,
            []
        );

        // Pre-seed domain + email so Email::getEmail() finds an existing row
        // and never falls through to GetDomainIdByEmail (Redis-backed).
        Repository\Domain::insert(['name' => 'example.com']);
        Repository\Email::insert([
            'email' => 'foo@example.com', 'name' => 'Foo',
            'project_id' => $this->project->id, 'domain_id' => 1,
        ]);
    }

    public function testSenderSendsAndQueueRoundTrips(): void
    {
        $sender = $this->emailer->getNewSender($this->project->id, $this->campaign->id);
        $mail = (new Mail())->setEmail('foo@example.com')->setEmailName('Foo');

        $hashRoute = $sender->send($mail);
        self::assertNotEmpty($hashRoute);

        // Decode the hash route shape: base64UrlEncode(getHash() . '-' . id).
        $decoded = Tools::base64UrlDecode($hashRoute);
        self::assertStringContainsString('-', $decoded);
        [$hash, $id] = explode('-', $decoded);
        self::assertSame(1, (int) $id);

        $queue = Model\Queue::findOneById((int) $id);
        self::assertSame($hash, $queue->getHash());
        self::assertSame(Model\Queue::QUEUE_STATUS_NEW, $queue->status);
        self::assertSame(1, $queue->email_id);
        self::assertFalse($queue->isStatusPossibleRepeat());

        // Body rendering through wrapper + content + blocks.
        self::assertSame('Hi', $queue->getBody());

        // Subject falls back to campaign.name when mail has no subject.
        self::assertSame('My Test Subject Line', $queue->getSubject());
        self::assertSame('', $queue->getDescr());
        self::assertFalse($queue->allowBodyAlt());
        self::assertSame('', $queue->getBodyAlt());

        // Replacer/header helpers depend on initReplacer being warm.
        $replacers = $queue->initReplacer();
        self::assertArrayHasKey('{{' . Model\Template::NAME_HOST . '}}', $replacers);

        $headers = $queue->getCustomHeaders();
        self::assertArrayHasKey('List-Unsubscribe', $headers);
        self::assertArrayHasKey('List-id', $headers);

        $msgId = $queue->getMessageID();
        self::assertStringContainsString('@demo.test', $msgId);

        // setReaded() should write once and be idempotent.
        $queue->setReaded();
        $queue->setReaded();
        $reloaded = Model\Queue::findOneById($queue->id);
        self::assertNotNull($reloaded->readed);

        $queue->updateLastError(str_repeat('x', 1000));
        $queue->updateTransportId(0);

        // Mail round-trips through queue_data.
        $mail2 = $queue->getMail();
        self::assertSame('foo@example.com', $mail2->getEmail());
    }

    public function testSenderRefusesUnsubscribed(): void
    {
        // Mark the email as unsubscribed for this notify.
        (new Cqrs\Subscribe\UpdateSubscribe(
            new Model\Queue([
                'project_id' => $this->project->id,
                'notify_id' => $this->notify->id,
                'email_id' => 1,
            ]),
            Model\Subscribe::STATUS_OFF
        ))->handler();

        $this->expectException(\Xakki\Emailer\Exception\Validation::class);
        $this->emailer->getNewSender($this->project->id, $this->campaign->id)
            ->send((new Mail())->setEmail('foo@example.com'));
    }

    public function testActionGetReturnsBodyWhenSecretMatches(): void
    {
        $hashRoute = $this->emailer->getNewSender($this->project->id, $this->campaign->id)
            ->send((new Mail())->setEmail('foo@example.com'));

        // Configure the read-only get-secret.
        $secretProp = new \ReflectionProperty(\Xakki\Emailer\ConfigService::class, 'secret_key');
        $secretProp->setValue($this->emailer->getConfig(), 'super-secret');

        $controller = new Controller\Mail($this->emailer);

        self::assertSame('Hi', $controller->get($hashRoute, 'super-secret'));

        // Bad secret -> opaque 404 string.
        self::assertSame('Not Found', $controller->get($hashRoute, 'wrong'));
        // Empty secret config -> dead route.
        $secretProp->setValue($this->emailer->getConfig(), '');
        self::assertSame('Not Found', $controller->get($hashRoute, 'super-secret'));
        // Restore and pass a malformed key whose decoded form has no `-` separator.
        $secretProp->setValue($this->emailer->getConfig(), 'super-secret');
        $badKey = Tools::base64UrlEncode('nodash');
        self::assertSame('Not Found', $controller->get($badKey, 'super-secret'));
        // Well-formed shape but wrong hash also yields 404.
        $wrongHash = Tools::base64UrlEncode('zzzzzzzz-1');
        self::assertSame('Not Found', $controller->get($wrongHash, 'super-secret'));
    }

    public function testActionIndexReturnsHelloWorld(): void
    {
        $controller = new Controller\Mail($this->emailer);
        // suppress the real header() call by overriding via anonymous subclass would be
        // ideal, but in the CLI test runner header() with output buffering inside
        // PHPUnit is harmless — Mail::index goes through run() which sets a header
        // and then returns the body. Use a controller subclass to silence headerSend.
        $controller = new class ($this->emailer) extends Controller\Mail {
            protected function headerSend(string $header, bool $replace = true, int $responseCode = 0): void
            {
            }
        };
        self::assertSame('HELLO WORLD', $controller->index());
    }
}
