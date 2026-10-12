<?php
declare(strict_types=1);

namespace Zika\Services;

use RuntimeException;
use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\Log;
use Zika\Core\Settings;

/**
 * Sao lưu toàn bộ database bằng PHP rồi nén gzip (02 §4.4 · cron/backup.php).
 *
 * Cố ý KHÔNG gọi `exec('mysqldump')`: hosting chung thường chặn hàm exec và
 * không cài sẵn mysqldump. Ở đây đọc `SHOW CREATE TABLE` rồi ghi INSERT theo
 * lô 500 dòng, dùng con trỏ không đệm để bảng lớn không ngốn RAM.
 */
final class BackupService
{
    /** Số bản sao lưu giữ lại; bản cũ hơn bị xoá sau mỗi lần chạy. */
    public const KEEP = 7;
    private const ROWS_PER_INSERT = 500;

    /**
     * Xuất DB ra `storage/backups/zika-YYYYmmdd-HHMM.sql.gz`.
     *
     * @return array{file:string,bytes:int,tables:int,rows:int}
     */
    public static function run(): array
    {
        $dir = Config::storageDir('backups');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Không tạo được thư mục sao lưu: ' . $dir);
        }

        $name = 'zika-' . Clock::now()->format('Ymd-Hi') . '.sql.gz';
        $path = $dir . '/' . $name;
        $tmp = $path . '.part';

        $gz = gzopen($tmp, 'wb9');
        if ($gz === false) {
            throw new RuntimeException('Không mở được file sao lưu: ' . $tmp);
        }

        $pdo = Db::pdo();
        $tables = 0;
        $rows = 0;
        try {
            gzwrite($gz, self::header());
            foreach (self::tableNames() as $table) {
                $tables++;
                $rows += self::dumpTable($gz, $table);
            }
            gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($tmp);
            throw $e;
        }
        gzclose($gz);
        unset($pdo);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Không đổi tên được file sao lưu.');
        }

        $bytes = (int) filesize($path);
        Settings::set('backup', ['last_at' => Clock::sql(), 'last_size' => $bytes]);
        self::rotate($dir);
        Log::info('backup.done', ['file' => $name, 'bytes' => $bytes, 'tables' => $tables, 'rows' => $rows]);

        return ['file' => $name, 'bytes' => $bytes, 'tables' => $tables, 'rows' => $rows];
    }

    /** Xoá các bản sao lưu cũ, chỉ giữ KEEP bản mới nhất. @return list<string> tên file đã xoá */
    public static function rotate(?string $dir = null, int $keep = self::KEEP): array
    {
        $dir ??= Config::storageDir('backups');
        $files = glob($dir . '/zika-*.sql.gz') ?: [];
        // Tên file có sẵn ngày giờ nên sắp theo tên là sắp theo thời gian.
        sort($files);
        $old = array_slice($files, 0, max(0, count($files) - $keep));
        $gone = [];
        foreach ($old as $f) {
            if (@unlink($f)) {
                $gone[] = basename($f);
            }
        }
        return $gone;
    }

    /** @return list<array{file:string,bytes:int,at:string}> các bản sao lưu đang có, mới nhất trước */
    public static function listFiles(?string $dir = null): array
    {
        $dir ??= Config::storageDir('backups');
        $out = [];
        foreach (glob($dir . '/zika-*.sql.gz') ?: [] as $f) {
            $out[] = ['file' => basename($f), 'bytes' => (int) filesize($f), 'at' => date('Y-m-d H:i:s', (int) filemtime($f))];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['file'], $a['file']));
        return $out;
    }

    private static function header(): string
    {
        return "-- Zìkǎ · sao lưu database\n"
            . '-- Tạo lúc ' . Clock::sql() . "\n"
            . '-- Nạp lại: gunzip < file.sql.gz | mysql -u USER -p TEN_DB' . "\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS = 0;\n"
            . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    }

    /** @return list<string> */
    private static function tableNames(): array
    {
        $out = [];
        foreach (Db::pdo()->query('SHOW FULL TABLES') as $row) {
            $vals = array_values((array) $row);
            // Bỏ VIEW: chỉ sao lưu bảng thật.
            if (($vals[1] ?? '') === 'BASE TABLE') {
                $out[] = (string) $vals[0];
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Ghi một bảng: DROP + CREATE rồi các INSERT theo lô.
     *
     * @param resource $gz
     * @return int số dòng đã ghi
     */
    private static function dumpTable($gz, string $table): int
    {
        $name = str_replace('`', '``', $table);
        $create = Db::pdo()->query('SHOW CREATE TABLE `' . $name . '`')->fetch(\PDO::FETCH_NUM);
        gzwrite($gz, "\n-- ---------- $table ----------\n");
        gzwrite($gz, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
        gzwrite($gz, ($create[1] ?? '') . ";\n");

        // Con trỏ không đệm: bảng vài trăm nghìn dòng vẫn chạy được trên hosting ít RAM.
        $pdo = Db::pdo();
        $attr = self::bufferedAttr();
        $buffered = true;
        if ($attr !== null) {
            $buffered = (bool) $pdo->getAttribute($attr);
            $pdo->setAttribute($attr, false);
        }
        $st = $pdo->query('SELECT * FROM `' . $name . '`');

        $rows = 0;
        $batch = [];
        $cols = null;
        foreach ($st as $row) {
            /** @var array<string,mixed> $row */
            $cols ??= '(`' . implode('`, `', array_keys($row)) . '`)';
            $vals = [];
            foreach ($row as $v) {
                $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            $rows++;
            if (count($batch) >= self::ROWS_PER_INSERT) {
                gzwrite($gz, 'INSERT INTO `' . $table . '` ' . $cols . " VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch !== []) {
            gzwrite($gz, 'INSERT INTO `' . $table . '` ' . $cols . " VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        if ($attr !== null) {
            $pdo->setAttribute($attr, $buffered);
        }

        return $rows;
    }

    /**
     * Mã thuộc tính “truy vấn có đệm” của driver MySQL.
     * PHP 8.4 trở lên có hằng trong lớp Pdo\Mysql; PDO::MYSQL_ATTR_* đã bị khai tử ở 8.5.
     * Trả null khi không tìm thấy (driver khác MySQL) — khi đó cứ dùng mặc định.
     */
    private static function bufferedAttr(): ?int
    {
        foreach (['Pdo\Mysql::ATTR_USE_BUFFERED_QUERY', 'PDO::MYSQL_ATTR_USE_BUFFERED_QUERY'] as $const) {
            if (defined($const)) {
                return (int) constant($const);
            }
        }
        return null;
    }
}
