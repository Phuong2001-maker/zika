<?php
declare(strict_types=1);

namespace Zika;

use RuntimeException;

/** Đọc config.php một lần rồi giữ trong bộ nhớ. */
final class Config
{
    private static ?array $data = null;

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException(
                "Thiếu file cấu hình: $file\n" .
                'Hãy sao config.example.php thành config.php rồi điền thông tin CSDL.'
            );
        }
        /** @var array $cfg */
        $cfg = require $file;
        self::$data = $cfg;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) {
            self::load(dirname(__DIR__) . '/config.php');
        }
        // Hỗ trợ khoá lồng kiểu 'db.host'
        $node = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return $default;
            }
            $node = $node[$part];
        }
        return $node;
    }
}
