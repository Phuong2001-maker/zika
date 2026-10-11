<?php
declare(strict_types=1);

/**
 * Điểm khởi động chung cho API (public/index.php), cron và công cụ dòng lệnh.
 * Thư mục này (<APP_DIR>) nằm NGOÀI public_html trên hosting.
 */

use Zika\Core\Config;
use Zika\Core\Log;

define('ZIKA_APP_DIR', __DIR__);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    // Dự phòng khi chưa chạy composer install: tự nạp lớp Zika\*. Thư viện ngoài (JWT, PHPMailer) sẽ không có.
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'Zika\\')) {
            $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 5)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

Config::load(getenv('ZIKA_CONFIG') ?: __DIR__ . '/config.php');

date_default_timezone_set((string) Config::get('timezone', 'Asia/Ho_Chi_Minh'));
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');

// Cảnh báo/thông báo của PHP coi như lỗi thật: lộ ra sớm thay vì âm thầm ra dữ liệu sai.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Log::error('fatal', ['message' => $e['message'], 'file' => $e['file'], 'line' => $e['line']]);
    }
});
