<?php
declare(strict_types=1);

namespace Zika\Core;

use Throwable;

/** Ghi log dạng JSON lines: storage/logs/app-YYYY-MM-DD.log (02 §4.2). */
final class Log
{
    /** Người dùng/đường dẫn của request hiện tại, App gán vào để mỗi dòng log có ngữ cảnh. */
    public static ?int $userId = null;
    public static string $path = '';

    /** @param array<string,mixed> $ctx */
    public static function info(string $msg, array $ctx = []): void
    {
        self::write('info', $msg, $ctx);
    }

    /** @param array<string,mixed> $ctx */
    public static function warn(string $msg, array $ctx = []): void
    {
        self::write('warn', $msg, $ctx);
    }

    /** @param array<string,mixed> $ctx */
    public static function error(string $msg, array $ctx = []): void
    {
        self::write('error', $msg, $ctx);
    }

    public static function exception(Throwable $e): void
    {
        self::write('error', $e->getMessage(), [
            'type' => $e::class,
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
        ]);
    }

    /** @param array<string,mixed> $ctx */
    public static function write(string $level, string $msg, array $ctx = [], string $channel = 'app'): void
    {
        try {
            $dir = Config::storageDir('logs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $line = Json::encode([
                'time' => date('c'),
                'level' => $level,
                'msg' => $msg,
                'user_id' => self::$userId,
                'path' => self::$path,
            ] + ($ctx === [] ? [] : ['ctx' => $ctx]));
            @file_put_contents($dir . '/' . $channel . '-' . date('Y-m-d') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Không để lỗi ghi log làm hỏng request.
        }
    }
}
