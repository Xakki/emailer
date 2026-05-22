<?php

declare(strict_types=1);

namespace Xakki\Emailer\Helper;

use Xakki\Emailer\Exception\DataNotFound;

/**
 * Tiny regex router replacing the unmaintained phroute/phroute.
 *
 * Routes keep the same DSL the project already used, so configuration does not
 * change: `METHOD:/path/{name}` with optional placeholder filters `{name:i}`.
 * Supported filters mirror phroute's defaults:
 *   `:i` → \d+   `:a` → [A-Za-z0-9]+   `:c` → [A-Za-z0-9+_\-.]+   `:h` → [A-Za-z0-9]+
 * No filter matches a single path segment (`[^/]+`). Method `ANY` matches any
 * verb.
 */
final class Router
{
    /** @var array<string, string> */
    private const FILTERS = [
        ''  => '[^/]+',
        'i' => '\d+',
        'a' => '[a-zA-Z0-9]+',
        'c' => '[a-zA-Z0-9+_\-\.]+',
        'h' => '[a-zA-Z0-9]+',
    ];

    /** @var list<array{method: string, regex: string, handler: mixed}> */
    private array $routes = [];

    /**
     * @param array<string, mixed> $routes Map of `METHOD:/path` => handler
     */
    public function __construct(array $routes)
    {
        foreach ($routes as $definition => $handler) {
            // Split on the first colon only: filters such as {version:i} keep theirs.
            [$method, $path] = explode(':', $definition, 2);
            $this->routes[] = [
                'method' => strtoupper($method),
                'regex' => self::compile($path),
                'handler' => $handler,
            ];
        }
    }

    /**
     * @return array{0: mixed, 1: array<string, string>} Matched handler and named params
     * @throws DataNotFound When no route matches
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $m)) {
                $vars = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$route['handler'], $vars];
            }
        }
        throw new DataNotFound('No route for ' . $method . ' ' . $path);
    }

    private static function compile(string $path): string
    {
        $parts = preg_split('/(\{\w+(?::[a-z])?\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{(\w+)(?::([a-z]))?\}$/', $part, $m)) {
                $filter = self::FILTERS[$m[2] ?? ''] ?? self::FILTERS[''];
                $regex .= '(?P<' . $m[1] . '>' . $filter . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
        return '#^' . $regex . '$#';
    }
}
