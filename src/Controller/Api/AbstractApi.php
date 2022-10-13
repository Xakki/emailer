<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller\Api;

use Xakki\Emailer\Controller\AbstractController;
use Xakki\Emailer\Exception\Exception;
use Xakki\Emailer\Exception\Validations;

/**
 * @OA\Info(
 *     title="Emailer API",
 *     version="0.1.0"
 * )
 * @OA\Server(url="/api/v1")
 * @OA\SecurityScheme(
 *     securityScheme="token",
 *     type="apiKey",
 *     name="x-token",
 *     in="header"
 * )
 */
abstract class AbstractApi extends AbstractController
{
    public const VERSIONS = [
        '1',
    ];
    protected static string $version = 'v1';

    /**
     * @param string $name
     * @param array<string,mixed> $arguments
     * @return string
     */
    protected function run(string $name, array $arguments): string
    {
        $this->headerSend('Content-Type: application/json; charset=utf-8');

        $m = 'action' . ucfirst($name);
        if (!method_exists($this, $m)) {
            $data = static::errorAction('Wrong endpoint: ' . $name);
            http_response_code(500);
        } elseif (empty($arguments['version']) || !in_array($arguments['version'], self::VERSIONS)) {
            // @phpstan-ignore-next-line
            $data = static::errorAction('Wrong version or empty: ' . $arguments['version'] ?? '');
            http_response_code(500);
        } else {
            $this::$version = $arguments['version'];
            unset($arguments['version']);

            try {
                $this->xAuth();
                $data = call_user_func_array([$this, $m], $arguments);
                $data = static::successAction($data);
                http_response_code(200);
            } catch (Validations $e) {
                http_response_code($e->httpCode);
                $this->logger->notice($e, ['controller']);
                $data = static::validationAction($e->getData());
            } catch (Exception $e) {
                http_response_code($e->httpCode);
                $this->logger->warning($e, ['controller']);
                $data = static::errorAction($e->title . ': ' . $e->getMessage());
            } catch (\Throwable $e) {
                http_response_code(!empty($e->httpCode) ? $e->httpCode : 500);
                $this->logger->error($e, ['controller']);
                $data = static::errorAction((!empty($e->title) ? $e->title : 'Error') . ': ' . $e->getMessage());
            }
        }
        return static::toJson($data);
    }

    /**
     * @return array<string,mixed>
     */
    protected function getPost(): array
    {
        return json_decode(file_get_contents('php://input'), true);
    }

    /**
     * @param array<mixed> $data
     * @return string
     */
    protected static function toJson(array $data): string
    {
        return json_encode($data);
    }

    /**
     * @OA\Schema(
     *     schema="Success",
     *     @OA\Property( property="success", type="boolean", default=true),
     *     @OA\Property( property="data", type="array", @OA\Items(oneOf={
     *         @OA\Schema(type="string"),
     *         @OA\Schema(type="integer")
     *     }))
     * )
     */
    /**
     * @param array<int,mixed> $data
     * @return array<string,mixed>
     */
    protected static function successAction(array $data): array
    {
        return [
            "success" => true,
            "data" => $data,
        ];
    }

    /**
     * @OA\Schema(
     *     schema="Error",
     *     example={
     *         "info": "API version: v1",
     *         "success": false,
     *         "data": {},
     *         "message" : "Some error message"
     *     }
     * )
     */
    /**
     * @param string $message
     * @return array<string,mixed>
     */
    protected static function errorAction(string $message): array
    {
        return [
            'info' => 'API version: ' . static::$version,
            "success" => false,
            "data" => [],
            "message" => $message,
        ];
    }

    /**
     * @OA\Schema(
     *     schema="ValidationError",
     *     example={
     *         "info": "API version: v1",
     *         "success": false,
     *         "data": {"fieldName1": "Error message", "fieldName2": "Error message"},
     *         "message" : "Validation errors"
     *     }
     * )
     */
    /**
     * @param array<mixed> $data
     * @return array<string,mixed>
     */
    protected static function validationAction(array $data): array
    {
        return [
            'info' => 'API version: ' . static::$version,
            "success" => false,
            "data" => $data,
            "message" => "Validation errors",
        ];
    }

    /**
     * @return bool
     */
    protected function xAuth(): bool
    {
        return true;
    }
}
