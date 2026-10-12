<?php
declare(strict_types=1);

/**
 * Cron sao lưu database (02 §4.4):  0 3 * * *  php <APP_DIR>/cron/backup.php
 * Chỉ chạy khi Cài đặt → Sao lưu → “Tự sao lưu mỗi ngày” đang bật.
 * Thêm --force để chạy dù cài đặt đang tắt, -v để in kết quả.
 *
 * Kết quả: storage/backups/zika-YYYYmmdd-HHMM.sql.gz, giữ 7 bản gần nhất.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use Zika\Core\Cron;
use Zika\Core\Settings;
use Zika\Services\BackupService;

$force = in_array('--force', $argv, true);
if (!$force && !Settings::value('backup', 'daily', true)) {
    if (in_array('-v', $argv, true)) {
        echo "Tự sao lưu đang tắt trong Cài đặt — bỏ qua (thêm --force để chạy).\n";
    }
    exit(0);
}

$result = Cron::run('backup', [
    'dump' => static fn () => BackupService::run(),
]);

if (in_array('-v', $argv, true)) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
}
