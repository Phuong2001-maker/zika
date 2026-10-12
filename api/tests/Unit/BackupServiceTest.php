<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\Settings;
use Zika\Services\BackupService;
use Zika\Tests\DbTestCase;

/** Sao lưu DB bằng PHP (02 §4.4): file gzip đọc được, giữ đúng 7 bản, tên file an toàn. */
final class BackupServiceTest extends DbTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = Config::storageDir('backups');
        foreach (glob($this->dir . '/zika-*.sql.gz') ?: [] as $f) {
            @unlink($f);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/zika-*.sql.gz') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function testRunWritesReadableDumpContainingData(): void
    {
        $uid = $this->makeUser(['display_name' => 'Người Sao Lưu']);

        $out = BackupService::run();

        self::assertMatchesRegularExpression('/^zika-\d{8}-\d{4}\.sql\.gz$/', $out['file']);
        self::assertGreaterThan(0, $out['bytes']);
        self::assertGreaterThan(10, $out['tables']);

        $path = $this->dir . '/' . $out['file'];
        self::assertFileExists($path);
        // Không còn file .part nào sót lại.
        self::assertSame([], glob($this->dir . '/*.part') ?: []);

        $sql = (string) gzdecode((string) file_get_contents($path));
        self::assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0;', $sql);
        self::assertStringContainsString('DROP TABLE IF EXISTS `users`;', $sql);
        self::assertStringContainsString('CREATE TABLE `users`', $sql);
        self::assertStringContainsString('Người Sao Lưu', $sql);
        self::assertStringContainsString('SET FOREIGN_KEY_CHECKS = 1;', $sql);
        unset($uid);

        // Ghi lại mốc sao lưu gần nhất để màn Cài đặt hiện được.
        Settings::clearCache();
        self::assertNotNull(Settings::value('backup', 'last_at'));
        self::assertSame($out['bytes'], (int) Settings::value('backup', 'last_size'));
    }

    public function testRotateKeepsOnlyNewest(): void
    {
        foreach (['zika-20260101-0300', 'zika-20260102-0300', 'zika-20260103-0300', 'zika-20260104-0300'] as $name) {
            file_put_contents($this->dir . '/' . $name . '.sql.gz', 'x');
        }

        $gone = BackupService::rotate($this->dir, 2);

        self::assertSame(['zika-20260101-0300.sql.gz', 'zika-20260102-0300.sql.gz'], $gone);
        $left = array_map('basename', glob($this->dir . '/zika-*.sql.gz') ?: []);
        sort($left);
        self::assertSame(['zika-20260103-0300.sql.gz', 'zika-20260104-0300.sql.gz'], $left);
    }

    public function testListFilesNewestFirst(): void
    {
        foreach (['zika-20260101-0300', 'zika-20260105-0300', 'zika-20260103-0300'] as $name) {
            file_put_contents($this->dir . '/' . $name . '.sql.gz', 'xyz');
        }

        $items = BackupService::listFiles($this->dir);

        self::assertSame(
            ['zika-20260105-0300.sql.gz', 'zika-20260103-0300.sql.gz', 'zika-20260101-0300.sql.gz'],
            array_column($items, 'file'),
        );
        self::assertSame(3, $items[0]['bytes']);
    }

    public function testDumpEscapesQuotesAndKeepsHanzi(): void
    {
        Db::insert('words', [
            'level_id' => 2,
            'hanzi' => '咖啡',
            'pinyin' => 'kāfēi',
            'han_viet' => 'CÀ PHÊ',
            'meaning_vi' => "cà phê 'ngon' \\ rất đậm",
            'pos' => 'n',
        ]);

        $out = BackupService::run();
        $sql = (string) gzdecode((string) file_get_contents($this->dir . '/' . $out['file']));

        self::assertStringContainsString('咖啡', $sql);
        self::assertStringContainsString('kāfēi', $sql);
        // Dấu nháy và dấu gạch chéo phải được thoát, nếu không nạp lại sẽ lỗi cú pháp.
        self::assertStringNotContainsString("'cà phê 'ngon'", $sql);
    }
}
