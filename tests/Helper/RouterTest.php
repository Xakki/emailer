<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Helper\Router;

class RouterTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function routes(): array
    {
        return [
            'ANY:/' => 'index',
            'GET:/emailer/home/{key}' => 'home',
            'GET:/emailer/goto/{key:a}/{url:c}' => 'goto',
            'POST:/emailer/api/v{version:i}/panel/login' => 'login',
            'GET:/logs' => 'logs',
        ];
    }

    public function testMatchesPlainAndAnyMethod(): void
    {
        $router = new Router($this->routes());
        self::assertSame(['index', []], $router->match('GET', '/'));
        // ANY matches any verb.
        self::assertSame(['index', []], $router->match('DELETE', '/'));
        self::assertSame(['logs', []], $router->match('GET', '/logs'));
    }

    public function testCapturesNamedParams(): void
    {
        $router = new Router($this->routes());
        [$handler, $vars] = $router->match('GET', '/emailer/home/abc123');
        self::assertSame('home', $handler);
        self::assertSame(['key' => 'abc123'], $vars);
    }

    public function testFilterIntAndAlphaAndCharClass(): void
    {
        $router = new Router($this->routes());

        [$handler, $vars] = $router->match('POST', '/emailer/api/v2/panel/login');
        self::assertSame('login', $handler);
        self::assertSame(['version' => '2'], $vars);

        // {url:c} accepts base64url alphabet (+ _ - .)
        [, $vars] = $router->match('GET', '/emailer/goto/key1/aHR0cHM6-_.');
        self::assertSame('key1', $vars['key']);
        self::assertSame('aHR0cHM6-_.', $vars['url']);
    }

    public function testIntegerFilterRejectsNonDigits(): void
    {
        $router = new Router($this->routes());
        $this->expectException(DataNotFound::class);
        $router->match('POST', '/emailer/api/vX/panel/login');
    }

    public function testUnknownPathThrows(): void
    {
        $router = new Router($this->routes());
        $this->expectException(DataNotFound::class);
        $router->match('GET', '/nope');
    }

    public function testMethodMismatchThrows(): void
    {
        $router = new Router($this->routes());
        $this->expectException(DataNotFound::class);
        // /logs is GET-only.
        $router->match('POST', '/logs');
    }

    public function testTrailingSegmentIsNotGreedyAcrossSlash(): void
    {
        $router = new Router($this->routes());
        // {key} matches a single segment, so an extra path segment must not match.
        $this->expectException(DataNotFound::class);
        $router->match('GET', '/emailer/home/a/b');
    }
}
