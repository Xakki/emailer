<?php

declare(strict_types=1);

opcache_reset();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('short_open_tag', 'on');

define('PATH_VENDOR', __DIR__ . '/../../../vendor');

require_once PATH_VENDOR . '/autoload.php';
