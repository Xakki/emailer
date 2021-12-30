<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller;

use Psr\Log\LoggerInterface;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception;

abstract class AbstractController
{
    protected string $viewDir;
    protected LoggerInterface $logger;
    protected Emailer $emailer;

    public function __construct(Emailer $emailer)
    {
        $this->emailer = $emailer;
        $this->logger = $emailer->getLogger();
        $this->setViewDir();
    }

    /**
     * @param string $name
     * @param array<string,mixed> $arguments
     * @return mixed
     * @throws Exception\DataNotFound
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->run($name, $arguments);
    }

    /**
     * @param string $name
     * @param array<string,mixed> $arguments
     * @return string
     * @throws Exception\DataNotFound
     */
    protected function run(string $name, array $arguments): string
    {
        $m = 'action' . ucfirst($name);
        if (!method_exists($this, $m)) {
            static::errorAction('Wrong action: ' . $name);
        }
        return call_user_func_array([$this, $m], $arguments);
    }

    public function setViewDir(?string $viewDir = null): void
    {
        if (!$viewDir) {
            $class = explode('\\', get_called_class());
            $viewDir = dirname(__DIR__) . '/view/' . array_pop($class) . '/';
        }
        $this->viewDir = $viewDir;
    }

    protected static function errorAction(string $message): mixed
    {
        throw new Exception\DataNotFound($message);
    }

    /**
     * @param string $view
     * @param array<string,string> $vars
     * @return string
     * @throws Exception\Exception
     */
    protected function renderView(string $view, array $vars): string
    {
        $file = $this->viewDir . $view;
        if (!file_exists($file)) {
            throw new Exception\Exception('View not exist: ' . $view);
        }
        $html = file_get_contents($file);
        $html = strtr($html, $vars);
        $html = strtr($html, $vars);
        return $html;
    }

    public const DEFAULT_IMAGE = [
        'image/png',
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mM88f/EJgAIaQNDp1m5NAAAAABJRU5ErkJggg==',
    ];

    /**
     * @param array<int,string>|string $file
     * @return string
     */
    protected function renderImage(array|string $file): string
    {
        if (is_array($file)) {
            $type = $file[0];
            $file = base64_decode($file[1]);
        } elseif (file_exists($file)) {
            $file = file_get_contents($file);
            $type = mime_content_type($file);
        } else {
            $this->logger->warning('Image file is not exist: ' . $file, ['controller']);
            [$type, $file] = self::DEFAULT_IMAGE;
        }

        $this->headerSend('Content-type: ' . $type, true, 200);
        $this->headerSend('Pragma: no-cache');
        return $file;
    }

    protected function redirect(string $url, int $code = 307): void
    {
        $this->headerSend('Location: ' . $url, true, $code);
    }

    protected function headerSend(string $header, bool $replace = true, int $response_code = 0): void
    {
        header($header, $replace, $response_code);
    }
}
