<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Model;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Model\AbstractModel;

class AbstractModelTest extends TestCase
{
    public function testConstruct(): void
    {
        $mock = $this->mockAbstractModel(['id' => 1]);
        self::assertSame(1, $mock->id);
    }

    public function testSetPkAndGetPk(): void
    {
        $mock = $this->mockAbstractModel();
        $mock->setPk(42);
        self::assertSame(42, $mock->getPk());
        self::assertSame('id', $mock::getPkName());
    }

    /**
     * @param array<string, mixed> $input
     */
    public function mockAbstractModel(array $input = []): AbstractModel
    {
        return new AbstractModelMock($input);
    }
}
