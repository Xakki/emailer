<?php

declare(strict_types=1);

namespace Xakki\Emailer;

/**
 * @property-read array $api
 * @property-read array $db
 * @property-read array $redis
 * @property-read array $route
 * @property-read array $migration
 * @property-read string $secret_key
 */
class ConfigService
{
    /** @var array<string,mixed> */
    protected array $api = [
        'email' => '',
        'password' => '',
        'key' => '',
    ];

    /** @var array<string,mixed> */
    protected array $db = [
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
    protected array $redis = [
        'host' => 'emailer-redis',
        'port' => 6379,
    ];

    /** @var array<string,mixed>  */
    protected array $route = [
        'ANY:/' => [Controller\Mail::class, 'index'],
        'GET:/emailer/home/{key}' => [Controller\Mail::class, 'home'],
        'GET:/emailer/goto/{key:a}/{url:c}' => [Controller\Mail::class, 'goto'],
        'GET:/emailer/logoimg/{key:a}' => [Controller\Mail::class, 'logoimg'],
        'GET:/emailer/unsubscribe/{key:a}' => [Controller\Mail::class, 'unsubscribe'],
        'GET:/emailer/subscribe/{key:a}' => [Controller\Mail::class, 'subscribe'],
        'GET:/emailer/status/{key:a}' => [Controller\Mail::class, 'status'],
        // Read-only e2e/test accessor: returns a rendered queued email body.
        // Disabled (opaque 404) unless `secret_key` is set non-empty.
        'GET:/emailer/get/{key:a}/{secret:c}' => [Controller\Mail::class, 'get'],
        'POST:/emailer/api/v{version:i}/panel/login' => [Controller\Api\Panel::class, 'login'],
        'GET:/emailer/api/v{version:i}/panel/head' => [Controller\Api\Panel::class, 'head'],
        'GET:/emailer/api/v{version:i}/panel/dashboard' => [Controller\Api\Panel::class, 'dashboard'],
        'POST:/emailer/api/v{version:i}/smtp/test' => [Controller\Api\Smtp::class, 'test'],
        'GET:/logs' => [Controller\Panel::class, 'logs'],
    ];

    /**
     * @var array<string,mixed>
     * https://www.doctrine-project.org/projects/doctrine-migrations/en/3.3/reference/configuration.html#configuration
     */
    protected array $migration = [
        'table_storage' => ['table_name' => 'migration'],
        'migrations_paths' => [
            'Xakki\Emailer\Migration' => __DIR__ . '/Migration',
        ],
        'all_or_nothing' => true,
    ];

    /**
     * Shared secret for the read-only /emailer/get test accessor.
     * Sourced from env SECRET_EMAILER_KEY (see wep Mail::getEmailer()).
     * Untyped on purpose: getenv() yields `false` when unset — absorbing it
     * here keeps email sending working ('' simply disables /emailer/get).
     * @var string
     */
    protected $secret_key = '';

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

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->$name;
    }
}
