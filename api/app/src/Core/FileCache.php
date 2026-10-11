<?php
declare(strict_types=1);

namespace Zika\Core;

/**
 * Cache nội dung học dạng file JSON + ETag (02 §4.3): API học đọc file, không chạm MySQL.
 * Khoá theo cấp để xoá gọn: "levels", "l2-lessons", "l2-lesson21-lt", "l2-review-lt".
 */
final class FileCache
{
    private static function dir(string $bucket): string
    {
        $dir = Config::storageDir('cache/' . $bucket);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function file(string $bucket, string $key): string
    {
        return self::dir($bucket) . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
    }

    /**
     * Lấy JSON đã cache, hoặc dựng mới bằng $build (trả mảng dữ liệu).
     * @param callable():mixed $build
     * @return array{etag:string,json:string}
     */
    public static function content(string $key, callable $build): array
    {
        $file = self::file('content', $key);
        if (is_file($file)) {
            $json = (string) @file_get_contents($file);
            if ($json !== '') {
                return ['etag' => '"' . sha1($json) . '"', 'json' => $json];
            }
        }
        $json = Json::encode(['data' => $build()]);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @rename($tmp, $file); // ghi nguyên tử: request khác không đọc phải file dở
        }
        return ['etag' => '"' . sha1($json) . '"', 'json' => $json];
    }

    /** Xoá cache nội dung của một cấp (mọi bài, tổng hợp, danh sách bài) và danh sách cấp. */
    public static function forgetLevel(int $levelId): void
    {
        foreach (glob(self::dir('content') . '/l' . $levelId . '-*.json') ?: [] as $f) {
            @unlink($f);
        }
        @unlink(self::file('content', 'levels'));
    }

    public static function forgetAllContent(): void
    {
        foreach (glob(self::dir('content') . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

    /**
     * Cache ngắn hạn theo thời gian (số liệu thống kê “hôm nay”, JWKS của Google…).
     * @param callable():mixed $build
     */
    public static function remember(string $bucket, string $key, int $ttlSec, callable $build): mixed
    {
        $file = self::file($bucket, $key);
        if (is_file($file) && time() - (int) filemtime($file) < $ttlSec) {
            $data = Json::decode((string) @file_get_contents($file), null);
            if ($data !== null) {
                return $data;
            }
        }
        $data = $build();
        @file_put_contents($file, Json::encode($data), LOCK_EX);
        return $data;
    }

    public static function forget(string $bucket, string $key): void
    {
        @unlink(self::file($bucket, $key));
    }
}
