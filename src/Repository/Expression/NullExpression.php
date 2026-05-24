<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository\Expression;

/**
 * Sentinel value passed to Repository writes to force a column to SQL NULL.
 *
 * AbstractRepository::insert() / updateById() drop entries whose value is
 * PHP `null` (because callers may pass partial data); to explicitly persist
 * NULL, pass an instance of this class instead.
 */
class NullExpression
{
}
