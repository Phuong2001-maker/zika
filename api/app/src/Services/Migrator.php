<?php
declare(strict_types=1);

namespace Zika\Services;

use PDO;
use RuntimeException;

/** Chạy các tệp migrations/NNN_*.sql chưa chạy, ghi vào schema_migrations (02 §9). */
final class Migrator
{
    public function __construct(private readonly PDO $pdo, private readonly string $dir)
    {
    }

    /** @return list<string> tên các tệp vừa chạy */
    public function migrate(): array
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            filename VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done = $this->pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(rtrim($this->dir, '/\\') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            $this->runFile($file);
            $st = $this->pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
            $st->execute([$name]);
            $ran[] = $name;
        }
        return $ran;
    }

    public function runFile(string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Không đọc được $file");
        }
        foreach (self::split($sql) as $i => $stmt) {
            try {
                $this->pdo->exec($stmt);
            } catch (\PDOException $e) {
                throw new RuntimeException(basename($file) . ' — câu lệnh ' . ($i + 1) . ': ' . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Tách tệp SQL thành từng câu lệnh theo dấu “;” nằm ngoài chuỗi và chú thích.
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';
            if ($quote !== null) {
                $buf .= $ch;
                if ($ch === '\\' && $quote !== '`') {
                    $buf .= $next;
                    $i++;
                } elseif ($ch === $quote) {
                    if ($next === $quote) { // '' trong chuỗi
                        $buf .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buf .= "\n";
                continue;
            }
            if ($ch === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buf .= "\n";
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }
}
