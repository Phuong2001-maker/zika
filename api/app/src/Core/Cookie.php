<?php
declare(strict_types=1);

namespace Zika\Core;

/** Gom cookie cần đặt trong request, gửi một lần khi trả response (dễ kiểm thử). */
final class Cookie
{
    /** @var array<string,array{value:string,options:array<string,mixed>}> */
    private static array $queue = [];

    /**
     * @param int  $expires 0 = cookie phiên trình duyệt; số âm = xoá
     */
    public static function queue(string $name, string $value, int $expires = 0, bool $httpOnly = true): void
    {
        self::$queue[$name] = [
            'value' => $value,
            'options' => [
                'expires'  => $expires < 0 ? time() - 3600 : $expires,
                'path'     => '/',
                'domain'   => (string) Config::get('cookie.domain', ''),
                'secure'   => (bool) Config::get('cookie.secure', true),
                'httponly' => $httpOnly,
                'samesite' => 'Lax',
            ],
        ];
    }

    public static function forget(string $name, bool $httpOnly = true): void
    {
        self::queue($name, '', -1, $httpOnly);
    }

    /** @return array<string,array{value:string,options:array<string,mixed>}> */
    public static function pending(): array
    {
        return self::$queue;
    }

    public static function flush(): void
    {
        if (!headers_sent()) {
            foreach (self::$queue as $name => $c) {
                setcookie($name, $c['value'], $c['options']);
            }
        }
        self::$queue = [];
    }

    public static function reset(): void
    {
        self::$queue = [];
    }
}
