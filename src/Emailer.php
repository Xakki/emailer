<?php

declare(strict_types=1);

namespace Xakki\Emailer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Psr\Log\LoggerInterface;
use Redis;

class Emailer
{
    protected static self $instances;
    protected ConfigService $config;
    protected LoggerInterface $logger;
    protected Connection $db;
    protected Redis $cache;

    public function __construct(ConfigService $config, LoggerInterface $logger)
    {
        static::$instances = $this;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Global access for Emailer object
     *
     * @return self
     * @throws Exception\Exception
     */
    public static function i(): self
    {
        // @phpstan-ignore-next-line
        if (!static::$instances) {
            throw new Exception\Exception('No instance');
        }
        return static::$instances;
    }

    public function __wakeup()
    {
        throw new Exception\Exception("Cannot unserialize a singleton.");
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getConfig(): ConfigService
    {
        return $this->config;
    }

    public function getDb(): Connection
    {
        if (!isset($this->db)) {
            if (empty($this->config->db['password'])) {
                throw new Exception\Exception('Use unique password for DB!');
            }
            /** @psalm-suppress InvalidArgument **/
            $this->db = DriverManager::getConnection($this->config->db);
//            $logger = new DebugStack();
//            $this->Db->getConfiguration()->setSQLLogger($logger);
//            $this->Db->setTransactionIsolation(\Doctrine\DBAL\TransactionIsolationLevel::READ_COMMITTED);
        }
        return $this->db;
    }

    public function getCache(): Redis
    {
        if (!isset($this->cache)) {
            $this->cache = new Redis();
            if (!$this->cache->connect($this->config->redis['host'], $this->config->redis['port'])) {
                throw new Exception\Exception('Cant connect to Redis');
            }
        }
        return $this->cache;
    }

    public function getMigrationConfig(): ConfigurationArray
    {
        return new ConfigurationArray($this->config->migration);
    }

    /**
     * @param string $name
     * @param array<string,mixed> $params
     * @return Model\Project
     * @throws Exception\Exception
     */
    public function createProject(string $name, array $params): Model\Project
    {
        return (new Cqrs\Project\CreateProject($name, $params))->handler();
    }

    /**
     * @param int $projectId
     * @return Model\Project
     * @throws Exception\DataNotFound
     */
    public function getProject(int $projectId): Model\Project
    {
        return (new Cqrs\Project\GetProject($projectId))->handler();
    }

    /**
     * @param int $projectId
     * @param int $campaignId
     * @return Sender
     * @throws Exception\DataNotFound
     */
    public function getNewSender(int $projectId, int $campaignId): Sender
    {
        return new Sender($this, $projectId, $campaignId);
    }

    public function getNewMail(): Mail
    {
        return new Mail();
    }

    /**
     * @param array<int,mixed> $args
     * @return string
     */
    public function dispatchConsole(array $args): string
    {
        try {
            /** @var callable $call */
            $call = [new Controller\Console($this), array_shift($args)];
            return call_user_func_array($call, $args);
        } catch (\Throwable $e) {
            $this->logger->error($e, ['category' => 'console']);
            return $e->getMessage();
        }
    }

    public function dispatchRoute(string $requestMethod, string $requestUri): string
    {
        try {
            $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
            $router = new Helper\Router($this->config->route);
            [$handler, $vars] = $router->match($requestMethod, $path);

            // Lazily instantiate string controller handlers: [Class, method] -> [object, method].
            if (is_array($handler) && is_string($handler[0])) {
                $handler[0] = new $handler[0]($this);
            }
            /** @var callable $handler */
            return (string) call_user_func_array($handler, $vars);
        } catch (\Throwable $e) {
            http_response_code($e instanceof Exception\Exception ? $e->httpCode : 500);
            $this->logger->error($e, ['category' => 'route']);
            return $e->getMessage();
        }
    }

    protected function __clone()
    {
        throw new \Exception('Clone this object not allow');
    }
}
