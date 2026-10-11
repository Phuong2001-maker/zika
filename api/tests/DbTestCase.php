<?php
declare(strict_types=1);

namespace Zika\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Zika\Core\Clock;
use Zika\Core\Cookie;
use Zika\Core\Db;
use Zika\Core\Settings;
use Zika\Services\Migrator;

/**
 * Cơ sở cho kiểm thử cần MySQL/MariaDB: lần đầu tạo bảng bằng migrations,
 * mỗi test xoá sạch dữ liệu (trừ dữ liệu bắt buộc của 002_base) để các test độc lập.
 * Không kết nối được DB thì bỏ qua (markTestSkipped) thay vì báo lỗi.
 */
abstract class DbTestCase extends TestCase
{
    private static bool $migrated = false;

    /** Bảng giữ nguyên dữ liệu nền giữa các test. */
    private const KEEP = ['schema_migrations', 'levels', 'site_settings', 'announcements'];

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $pdo = Db::pdo();
        } catch (\PDOException $e) {
            self::markTestSkipped('Không kết nối được DB kiểm thử: ' . $e->getMessage());
        }
        if (!self::$migrated) {
            (new Migrator($pdo, ZIKA_APP_DIR . '/migrations'))->migrate();
            self::$migrated = true;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (!in_array($t, self::KEEP, true)) {
                $pdo->exec("TRUNCATE TABLE `$t`");
            }
        }
        $pdo->exec("DELETE FROM announcements WHERE is_auto_welcome = 0");
        $pdo->exec('UPDATE levels SET is_visible = 0');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        Settings::clearCache();
        Cookie::reset();
        Clock::freeze(null);
    }

    protected function tearDown(): void
    {
        Clock::freeze(null);
        parent::tearDown();
    }

    /** Tạo người dùng nhanh cho test. @param array<string,mixed> $over */
    protected function makeUser(array $over = []): int
    {
        static $n = 0;
        $n++;
        return Db::insert('users', $over + [
            'email' => "u$n@zika.test",
            'display_name' => "Người $n",
            'full_name' => "Người Thử $n",
            'signup_method' => 'email',
            'email_verified_at' => Clock::sql(),
            'created_at' => Clock::sql(),
        ]);
    }
}
