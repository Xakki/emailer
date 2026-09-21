<?php

declare(strict_types=1);

namespace Xakki\Emailer;

/**
 * @property-read array<string, mixed> $api
 * @property-read array<string, mixed> $db
 * @property-read array<string, mixed> $redis
 * @property-read array<string, mixed> $route
 * @property-read array<string, mixed> $migration
 * @property-read array<string, int> $retry
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
     * Geometric backoff for QUEUE_STATUS_TEMP_ERROR retries (see
     * Cqrs\Queue\AbstractQueue and Helper\RetrySchedule). max_attempts counts the
     * first send, so 5 = first send + 4 retries. first_delay/max_delay are the
     * shortest/longest wait between attempts, in seconds; the `reSend` cron
     * interval is the practical floor for first_delay. Validated at construction
     * (integers — integer-valued numeric strings, e.g. from env, are cast to int —
     * max_attempts >= 1, first_delay >= 1, max_delay >= first_delay).
     *
     * @var array<string,mixed> ints once validateRetry() has run
     */
    protected array $retry = [
        'max_attempts' => 5,
        'first_delay' => 900,
        'max_delay' => 86400,
    ];

    /**
     * Shared secret for the read-only /emailer/get test accessor.
     * Sourced from env SECRET_EMAILER_KEY (see wep Mail::getEmailer()).
     * Untyped on purpose: getenv() yields `false` when unset — absorbing it
     * here keeps email sending working ('' simply disables /emailer/get).
     *
     * snake_case is required: __construct() maps config-array keys straight to
     * properties, and the public config key is `secret_key`. Renaming would be
     * a config BC break, so the camelCase sniff is intentionally silenced.
     *
     * @var string
     */
    // phpcs:ignore WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCapsProperty
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
        $this->validateRetry();
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function validateRetry(): void
    {
        foreach (['max_attempts', 'first_delay', 'max_delay'] as $key) {
            $value = $this->retry[$key] ?? null;
            // Env-sourced config is a string: accept integer-valued ones only
            // (FILTER_VALIDATE_INT rejects "900.5", "9e2" and int overflow).
            if (is_string($value)) {
                $value = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $value;
            }
            if (!is_int($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'retry.%s must be an integer, %s given',
                    $key,
                    get_debug_type($value),
                ));
            }
            $this->retry[$key] = $value;
        }
        Helper\RetrySchedule::assertValid(
            $this->retry['max_attempts'],
            $this->retry['first_delay'],
            $this->retry['max_delay'],
        );
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
