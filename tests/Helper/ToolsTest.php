<?php

declare(strict_types=1);

namespace Xakki\Emailer\test\phpunit\Helper;

use PHPUnit\Framework\TestCase;
use Xakki\Emailer\Helper\Tools;
use Xakki\Emailer\test\phpunit\Mocks;

class ToolsTest extends TestCase
{
    use Mocks;

    public function testHasReplacer(): void
    {
        $m = [];
        $res = Tools::hasReplacer('qqqq {{mark}} test{ test {{mark1}} HELP }}} sfsdf {{mark.123}} sfs f {{$6jfgn}}', $m);
        self::assertEquals(['mark', 'mark1', 'mark.123'], $m);
        self::assertEquals(3, $res);
    }

    public function testWrapReplacer(): void
    {
        $m = ['test1' => 1, 'test2' => 2, 9 => 'xxx'];
        $res = Tools::wrapReplacer($m);
        self::assertEquals(['{{test1}}' => 1, '{{test2}}' => 2, '{{9}}' => 'xxx'], $res);
    }

    public function testBase64Url(): void
    {
        $str = 'https://example.com';
        $strEncoded = 'aHR0cHM6Ly9leGFtcGxlLmNvbQ';
        $res = Tools::base64UrlEncode($str);
        self::assertEquals($strEncoded, $res);

        $res = Tools::base64UrlDecode($strEncoded);
        self::assertEquals($str, $res);
    }

    public function testGetPublicProperty(): void
    {
        $obj = new class {
            public int $id = 5;
            protected string $name = 'test';
            private array $data = [9, 'hello']; // @phpstan-ignore-line
        };

        $res = Tools::getPublicProperty($obj);
        self::assertEquals(['id' => 5], $res);
    }

    public function testRedirectLink(): void
    {
        $txt = 'Hello, this is my <a href="https://test.com">Home page</a>';
        $toRoute = 'https://example.com/';
        $res = Tools::redirectLink($txt, $toRoute);
        self::assertEquals('Hello, this is my <a  href="https://example.com/aHR0cHM6Ly90ZXN0LmNvbQ">Home page</a>', $res);
    }

    public function testDumpAsString(): void
    {
        $data = [
            'test',
            'key' => 'hello',
            'boolean' => false,
            'NULL' => null,
            'array' => ['world', 'city'],
            'object' => (new \Xakki\Emailer\Mail())->setEmail('test@example.com'),
        ];
        $expected = <<<HTML
[
    0 => 'test'
    'key' => 'hello'
    'boolean' => false
    'NULL' => null
    'array' => [
        0 => 'world'
        1 => 'city'
    ]
    'object' => Xakki\Emailer\Mail#1
    (
        [*:data] => [
            'email' => 'test@example.com'
        ]
    )
]
HTML;
        $res = Tools::dumpAsString($data, 3);

        self::assertEquals($expected, $res);
    }
}
