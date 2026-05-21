<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit\Repository;

use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Repository\AbstractRepository;
use Xakki\Emailer\test\phpunit\DbConnection;
use Xakki\Emailer\test\phpunit\Mocks;

class AbstractRepositoryTest extends TestCase
{
    use Mocks;

    public function testSaveNew(): void
    {
        $id = 100;
        $data = ['name' => 'test'];
        $types = ['id' => ParameterType::INTEGER, 'name' => ParameterType::STRING];

        $expects = [
            'insert' => [
                [
                    'return' => 1,
                    'args' => ['test', ['name' => 'test'], $types],
                ],
            ],
        ];
        $emailer = $this->mockEmailerSuccess($expects);
        /** @var DbConnection $db */
        $db = $emailer->getDb();
        $mock = $this->mockAbstractRepository($db);
        $db->lastId = $id;

        self::assertEquals($id, $mock::save($data, $types));
    }

    public function testSaveExist(): void
    {
        $id = 200;
        $data = ['id' => $id, 'name' => 'test'];
        $types = ['id' => ParameterType::INTEGER, 'name' => ParameterType::STRING];

        $expects = [
            'update' => [
                [
                    'return' => 1,
                    'args' => ['test', ['name' => 'test'], ['id' => $id], $types],
                ],
            ],
        ];

        $emailer = $this->mockEmailerSuccess($expects);
        /** @var DbConnection $db */
        $db = $emailer->getDb();
        $mock = $this->mockAbstractRepository($db);
        $db->lastId = $id;

        self::assertEquals($id, $mock::save($data, $types));
    }

    public function mockAbstractRepository(DbConnection $db): AbstractRepository
    {
        return new AbstractRepositoryMock($db);
    }
}
