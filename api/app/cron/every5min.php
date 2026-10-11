<?php
declare(strict_types=1);

/**
 * Cron mỗi 5 phút (02 §4.4):  *\/5 * * * *  php <APP_DIR>/cron/every5min.php
 *  1. đóng các phiên truy cập quá 5 phút không có heartbeat
 *  2. ghi số người online vào online_samples (bản đồ nhiệt “online lúc mấy giờ”)
 *  3. chấm các lượt kiểm tra / thi thử đã quá hạn cứng mà chưa nộp
 *  4. gửi tối đa 50 email trong hàng đợi
 *  5. thông báo “Đã lên lịch” → “Đang hiện”, “Đang hiện” → “Đã kết thúc”
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use Zika\Core\Cron;
use Zika\Core\Mailer;
use Zika\Services\AnnouncementService;
use Zika\Services\StatsService;
use Zika\Services\VisitService;

$steps = [
    'closeVisits' => static fn () => VisitService::closeStale(),
    'onlineSample' => static fn () => StatsService::sampleOnline(),
    'announcements' => static fn () => AnnouncementService::tick(),
    'mail' => static fn () => Mailer::sendPending(50),
];
// Chấm bài kiểm tra quá hạn (có từ Giai đoạn 2).
if (class_exists(\Zika\Services\TestService::class)) {
    $steps = ['gradeExpiredTests' => static fn () => \Zika\Services\TestService::gradeExpired()] + $steps;
}

$result = Cron::run('every5min', $steps);
if (in_array('-v', $argv, true)) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
}
