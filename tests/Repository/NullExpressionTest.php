<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Repository\Expression\NullExpression;
use Xakki\Emailer\Repository\expresion\NullExpresion;

/**
 * Verifies the deprecated typo alias still satisfies `instanceof` on the
 * correctly spelled class — that's the only contract AbstractRepository
 * cares about.
 */
class NullExpressionTest extends TestCase
{
    public function testDeprecatedAliasIsSubclassOfNewClass(): void
    {
        $old = new NullExpresion();
        self::assertInstanceOf(NullExpression::class, $old);
    }

    public function testNewClassInstanceWorksDirectly(): void
    {
        $sentinel = new NullExpression();
        self::assertInstanceOf(NullExpression::class, $sentinel);
    }
}
