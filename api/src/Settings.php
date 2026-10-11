<?php
declare(strict_types=1);

namespace Zika;

/** Đọc/ghi bảng settings (cấu hình chung của web, admin sửa được trong giao diện). */
final class Settings
{
    private static ?array $cache = null;

    /** @return array<string, string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [];
        foreach (Db::all('SELECT k, v FROM settings') as $r) {
            $out[(string) $r['k']] = (string) ($r['v'] ?? '');
        }
        return self::$cache = $out;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function flag(string $key, bool $default): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    public static function json(string $key, mixed $default = null): mixed
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : Util::jsonDecode($v, $default);
    }

    public static function set(string $key, string|int|bool|array|null $value): void
    {
        if (is_bool($value)) {
            $v = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $v = Util::jsonEncode($value);
        } else {
            $v = $value === null ? '' : (string) $value;
        }

        Db::run(
            'INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            [$key, $v]
        );
        self::$cache = null;
    }
}
