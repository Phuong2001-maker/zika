<?php
declare(strict_types=1);

/**
 * Cài đặt lần đầu / cập nhật cấu trúc DB (02 §9).
 *   php tools/install.php            kiểm tra môi trường, chạy migration, tạo admin đầu tiên (hỏi email/tên/mật khẩu)
 *   php tools/install.php --migrate  chỉ chạy migration mới (khi cập nhật phiên bản)
 *   php tools/install.php --demo     nạp thêm dữ liệu mẫu (tự bật khi env = local). KHÔNG dùng trên web thật.
 *   php tools/install.php --reset-demo-password
 *                                    tạo / đặt lại hai tài khoản mẫu admin@zika.local và hocvien@zika.local
 *                                    về mật khẩu Zika@2026 (chỉ máy dev; alias: --demo-users)
 * Biến môi trường ZIKA_ADMIN_EMAIL / ZIKA_ADMIN_NAME / ZIKA_ADMIN_PASSWORD để chạy không cần hỏi.
 */

if (PHP_SAPI !== 'cli') {
    exit("Chỉ chạy bằng dòng lệnh.\n");
}
require dirname(__DIR__) . '/bootstrap.php';

use Zika\Core\Config;
use Zika\Core\HttpError;
use Zika\Services\Installer;

$args = array_slice($argv, 1);
$migrateOnly = in_array('--migrate', $args, true);
$demoUsersOnly = in_array('--reset-demo-password', $args, true) || in_array('--demo-users', $args, true);
$demo = in_array('--demo', $args, true) || (Config::isLocal() && !$migrateOnly && !$demoUsersOnly);

$problems = Installer::problems();
if ($problems !== []) {
    fwrite(STDERR, "Môi trường chưa đạt:\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}

// Chỉ tạo / đặt lại hai tài khoản mẫu — không chạy migration, không nạp lại dữ liệu.
if ($demoUsersOnly) {
    if (!Config::isLocal() && !in_array('--force', $args, true)) {
        fwrite(STDERR, 'Hai tài khoản mẫu chỉ dùng khi env = local (thêm --force nếu thật sự muốn).' . PHP_EOL);
        exit(1);
    }
    $emails = Installer::seedDemoUsers();
    echo 'Mật khẩu ' . Installer::DEMO_PASSWORD . ' cho: ' . implode(', ', $emails) . PHP_EOL;
    exit(0);
}

$ran = Installer::migrate();
echo $ran === [] ? "Không có migration mới.\n" : 'Đã chạy: ' . implode(', ', $ran) . "\n";
if ($migrateOnly) {
    exit(0);
}

if ($demo) {
    if (!Config::isLocal() && !in_array('--force', $args, true)) {
        fwrite(STDERR, "--demo chỉ dùng khi env = local (thêm --force nếu thật sự muốn).\n");
        exit(1);
    }
    $n = Installer::seedDemo();
    echo "Đã nạp dữ liệu mẫu; tải được dữ liệu nét cho $n chữ.\n";
    $emails = Installer::seedDemoUsers();
    echo 'Hai tài khoản mẫu (mật khẩu ' . Installer::DEMO_PASSWORD . '): ' . implode(', ', $emails) . PHP_EOL;
}

if (!Installer::hasAdmin()) {
    $ask = static function (string $label, string $env, bool $hidden = false): string {
        $v = getenv($env);
        if (is_string($v) && $v !== '') {
            return $v;
        }
        echo $label . ': ';
        if ($hidden && DIRECTORY_SEPARATOR === '/') {
            system('stty -echo');
            $line = (string) fgets(STDIN);
            system('stty echo');
            echo "\n";
            return trim($line);
        }
        return trim((string) fgets(STDIN));
    };
    echo "Tạo tài khoản quản trị đầu tiên.\n";
    while (true) {
        $email = $ask('Email', 'ZIKA_ADMIN_EMAIL');
        $name = $ask('Tên', 'ZIKA_ADMIN_NAME');
        $pass = $ask('Mật khẩu (≥ 8 ký tự, có chữ và số)', 'ZIKA_ADMIN_PASSWORD', true);
        try {
            Installer::createAdmin($email, $name, $pass);
            break;
        } catch (HttpError $e) {
            fwrite(STDERR, implode("\n", $e->fields) . "\n");
            if (getenv('ZIKA_ADMIN_EMAIL')) {
                exit(1);
            }
        }
    }
}

echo 'Cài đặt xong. Đăng nhập tại ' . Config::appUrl() . "/login\n";
