<?php

require_once __DIR__.'/../vendor/autoload.php';

use Xakki\Emailer;


$config = new Emailer\ConfigService(include __DIR__ . '/../config/' . getenv('ENV') . '.php');
$logger = new Emailer\test\phpunit\Logger();
$emailer = new Emailer\Emailer($config, $logger);

echo $emailer->dispatchRoute($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
