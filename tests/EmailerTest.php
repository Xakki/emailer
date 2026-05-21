<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Exception\Exception;
use Xakki\Emailer\Mail;

class EmailerTest extends TestCase
{
    public function testGettersAndFactories(): void
    {
        $config = new ConfigService(['db' => ['password' => 'x'], 'secret_key' => 'top']);
        $logger = new Logger('test');
        $emailer = new Emailer($config, $logger);

        self::assertSame($config, $emailer->getConfig());
        self::assertSame($logger, $emailer->getLogger());
        self::assertSame('top', $config->secret_key);
        self::assertInstanceOf(Mail::class, $emailer->getNewMail());
        self::assertSame($emailer, Emailer::i());
    }

    public function testGetDbRequiresPassword(): void
    {
        $emailer = new Emailer(new ConfigService(['db' => ['password' => '']]), new Logger('test'));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Use unique password for DB!');
        $emailer->getDb();
    }

    public function testGetNewSenderRejectsZeroIds(): void
    {
        $emailer = new Emailer(new ConfigService(['db' => ['password' => 'x']]), new Logger('test'));
        $this->expectException(DataNotFound::class);
        $emailer->getNewSender(0, 1);
    }

    public function testWakeupForbidden(): void
    {
        $emailer = new Emailer(new ConfigService(['db' => ['password' => 'x']]), new Logger('test'));
        $this->expectException(Exception::class);
        $emailer->__wakeup();
    }

    public function testDispatchConsoleReturnsErrorMessageForUnknownCommand(): void
    {
        $emailer = new Emailer(new ConfigService(['db' => ['password' => 'x']]), new Logger('test'));
        $out = $emailer->dispatchConsole(['no-such-method']);
        self::assertNotSame('', $out);
    }

    public function testDispatchRouteReturnsErrorMessageForUnknownRoute(): void
    {
        $emailer = new Emailer(new ConfigService(['db' => ['password' => 'x']]), new Logger('test'));
        $out = $emailer->dispatchRoute('GET', '/route/that/does/not/exist');
        // Phroute throws → caught → message string returned.
        self::assertNotSame('', $out);
    }

    public function testGetMigrationConfig(): void
    {
        $emailer = new Emailer(new ConfigService(['db' => ['password' => 'x']]), new Logger('test'));
        $cfg = $emailer->getMigrationConfig();
        self::assertInstanceOf(\Doctrine\Migrations\Configuration\Migration\ConfigurationArray::class, $cfg);
    }
}
