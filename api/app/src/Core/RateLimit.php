<?php
declare(strict_types=1);

namespace Zika\Core;

/** Đếm số lần theo khoá trong một cửa sổ thời gian cố định (bảng rate_limits, 02 §6.3). */
final class RateLimit
{
    /** Ghi thêm 1 lần và cho biết còn trong giới hạn không. */
    public static function hit(string $key, int $max, int $windowSec): bool
    {
        $k = self::key($key);
        // Thứ tự gán trong ON DUPLICATE KEY UPDATE quan trọng: tính hits theo window_start CŨ, rồi mới đặt lại window_start.
        Db::exec(
            'INSERT INTO rate_limits (k, hits, window_start) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
               hits = IF(window_start < NOW() - INTERVAL ? SECOND, 1, hits + 1),
               window_start = IF(window_start < NOW() - INTERVAL ? SECOND, NOW(), window_start)',
            [$k, $windowSec, $windowSec],
        );
        return (int) Db::val('SELECT hits FROM rate_limits WHERE k = ?', [$k]) <= $max;
    }

    /** Đã vượt giới hạn chưa (không đếm thêm). */
    public static function exceeded(string $key, int $max, int $windowSec): bool
    {
        $hits = Db::val(
            'SELECT hits FROM rate_limits WHERE k = ? AND window_start >= NOW() - INTERVAL ? SECOND',
            [self::key($key), $windowSec],
        );
        return $hits !== null && (int) $hits >= $max;
    }

    /** Đếm 1 lần; vượt giới hạn thì báo 429. */
    public static function enforce(string $key, int $max, int $windowSec, string $message = ''): void
    {
        if (!self::hit($key, $max, $windowSec)) {
            throw new HttpError(429, 'RATE_LIMITED', $message);
        }
    }

    public static function clear(string $key): void
    {
        Db::exec('DELETE FROM rate_limits WHERE k = ?', [self::key($key)]);
    }

    private static function key(string $key): string
    {
        return strlen($key) <= 190 ? $key : substr($key, 0, 140) . ':' . sha1($key);
    }
}
