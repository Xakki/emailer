<?php

declare(strict_types=1);

namespace Xakki\Emailer\Helper;

use Xakki\Emailer\Exception;

final class Tools
{
    /**
     * @param string $txt
     * @param array<string, string> $m
     * @return int
     */
    public static function hasReplacer(string $txt, array &$m = []): int
    {
        $matches = [];
        $cnt = (int) preg_match_all("/\{\{([\w\.]+)\}\}/ui", $txt, $matches);
        if ($cnt) {
            $m = array_unique($matches[1]);
        }
        return $cnt;
    }

    /**
     * @param array<int|string, int|string> $vars
     * @return array<string, int|string>
     */
    public static function wrapReplacer(array $vars): array
    {
        $newVars = [];
        foreach ($vars as $k => $v) {
            $newVars['{{' . $k . '}}'] = $v;
        }
        return $newVars;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * @param object $object
     * @return array<string, mixed>
     */
    public static function getPublicProperty(object $object): array
    {
        return get_object_vars($object);
    }

    public static function redirectLink(string $text, string $toRoute): string
    {
        $toRoute = rtrim($toRoute, '/');
        $match = [];
        $pattern = '/(href=")(http:\/\/|https:\/\/|www\.)[^"]+/u';
        preg_match_all($pattern, $text, $match);
        if (count($match[0])) {
            $temp = [];
            foreach ($match[0] as $rc) {
                if (mb_substr($rc, 0, 2) == '="') {
                    $temp[] = $rc;
                    continue;
                }
                if (mb_strpos($rc, 'href="') === 0) {
                    $rc = str_replace('href="', '', $rc);
                    $temp[] = ' href="' . $toRoute . '/' . self::base64UrlEncode($rc);
                } else {
                    $temp[] = $toRoute . '/' . self::base64UrlEncode($rc);
                }
            }
            $text = strtr($text, array_combine($match[0], $temp));
        }
        return $text;
    }

    /** @var array<int,mixed> */
    private static array $objects = [];
    public static int $strMaxLen = 1024;

    public static function dumpAsString(mixed $var, int $depth = 3, bool $highlight = false): string
    {
        $output = self::dumpInternal($var, $depth, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . $output, true);
            $output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        }
        self::$objects = [];
        return $output;
    }

    private static function dumpInternal(mixed $var, int $depth, int $level = 0): string
    {
        $output = '';
        switch (gettype($var)) {
            case 'boolean':
                $output .= $var ? 'true' : 'false';
                break;
            case 'integer':
            case 'double':
                $output .= (string) $var;
                break;
            case 'string':
                $output .= "'" . addslashes(self::$strMaxLen ? substr($var, 0, self::$strMaxLen) : $var) . "'";
                break;
            case 'resource':
                $output .= '{resource}';
                break;
            case 'NULL':
                $output .= 'null';
                break;
            case 'unknown type':
                $output .= '{unknown}';
                break;
            case 'array':
                if ($depth <= $level) {
                    $output .= '[...]';
                } elseif (empty($var)) {
                    $output .= '[]';
                } else {
                    $keys = array_keys($var);
                    $spaces = str_repeat(' ', $level * 4);
                    $output .= '[';
                    foreach ($keys as $key) {
                        $output .= "\n" . $spaces . '    ';
                        $output .= self::dumpInternal($key, $depth, 0);
                        $output .= ' => ';
                        $output .= self::dumpInternal($var[$key], $depth, $level + 1);
                    }
                    $output .= "\n" . $spaces . ']';
                }
                break;
            case 'object':
                $id = array_search($var, self::$objects, true);
                if ($id !== false) {
                    $output .= get_class($var) . '#' . ($id + 1) . '(...)';
                } elseif ($depth <= $level) {
                    $output .= get_class($var) . '(...)';
                } else {
                    $id = array_push(self::$objects, $var);
                    $className = get_class($var);
                    $spaces = str_repeat(' ', $level * 4);
                    $output .= "$className#$id\n" . $spaces . '(';
                    if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__debugInfo')) {
                        $dumpValues = $var->__debugInfo();
                        if (!is_array($dumpValues)) {
                            throw new \Exception('__debuginfo() must return an array');
                        }
                    } else {
                        $dumpValues = (array) $var;
                    }
                    foreach ($dumpValues as $key => $value) {
                        $keyDisplay = strtr(trim($key), "\0", ':');
                        $output .= "\n" . $spaces . "    [$keyDisplay] => ";
                        $output .= self::dumpInternal($value, $depth, $level + 1);
                    }
                    $output .= "\n" . $spaces . ')';
                }
                break;
        }
        return $output;
    }

    /**
     * @param string $lang
     * @param string $file
     * @return array<string,string>
     * @throws Exception\Exception
     */
    public static function getLocale(string $lang, string $file): array
    {
        $file = dirname(__DIR__) . '/locale/' . $lang . '/' . $file . '.php';
        if (!file_exists($file)) {
            throw new Exception\Exception('Locale file not exist: ' . $file);
        }
        return include $file;
    }

    /**
     * @param string $file
     * @return array{0: string, 1: string}
     */
    public static function getBase64File(string $file): array
    {
        return [mime_content_type($file), base64_encode(file_get_contents($file))];
    }
}
