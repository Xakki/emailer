<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Xakki\Emailer;

$config = new Emailer\ConfigService(require __DIR__ . '/../config/' . getenv('ENV') . '.php');

$logger = new Logger('web');
$handler = new StreamHandler(
    '/var/log/app.log',
    getenv('DEBUG_MODE') ? Level::Debug : Level::Warning
);
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);
$emailer = new Emailer\Emailer($config, $logger);

echo $emailer->dispatchRoute($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
