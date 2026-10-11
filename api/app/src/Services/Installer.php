<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Auth;
use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Str;

/** Cài đặt lần đầu / chạy migration (02 §9) — dùng chung cho tools/install.php và trang /api/install. */
final class Installer
{
    /** @return list<string> các vấn đề môi trường (rỗng = ổn) */
    public static function problems(): array
    {
        $p = [];
        if (PHP_VERSION_ID < 80400) {
            $p[] = 'Cần PHP 8.4 trở lên (đang là ' . PHP_VERSION . ').';
        }
        foreach (['pdo_mysql', 'mbstring', 'intl', 'gd', 'openssl', 'curl', 'fileinfo', 'zip'] as $ext) {
            if (!extension_loaded($ext)) {
                $p[] = "Thiếu extension PHP: $ext.";
            }
        }
        if (extension_loaded('gd') && !(gd_info()['WebP Support'] ?? false)) {
            $p[] = 'GD chưa hỗ trợ WebP.';
        }
        foreach ([Config::storageDir(), Config::uploadsDir()] as $dir) {
            if ($dir === '') {
                $p[] = 'Chưa cấu hình paths.uploads_dir trong config.php.';
                continue;
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                $p[] = "Thư mục không ghi được: $dir";
            }
        }
        foreach (['cache/content', 'cache/stats', 'logs', 'backups', 'tmp'] as $sub) {
            $d = Config::storageDir($sub);
            if (!is_dir($d)) {
                @mkdir($d, 0755, true);
            }
        }
        if (strlen((string) Config::get('app_key', '')) < 32) {
            $p[] = 'Chưa đặt app_key (≥ 32 ký tự) trong config.php.';
        }
        return $p;
    }

    /** @return list<string> */
    public static function migrate(): array
    {
        return (new Migrator(Db::pdo(), ZIKA_APP_DIR . '/migrations'))->migrate();
    }

    public static function hasAdmin(): bool
    {
        try {
            return (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin'") > 0;
        } catch (\PDOException) {
            return false; // chưa có bảng users
        }
    }

    public static function createAdmin(string $email, string $name, string $password): int
    {
        $email = Str::lower($email);
        $fields = [];
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fields['email'] = 'Email không hợp lệ.';
        }
        if (mb_strlen(trim($name)) < 2 || mb_strlen(trim($name)) > 60) {
            $fields['name'] = 'Tên cần 2–60 ký tự.';
        }
        if (($pp = Auth::passwordProblem($password)) !== null) {
            $fields['password'] = $pp;
        }
        if ($fields !== []) {
            throw HttpError::validation($fields);
        }
        $existing = Db::one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing !== null) {
            Db::exec("UPDATE users SET role = 'admin', password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?", [Auth::hashPassword($password), $existing['id']]);
            return (int) $existing['id'];
        }
        $name = Str::nfc(trim($name));
        return Db::insert('users', [
            'email' => $email,
            'password_hash' => Auth::hashPassword($password),
            'full_name' => $name,
            'display_name' => $name,
            'role' => 'admin',
            'signup_method' => 'email',
            'email_verified_at' => Clock::sql(),
            'terms_accepted_at' => Clock::sql(),
            'created_at' => Clock::sql(),
        ]);
    }

    /** Mật khẩu hai tài khoản mẫu ở máy dev — README §"Hai tài khoản mẫu". */
    public const DEMO_PASSWORD = 'Zika@2026';

    /** @var list<array{email: string, name: string, role: string}> */
    private const DEMO_USERS = [
        ['email' => 'admin@zika.local', 'name' => 'Quan tri vien', 'role' => 'admin'],
        ['email' => 'hocvien@zika.local', 'name' => 'Minh Anh', 'role' => 'learner'],
    ];

    /**
     * Tạo (hoặc đặt lại mật khẩu) hai tài khoản mẫu: một admin, một người học.
     * Chỉ dùng ở máy dev — mật khẩu nằm ngay trong mã nguồn nên không được chạy trên web thật.
     *
     * @return list<string> email đã xử lý
     */
    public static function seedDemoUsers(string $password = self::DEMO_PASSWORD): array
    {
        if (($pp = Auth::passwordProblem($password)) !== null) {
            throw HttpError::validation(['password' => $pp]);
        }
        $done = [];
        foreach (self::DEMO_USERS as $u) {
            $hash = Auth::hashPassword($password);
            $existing = Db::one('SELECT id FROM users WHERE email = ?', [$u['email']]);
            if ($existing !== null) {
                // Giữ nguyên tiến độ học, chỉ đặt lại mật khẩu và mở lại khoá nếu có.
                Db::exec(
                    "UPDATE users SET password_hash = ?, role = ?, status = 'active',
                                     email_verified_at = COALESCE(email_verified_at, NOW())
                     WHERE id = ?",
                    [$hash, $u['role'], $existing['id']],
                );
            } else {
                Db::insert('users', [
                    'email' => $u['email'],
                    'password_hash' => $hash,
                    'full_name' => $u['name'],
                    'display_name' => $u['name'],
                    'role' => $u['role'],
                    'signup_method' => 'email',
                    'email_verified_at' => Clock::sql(),
                    'terms_accepted_at' => Clock::sql(),
                    'created_at' => Clock::sql(),
                ]);
            }
            $done[] = $u['email'];
        }
        return $done;
    }

    /** Nạp dữ liệu mẫu (chỉ máy dev) rồi tải dữ liệu nét chữ. Trả số chữ tải được. */
    public static function seedDemo(): int
    {
        if ((int) Db::val('SELECT COUNT(*) FROM lessons') === 0) {
            (new Migrator(Db::pdo(), ZIKA_APP_DIR . '/demo'))->runFile(ZIKA_APP_DIR . '/demo/seed-demo.sql');
        }
        $ok = 0;
        foreach (Db::col("SELECT id FROM characters WHERE stroke_data_status = 'missing'") as $id) {
            $ok += HanziDataService::fetch((int) $id) ? 1 : 0;
        }
        return $ok;
    }
}
