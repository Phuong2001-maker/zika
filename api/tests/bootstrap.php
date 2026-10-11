<?php
declare(strict_types=1);

/**
 * Khởi động PHPUnit: nạp autoload, cấu hình riêng cho kiểm thử.
 * Kiểm thử cần DB dùng database riêng (mặc định “zika_test”, đổi bằng biến ZIKA_TEST_DB),
 * lấy host/user/mật khẩu từ app/config.php của máy dev. Không bao giờ chạm DB thật.
 */

define('ZIKA_APP_DIR', dirname(__DIR__) . '/app');
require ZIKA_APP_DIR . '/vendor/autoload.php';

use Zika\Core\Config;

$base = is_file(ZIKA_APP_DIR . '/config.php') ? require ZIKA_APP_DIR . '/config.php' : [];
$tmp = sys_get_temp_dir() . '/zika-test-' . getmypid();

Config::set([
    'app_url' => 'http://localhost:5173',
    'env' => 'local',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'db' => [
        'host' => $base['db']['host'] ?? '127.0.0.1',
        'port' => $base['db']['port'] ?? 3306,
        'name' => getenv('ZIKA_TEST_DB') ?: 'zika_test',
        'user' => $base['db']['user'] ?? 'zika',
        'pass' => $base['db']['pass'] ?? '',
    ],
    'cookie' => ['domain' => '', 'secure' => false],
    'cors_origins' => [],
    'app_key' => str_repeat('k', 64),
    'google' => ['client_id' => '', 'client_secret' => ''],
    'mail' => ['host' => '', 'from' => 'no-reply@zika.test', 'from_name' => 'Zìkǎ'],
    'paths' => [
        'uploads_dir' => $tmp . '/uploads',
        'uploads_url' => '/uploads',
        'storage_dir' => $tmp . '/storage',
    ],
    'install_token' => '',
]);

date_default_timezone_set('Asia/Ho_Chi_Minh');
mb_internal_encoding('UTF-8');
