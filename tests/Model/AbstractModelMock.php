<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Model;

use Xakki\Emailer\Model\AbstractModel;
use Xakki\Emailer\Repository\Project as ProjectRepository;

/**
 * Concrete stand-in for the abstract model.
 * PHPUnit 12 removed getMockForAbstractClass(); a real subclass is the
 * recommended replacement for testing shared base behaviour.
 */
final class AbstractModelMock extends AbstractModel
{
    protected static function repositoryClass(): string
    {
        return ProjectRepository::class;
    }
}
