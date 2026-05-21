<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Mail;

class SenderTest extends TestCase
{
    use Mocks;

    public function testSend(): void
    {
        $dbExpects = [
//            'fetchAssociative' => [
//                'return' => ['id' => 1, 'domain_id' => 1, 'email' => '', 'name' => '',  'status' => '', ],
//                ['SELECT * FROM email WHERE id=:id LIMIT 1', ['id' => 1], []],
//            ],
        ];
        $emailer = $this->mockEmailerSuccess($dbExpects);
        $project = $emailer->getProject(1);
        $campaign = $project->getCampaign(1);

        $mailData = [
            'email' => 'test@example.com',
            'emailName' => 'Mr. Example',
            'subject' => 'Test subject',
            'replyTo' => ['reply@example.com' => 'Mr. Reply'],
            'descr' => 'This description',
            'body' => 'This boy',
        ] + $this->campaignReplacer;

        $mail = new Mail();
        $mail->setData($mailData);

        $sender = $this->mockSender($emailer, $project->id, $campaign->id);
        self::assertTrue(is_string($sender->send($mail)));
    }
}
