<?php
declare(strict_types=1);

namespace Zika\Core;

use RuntimeException;

/** Đọc cấu hình từ config.php, truy cập theo khoá dạng chấm: Config::get('db.host'). */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException("Thiếu tệp cấu hình {$file}. Hãy sao chép config.example.php thành config.php.");
        }
        $items = require $file;
        if (!is_array($items)) {
            throw new RuntimeException('config.php phải trả về một mảng.');
        }
        self::$items = $items;
    }

    /** Dùng trong kiểm thử. @param array<string,mixed> $items */
    public static function set(array $items): void
    {
        self::$items = $items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function isLocal(): bool
    {
        return self::get('env') === 'local';
    }

    public static function appUrl(): string
    {
        return rtrim((string) self::get('app_url', ''), '/');
    }

    public static function storageDir(string $sub = ''): string
    {
        $base = rtrim((string) self::get('paths.storage_dir', ZIKA_APP_DIR . '/storage'), '/\\');
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }

    public static function uploadsDir(string $sub = ''): string
    {
        $base = rtrim((string) self::get('paths.uploads_dir', ''), '/\\');
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }

    public static function uploadsUrl(string $sub = ''): string
    {
        $base = rtrim((string) self::get('paths.uploads_url', '/uploads'), '/');
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }
}
