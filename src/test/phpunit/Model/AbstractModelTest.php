<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit\Model;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Model\AbstractModel;
use Xakki\Emailer\test\phpunit\Mocks;

class AbstractModelTest extends TestCase
{
    use Mocks;

    public function testConstruct(): void
    {
        $mock = $this->mockAbstractModel();
        self::assertEquals(1, $mock->id);
    }

    public function mockAbstractModel(): AbstractModel
    {
        return $this->getMockForAbstractClass(AbstractModel::class, [['id' => 1]]);
    }
}
