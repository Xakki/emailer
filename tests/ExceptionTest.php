<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Exception\AccessFail;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Exception\Exception;
use Xakki\Emailer\Exception\Transport\AbstractTransportException;
use Xakki\Emailer\Exception\Transport\SpamException;
use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Exception\Validations;

class ExceptionTest extends TestCase
{
    public function testHttpCodesAndTitles(): void
    {
        self::assertSame(500, (new Exception('x'))->httpCode);
        self::assertSame('Error', (new Exception('x'))->title);

        self::assertSame(450, (new Validation('x'))->httpCode);
        self::assertSame('Validation', (new Validation('x'))->title);

        self::assertSame(404, (new DataNotFound('x'))->httpCode);
        self::assertSame('Not Found', (new DataNotFound('x'))->title);

        self::assertSame(401, (new AccessFail('x'))->httpCode);

        self::assertSame('Transport Error', (new AbstractTransportException('x'))->title);
        self::assertSame('Spam detected', (new SpamException('x'))->title);
    }

    public function testValidationsSuccess(): void
    {
        $data = [
            ['field-a', Validation::CODE_REQUIRE],
            ['field-b', Validation::CODE_REQUIRE],
        ];
        $v = new Validations($data);
        self::assertSame('Validations', $v->title);
        self::assertSame(Validations::CODE_VALIDATIONS, $v->getCode());
        $out = $v->getData();
        self::assertCount(2, $out);
        self::assertSame('is require', $out[0][1]);
    }

    public function testValidationsRejectsNonStringField(): void
    {
        $this->expectException(\Exception::class);
        new Validations([[42, Validation::CODE_REQUIRE]]);
    }

    public function testValidationsRejectsUnknownCode(): void
    {
        $this->expectException(\Exception::class);
        new Validations([['field', 9999]]);
    }
}
