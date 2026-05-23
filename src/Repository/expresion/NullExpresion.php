<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository\expresion;

use Xakki\Emailer\Repository\Expression\NullExpression;

/**
 * @deprecated 1.1 use {@see \Xakki\Emailer\Repository\Expression\NullExpression}.
 *             Both the namespace (`expresion`) and the class (`NullExpresion`)
 *             carried typos; this empty subclass keeps `instanceof` checks
 *             working for callers that imported the old name. Slated for
 *             removal in v2.
 */
class NullExpresion extends NullExpression
{
}
