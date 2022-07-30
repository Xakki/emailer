<?php

declare(strict_types=1);

namespace Xakki\Emailer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Phroute\Phroute;
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

    public function getDb(): Connection
    {
        if (!isset($this->db)) {
            if (empty($this->config->db['password'])) {
                throw new Exception\Exception('Use unique password for DB!');
            }
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
     * @param array<string,string> $params
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
            $controller = new Controller\Console($this);
            return call_user_func_array([$controller, array_shift($args)], $args);
        } catch (\Throwable $e) {
            $this->logger->error($e, ['category' => 'console']);
            return $e->getMessage();
        }
    }

    public function dispatchRoute(string $requestMethod, string $requestUri): string
    {
        try {
            $router = new Phroute\RouteCollector();
            foreach ($this->config->route as $route => $handler) {
                $route = explode(':', $route);
                $httpMethod = array_shift($route);
                $route = implode(':', $route);
                $router->addRoute($httpMethod, $route, $handler);
            }
            $dispatcher = new Phroute\Dispatcher($router->getData(), new Helper\HandlerResolverRoute($this));
            return $dispatcher->dispatch($requestMethod, parse_url($requestUri, PHP_URL_PATH));
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->logger->error($e, ['category' => 'route']);
            return $e->getMessage();
        }
    }

    protected function __clone()
    {
        throw new \Exception('Clone this object not allow');
    }
}
