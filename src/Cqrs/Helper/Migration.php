<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Helper;

use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command;
use Symfony\Component\Console\Application;
use Xakki\Emailer\Cqrs\CqrsInterface;
use Xakki\Emailer\Emailer;

class Migration implements CqrsInterface
{
    public function __construct()
    {
    }

    public function handler(): mixed
    {
        $connection = Emailer::i()->getDb();

        $config = Emailer::i()->getMigrationConfig();

        $dependencyFactory = DependencyFactory::fromConnection($config, new ExistingConnection($connection));

        $cli = new Application('Doctrine Migrations');
        $cli->setCatchExceptions(true);

        $cli->addCommands([
            new Command\DumpSchemaCommand($dependencyFactory),
            new Command\ExecuteCommand($dependencyFactory),
            new Command\GenerateCommand($dependencyFactory),
            new Command\LatestCommand($dependencyFactory),
            new Command\ListCommand($dependencyFactory),
            new Command\MigrateCommand($dependencyFactory),
            new Command\RollupCommand($dependencyFactory),
            new Command\StatusCommand($dependencyFactory),
            new Command\SyncMetadataCommand($dependencyFactory),
            new Command\VersionCommand($dependencyFactory),
        ]);

        return $cli->run();
    }
}
