<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Model\Campaign;
use Xakki\Emailer\Model\Domain;
use Xakki\Emailer\Model\Email;
use Xakki\Emailer\Model\Notify;
use Xakki\Emailer\Model\Project;
use Xakki\Emailer\Model\Queue;
use Xakki\Emailer\Model\Template;
use Xakki\Emailer\Sender;

trait Mocks
{
    /** @var string[] */
    protected array $projectParams = [
        Template::NAME_URL_LOGO => __DIR__ . '/logo.png',
        Template::NAME_ROUTE => 'rdr',
        Template::NAME_HOST => 'test.com',
    ];
    /** @var string[] */
    protected array $campaignReplacer = [
        'test' => 'Some message',
    ];

    protected function mockLogger(): MockObject|Logger
    {
//        return new Logger('test');
        $mock = $this->getMockBuilder(Logger::class)
            ->setConstructorArgs(['test'])
            ->onlyMethods(['log']);
        return $mock->getMock();
    }

    /**
     * @param array<mixed> $expects
     * @return MockObject|DbConnection
     */
    protected function mockDb(array $expects = []): MockObject|DbConnection
    {
        $eventManager = new EventManager();
        $driverMock   = $this->createMock(Driver::class);
        $driverMock->expects(self::any())
            ->method('connect')
            ->willReturn(
                $this->createMock(DriverConnection::class)
            );
        $methodMock = ['executeStatement', 'isTransactionActive'];
        foreach ($expects as $m => $args) {
            $methodMock[] = $m;
        }

        $platform = $this->getMockBuilder(\Doctrine\DBAL\Platforms\SqlitePlatform::class)
            ->onlyMethods([])
            ->getMock();
        $options  = [
            'url' => 'sqlite::memory:',
            'platform' => $platform,
        ];
        $db = $this->getMockBuilder(DbConnection::class)
            ->setConstructorArgs([
                $options,
                $driverMock,
                new Configuration(),
                $eventManager,
            ])
            ->onlyMethods($methodMock)
            ->getMock();

        $db
            ->method('executeStatement')
            ->willReturn(1);
        $db
            ->method('isTransactionActive')
            ->willReturn(true);
//        $db
//            ->method('lastInsertId')
//            ->willReturn(1);

        foreach ($expects as $m => $row) {
            $args = $returns = [];
            foreach ($row as $item) {
                $args[] = $item['args'];
                $returns[] = $item['return'];
            }
            $db
                ->method($m)
                ->withConsecutive(...$args)
                ->willReturnOnConsecutiveCalls(...$returns);
        }
        return $db;
    }

    /**
     * @param Project $project
     * @param array<mixed> $dbExpects
     * @return MockObject|Emailer
     */
    protected function mockEmailer(Project $project, array $dbExpects): MockObject|Emailer
    {
        $config = new ConfigService();
        $config->db['password'] = 'testpass123';
        $mock = $this->getMockBuilder(Emailer::class)
            ->setConstructorArgs([$config, $this->mockLogger()])
            ->onlyMethods(['getDb', 'getProject'])
            ->getMock();

        $mock
            ->expects(self::any())
            ->method('getDb')
            ->willReturn($this->mockDb($dbExpects));

        $mock
            ->method('getProject')
            ->willReturn($project);

        return $mock;
    }

    /**
     * @param array<mixed> $dbExpects
     * @return Emailer|MockObject
     */
    protected function mockEmailerSuccess(array $dbExpects = []): Emailer|MockObject
    {
        $campaignData = [
            'id' => 1,
            'project_id' => 1,
            'name' => 'Test Campaign',
            'created' => date('Y-m-d H:i:s'),
            'replacers' => json_encode(array_keys($this->campaignReplacer)),
            'cnt_queue' => 0,
            'transport_id' => 1,
        ];
        $projectData = [
            'id' => 1,
            'name' => 'Test',
            'created' => date('Y-m-d H:i:s'),
            'status' => Project::STATUS_ON,
            'params' => json_encode($this->projectParams),
            'token' => '',
        ];
        $company = $this->mockCampaign($campaignData);
        $project = $this->mockProject($projectData, $company);
        return $this->mockEmailer($project, $dbExpects);
    }

    protected function mockSender(Emailer $emailer, int $projectId, int $campaignId): Sender|MockObject
    {
        $mock = $this->getMockBuilder(Sender::class)
            ->setConstructorArgs([$emailer, $projectId, $campaignId])
            ->onlyMethods(['buildNewQueue'])
            ->getMock();

        $mock
            ->method('buildNewQueue')
            ->willReturn($this->mockQueueNew($emailer));

        return $mock;
    }

    /**
     * @param array<mixed> $projectData
     * @param Campaign $campaign
     * @return MockObject|Project
     */
    protected function mockProject(array $projectData, Campaign $campaign): MockObject|Project
    {
        $mock = $this->getMockBuilder(Project::class)
            ->setConstructorArgs([$projectData])
            ->onlyMethods(['findAll', 'findOne', 'insert', 'update', 'getCampaign'])
            ->getMock();

        $mock
            ->method('getCampaign')
            ->willReturn($campaign);
        $this->mockMethodInsert($mock);

        return $mock;
    }

    /**
     * @param array<mixed> $campaignData
     * @return MockObject|Campaign
     */
    protected function mockCampaign(array $campaignData): MockObject|Campaign
    {
        $notify = $this->mockNotify($campaignData['project_id']);
        $campaignData['notify_id'] = $notify->id;
        $mock = $this->getMockBuilder(Campaign::class)
            ->setConstructorArgs([$campaignData])
            ->onlyMethods(['findAll', 'findOne', 'insert', 'update', 'getNotify'])
            ->getMock();
        $this->mockMethodInsert($mock);

        $mock
            ->method('getNotify')
            ->willReturn($notify);

        return $mock;
    }

    protected function mockQueueNew(MockObject|Emailer $emailer): MockObject|Queue
    {
        /** @var MockObject|Queue $mock */
        $mock = $this->getMockBuilder(Queue::class)
            ->onlyMethods([
                'findAll',
                'findOne',
                'insert',
                'update',
                'getCampaign',
                'getProject',
                'getEmailModel',
                'isActiveSubscribe',
//                'lockForUpdate',
//                'executeQueue',
            ])
            ->getMock();

        $project = $emailer->getProject(1);
        $mock->project_id = $project->id;
        $mock
            ->method('getProject')
            ->willReturn($project);

        $campaign = $project->getCampaign(1);
        $mock->campaign_id = $campaign->id;
        $mock->notify_id = $campaign->notify_id;
        $mock
            ->method('getCampaign')
            ->willReturn($campaign);

        $mock->email_id = 1;
        $mock->created = date('Y-m-d H:i:s');
        $mock
            ->method('getEmailModel')
            ->willReturn($this->mockEmail());

        $mock
            ->method('isActiveSubscribe')
            ->willReturn(true);
//        $mock
//            ->method('lockForUpdate')
//            ->willReturnSelf();
        $this->mockMethodInsert($mock);

        return $mock;
    }

    protected function mockQueue(int $id, MockObject|Emailer $emailer): MockObject|Queue
    {
        // @phpstan-ignore-next-line
        $emailer->getDb()->lastId = 1;
        /** @var Queue $mock */
        $mock = $this->mockQueueNew($emailer);
        $mock->id = $id;
        $mock->readed = '';
        $mock->created = date('Y-m-d H:i:s');
        return $mock;
    }

    protected function mockEmail(): MockObject|Email
    {
        $data = [
            'id' => 1,
            'domain_id' => 1,
            'email' => 'test@example.com',
            'name' => 'test',
            'status' => 'on',
            'created' => date('Y-m-d H:i:s'),
            'cnt_send' => 0,
            'cnt_read' => 0,
            'project_id' => 1,
            'domain' => $this->mockDomain(),
        ];
        $mock = $this->getMockBuilder(Email::class)
            ->setConstructorArgs([$data])
            ->onlyMethods(['findAll', 'findOne', 'insert', 'update'])
            ->getMock();
        $this->mockMethodInsert($mock);

        return $mock;
    }

    protected function mockDomain(): MockObject|Domain
    {
        $data = [
            'id' => 1,
            'name' => 'example.com',
            'mx' => '',
        ];
        $mock = $this->getMockBuilder(Domain::class)
            ->setConstructorArgs([$data])
            ->onlyMethods(['findAll', 'findOne', 'insert', 'update'])
            ->getMock();
        $this->mockMethodInsert($mock);

        return $mock;
    }

    protected function mockNotify(int $projectId): MockObject|Notify
    {
        $data = [
            'id' => 1,
            'created' => date('Y-m-d H:i:s'),
            'name' => 'Test Notify',
            'project_id' => $projectId,
        ];
        $mock = $this->getMockBuilder(Notify::class)
            ->setConstructorArgs([$data])
            ->onlyMethods(['findAll', 'findOne', 'insert', 'update'])
            ->getMock();
        $this->mockMethodInsert($mock);

        return $mock;
    }

    protected function mockMethodInsert(MockObject $mock): void
    {
        $mock
            ->method('insert')
            ->willReturnCallback(
                function () use ($mock) {
                    // @phpstan-ignore-next-line
                    $mock->id = 1;
                    return $mock;
                }
            );
    }
}
