<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Mail;

class MailTest extends TestCase
{
    use Mocks;

    public function testSuccess1(): void
    {
        $replacer = [
            'test' => 'Some message',
        ];
        $mailData = [
            'email' => 'test@example.com',
            'emailName' => 'Mr. Example',
            'subject' => 'Test subject',
            'replyTo' => ['reply@example.com' => 'Mr. Reply'],
            'descr' => 'This description',
            'body' => 'This boy',
        ] + $replacer;
        $campaignData = [
            'id' => 1,
            'project_id' => 1,
            'name' => 'Test campaign',
            'created' => date('Y-m-d H:i:s'),
            'replacers' => json_encode(array_keys($replacer)),
        ];

        $mail = new Mail();
        $mail->setData($mailData);
        $campaign = $this->mockCampaign($campaignData);
        $mail->validate($campaign->getRequiredParams());
        self::assertEquals($mailData, $mail->getData());
    }

    public function testSuccess2(): void
    {
        $replacer = [];
        $mailData = [
            'email' => 'test@example.com',
        ];
        $campaignData = [
            'id' => 1,
            'project_id' => 1,
            'name' => 'Test campaign',
            'created' => date('Y-m-d H:i:s'),
            'replacers' => json_encode(array_keys($replacer)),
        ];

        $mail = new Mail();
        $mail->setData($mailData);
        $campaign = $this->mockCampaign($campaignData);

        $mail->validate($campaign->getRequiredParams());
        self::assertIsBool(true);
//        self::assertEquals($mailData + ['subject' => $campaign->name], $mail->getData());
    }

    /**
     * @dataProvider errorDataProvider
     * @param array<mixed> $mailData
     * @param array<mixed> $replacer
     * @param int $expectCode
     * @param string $expectMess
     * @return void
     * @throws Validation
     */
    public function testErrors(array $mailData, array $replacer, int $expectCode, string $expectMess): void
    {
        $this->expectException(Validation::class);
        $this->expectExceptionCode($expectCode);
        $this->expectExceptionMessage($expectMess);
        $campaignData = [
            'id' => 1,
            'project_id' => 1,
            'name' => 'Test campaign',
            'created' => date('Y-m-d H:i:s'),
            'replacers' => json_encode(array_keys($replacer)),
        ];
        $mail = new Mail();
        $mail->setData($mailData);
        $campaign = $this->mockCampaign($campaignData);

        $mail->validate($campaign->getRequiredParams());
    }

    /**
     * @return array<mixed>
     */
    public function errorDataProvider(): array
    {
        return [
            'Error. Email is required' => [
                'mailData' => [],
                'replacer' => [],
                'expectCode' => Validation::CODE_REQUIRE,
                'expectMess' => 'Email is required',
            ],
            'Error. Invalid Email' => [
                'mailData' => [
                    'email' => 'test',
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_EMAIL_BAD,
                'expectMess' => 'Invalid Email',
            ],
            'EmailName too long.' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                    'emailName' => str_repeat('long long ', 30),
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'EmailName too long. Max 255 chars.',
            ],
//            'Error. Subject is required' => [
//                'mailData' => [
//                    'email' => 'test@xakki.ru'],
//                'replacer' => [],
//                'expectCode' =>emailer\exception\Validation::CODE_REQUIRE,
//                'expectMess' => 'Subject is required',
//            ],
            'Error. Too small subject' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                    'subject' => 'small',
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'Subject: too small. Min 10 chars.',
            ],
            'Error. Subject: too long.' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                    'subject' => str_repeat('long ', 56),
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'Subject: too long. Max 255 chars.',
            ],
            'Error. Descr: too long.' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                    'descr' => str_repeat('long ', 205),
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'Descr: too long. Max 1024 chars.',
            ],
            'Error. Invalid replyTo' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                    'replyTo' => ['qwee'],
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'ReplyTo: allow array with string key and value.',
            ],
            'Error. Bad email in replyTo' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                    'replyTo' => ['qwee' => 'test'],
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_EMAIL_BAD,
                'expectMess' => 'Invalid Email',
            ],
            'Error. Data: Too much count' => [
                'mailData' => array_fill(0, 101, 'test'),
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'Data: Too much count',
            ],
            'Error. Data wrong key' => [
                'mailData' => ['qweqwe'],
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'Data: allow only `string` key',
            ],
            'Error. Data wrong item' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                    'test' => [1, 2, 3],
                ],
                'replacer' => [],
                'expectCode' => Validation::CODE_WRONG_VALUE,
                'expectMess' => 'Data: allow only `string` and `int` item. Wrong key `test`',
            ],
            'Error. Data: miss replacers ' => [
                'mailData' => [
                    'email' => 'test@xakki.ru',
                ],
                'replacer' => [
                    'test' => 'value',
                ],
                'expectCode' => Validation::CODE_DATA_MISS,
                'expectMess' => 'Data: miss replacers `test`',
            ],
        ];
    }
}
