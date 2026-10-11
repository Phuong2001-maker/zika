<?php
declare(strict_types=1);

namespace Zika\Core;

use Throwable;

/**
 * Chạy một tác vụ cron có khoá file (flock) để không chạy chồng, ghi log thời gian chạy (02 §4.4).
 * Mỗi bước chạy độc lập: một bước lỗi không chặn các bước sau.
 */
final class Cron
{
    /**
     * @param array<string,callable():mixed> $steps tên bước => hàm
     * @return array<string,mixed> kết quả từng bước (hoặc 'error: …')
     */
    public static function run(string $name, array $steps): array
    {
        $dir = Config::storageDir('tmp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $lock = fopen($dir . '/cron-' . $name . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            Log::write('warn', 'cron.skip', ['job' => $name, 'reason' => 'đang chạy'], 'cron');
            return ['skipped' => true];
        }
        $start = microtime(true);
        $out = [];
        try {
            foreach ($steps as $step => $fn) {
                try {
                    $out[$step] = $fn();
                } catch (Throwable $e) {
                    Log::exception($e);
                    $out[$step] = 'error: ' . $e->getMessage();
                }
            }
        } finally {
            Log::write('info', 'cron.done', ['job' => $name, 'ms' => (int) round((microtime(true) - $start) * 1000), 'result' => $out], 'cron');
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $out;
    }
}
