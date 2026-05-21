<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Controller;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Helper\Tools;
use Xakki\Emailer\Model\Queue;
use Xakki\Emailer\Model\Stats;
use Xakki\Emailer\Model\Subscribe;
use Xakki\Emailer\Model\Template;
use Xakki\Emailer\test\phpunit\Mocks;

class MailTest extends TestCase
{
    use Mocks;

    protected function setUp(): void
    {
        $_SERVER['HTTP_HOST'] = $this->projectParams[Template::NAME_HOST];
        $_SERVER['HTTP_USER_AGENT'] = 'test';
    }

    public function testHome(): void
    {
        $dbExpects = [
            'insert' => [
                [
                    'return' => 1,
                    'args' => ['browser', ['ua' => 'test'], []],
                ],
                [
                    'return' => 1,
                    'args' => ['stats', ['project_id' => 1, 'queue_id' => 1, 'browser_id' => 1, 'action' => Stats::ACTION_HOME], []],
                ],
            ],
        ];
        $emailer = $this->mockEmailerSuccess($dbExpects);
        $queue = $this->mockQueue(1, $emailer);
        $mail = $this->mockControllerMail($queue, $emailer);
        $mail->expects(self::once())
            ->method('headerSend');

        $key = $queue->getHashRoute();
        self::assertEquals('', $mail->home($key));
    }

    public function testGoto(): void
    {
        $url = 'https://example.com';
        $dbExpects = [
            'insert' => [
                [
                    'return' => 1,
                    'args' => ['browser', ['ua' => $_SERVER['HTTP_USER_AGENT']], []],
                ],
                [
                    'return' => 1,
                    'args' => ['stats', ['project_id' => 1, 'queue_id' => 1, 'browser_id' => 1, 'action' => Stats::ACTION_GOTO], []],
                ],
            ],
        ];
        $emailer = $this->mockEmailerSuccess($dbExpects);
        $queue = $this->mockQueue(1, $emailer);
        $mail = $this->mockControllerMail($queue, $emailer);
        $mail->expects(self::once())
            ->method('headerSend');

        $mail
            ->method('redirect')
            ->with($url);

        $key = $queue->getHashRoute();
        self::assertEquals('', $mail->goto($key, Tools::base64UrlEncode($url)));
    }

    public function testLogoimg(): void
    {
        $dbExpects = [
            'insert' => [
                [
                    'return' => 1,
                    'args' => ['browser', ['ua' => $_SERVER['HTTP_USER_AGENT']], []],
                ],
                [
                    'return' => 1,
                    'args' => ['stats', ['project_id' => 1, 'queue_id' => 1, 'browser_id' => 1, 'action' => Stats::ACTION_READ], []],
                ],
            ],
        ];
        $emailer = $this->mockEmailerSuccess($dbExpects);
        $queue = $this->mockQueue(1, $emailer);
        $mail = $this->mockControllerMail($queue, $emailer);
        $mail->expects(self::once())
            ->method('headerSend');
        $mail
            ->method('renderImage')
            ->with($this->projectParams[Template::NAME_URL_LOGO]);

        $key = $queue->getHashRoute();
        self::assertNotEmpty($mail->logoimg($key));
    }

    public function testUnsubscribe(): void
    {
        $dbExpects = [
            'insert' => [
                [
                    'return' => 1,
                    'args' => ['subscribe', ['notify_id' => 1, 'email_id' => 1, 'project_id' => 1, 'period' => 600, 'status' => Subscribe::STATUS_OFF], []],
                ],
                [
                    'return' => 1,
                    'args' => ['browser', ['ua' => $_SERVER['HTTP_USER_AGENT']], []],
                ],
                [
                    'return' => 1,
                    'args' => ['stats', ['project_id' => 1, 'queue_id' => 1, 'browser_id' => 1, 'action' => Stats::ACTION_UNSUB], []],
                ],
            ],
            'update' => [
                [
                    'return' => 1,
                    'args' => ['stats', ['project_id' => 1, 'queue_id' => 1, 'browser_id' => 1, 'action' => Stats::ACTION_UNSUB], []],
                ],
            ],
        ];
        $emailer = $this->mockEmailerSuccess($dbExpects);
        $queue = $this->mockQueue(1, $emailer);
        $mail = $this->mockControllerMail($queue, $emailer);
        $mail->expects(self::once())
            ->method('headerSend');

        $mail
            ->method('renderView')
            ->with('unsubscribe.html', $queue->initReplacer());

        $key = $queue->getHashRoute();
        self::assertEquals('', $mail->unsubscribe($key));
    }

    public function testSubscribe(): void
    {
        $dbExpects = [
            'insert' => [
                [
                    'return' => 1,
                    'args' => ['subscribe', ['notify_id' => 1, 'email_id' => 1, 'project_id' => 1, 'period' => 600, 'status' => Subscribe::STATUS_ON], []],
                ],
                [
                    'return' => 1,
                    'args' => ['browser', ['ua' => $_SERVER['HTTP_USER_AGENT']], []],
                ],
                [
                    'return' => 1,
                    'args' => ['stats', ['project_id' => 1, 'queue_id' => 1, 'browser_id' => 1, 'action' => Stats::ACTION_SUBS], []],
                ],
            ],
            'update' => [
                [
                    'return' => 1,
                    'args' => ['stats', ['project_id' => 1, 'queue_id' => 1, 'browser_id' => 1, 'action' => Stats::ACTION_UNSUB], []],
                ],
            ],
        ];
        $emailer = $this->mockEmailerSuccess($dbExpects);
        $queue = $this->mockQueue(1, $emailer);
        $mail = $this->mockControllerMail($queue, $emailer);
        $mail->expects(self::once())
            ->method('headerSend');
        $mail
            ->method('renderView')
            ->with('subscribe.html', $queue->initReplacer());

        $key = $queue->getHashRoute();
        self::assertEquals('', $mail->subscribe($key));
    }

    /**
     * @param MockObject&Queue $queue
     * @return MockObject&Controller\Mail
     */
    protected function mockControllerMail(MockObject&Queue $queue, MockObject&Emailer $emailer): MockObject&Controller\Mail
    {
        $mock = $this->getMockBuilder(Controller\Mail::class)
            ->setConstructorArgs([$emailer])
            ->onlyMethods(['getQueueById', 'redirect', 'renderImage', 'renderView', 'headerSend'])
            ->getMock();

        $mock
            ->method('getQueueById')
            ->willReturn($queue);

        $mock
            ->method('renderImage')
            ->willReturn('logo');

        return $mock;
    }
}
