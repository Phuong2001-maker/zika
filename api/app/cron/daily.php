<?php
declare(strict_types=1);

/**
 * Cron mỗi ngày (02 §4.4):  10 0 * * *  php <APP_DIR>/cron/daily.php
 *  1. chốt số liệu stats_daily của hôm qua
 *  2. gộp affiliate_events vào affiliate_daily, xoá sự kiện thô > 30 ngày
 *  3. xoá dữ liệu quá hạn: phiên truy cập, mẫu online, token, hàng đợi thư, nhật ký
 *
 * Thêm -v để in kết quả từng bước (dùng khi chạy tay để kiểm tra).
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use Zika\Core\Cron;
use Zika\Services\MaintenanceService;

$result = Cron::run('daily', [
    'rollupDay' => static fn () => MaintenanceService::rollupDay(),
    'rollupAffiliate' => static fn () => MaintenanceService::rollupAffiliate(),
    'prune' => static fn () => MaintenanceService::prune(),
]);

if (in_array('-v', $argv, true)) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
}
