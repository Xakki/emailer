<?php

declare(strict_types=1);

namespace Xakki\Emailer;

class ConfigService
{
    /** @var array<string,mixed> */
    public array $api = [
        'email' => '',
        'password' => '',
        'key' => '',
    ];

    /** @var array<string,mixed> */
    public array $db = [
        'driver' => 'pdo_mysql',
        'charset' => 'UTF8',
        'host' => 'emailer-mariadb',
        'port' => 3306,
        'user' => 'emailer',
        'password' => 'CHENGE_ME',
        'dbname' => 'emailer',
        'persistent' => true,
//        'url' => null,
    ];

    /** @var array<string,mixed> */
    public array $redis = [
        'host' => 'emailer-redis',
        'port' => 6379,
    ];

    /** @var array<string,callable>  */
    public array $route = [
        'ANY:/' => [Controller\Mail::class, 'index'],
        'GET:/emailer/home/{key}' => [Controller\Mail::class, 'home'],
        'GET:/emailer/goto/{key:a}/{url:c}' => [Controller\Mail::class, 'goto'],
        'GET:/emailer/logoimg/{key:a}' => [Controller\Mail::class, 'logoimg'],
        'GET:/emailer/unsubscribe/{key:a}' => [Controller\Mail::class, 'unsubscribe'],
        'GET:/emailer/subscribe/{key:a}' => [Controller\Mail::class, 'subscribe'],
        'GET:/emailer/status/{key:a}' => [Controller\Mail::class, 'status'],
        'POST:/emailer/api/v{version:i}/panel/login' => [Controller\Api\Panel::class, 'login'],
        'GET:/emailer/api/v{version:i}/panel/head' => [Controller\Api\Panel::class, 'head'],
        'GET:/emailer/api/v{version:i}/panel/dashboard' => [Controller\Api\Panel::class, 'dashboard'],
        'POST:/emailer/api/v{version:i}/smtp/test' => [Controller\Api\Smtp::class, 'test'],
    ];

    /**
     * @var array<string,mixed>
     * https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/configuration.html#configuration
     */
    public array $migration = [
        'table_storage' => ['table_name' => 'migration'],
        'migrations_paths' => [
            'Xakki\Emailer\Migration' => __DIR__ . '/Migration',
        ],
        'all_or_nothing' => true,
    ];

    /**
     * @param array<string,mixed> $input
     */
    public function __construct(array $input = [])
    {
        foreach ($input as $k => $v) {
            if (is_array($v)) {
                if (!isset($this->{$k})) {
                    $this->{$k} = [];
                }
                foreach ($v as $k2 => $v2) {
                    $this->{$k}[$k2] = $v2;
                }
            } else {
                $this->{$k} = $v;
            }
        }
    }
}
