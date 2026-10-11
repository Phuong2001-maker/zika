<?php
declare(strict_types=1);

namespace Zika\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Lớp mỏng quanh PDO. Mọi câu SQL đều dùng prepared statement;
 * tên bảng/cột truyền vào insert()/update() chỉ được lấy từ code, không bao giờ từ người dùng.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static int $txDepth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) Config::get('db.host', 'localhost'),
                (int) Config::get('db.port', 3306),
                (string) Config::get('db.name', ''),
            );
            self::$pdo = new PDO($dsn, (string) Config::get('db.user', ''), (string) Config::get('db.pass', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '+07:00', NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        return self::$pdo;
    }

    /** Dùng trong kiểm thử hoặc khi cài đặt (kết nối chưa chọn database). */
    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$txDepth = 0;
    }

    /** @param array<int|string,mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $positional = array_is_list($params);
        foreach ($params as $k => $v) {
            $key = $positional ? (int) $k + 1 : (str_starts_with((string) $k, ':') ? (string) $k : ':' . $k);
            $type = match (true) {
                is_int($v)  => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_INT,
                $v === null => PDO::PARAM_NULL,
                default     => PDO::PARAM_STR,
            };
            $st->bindValue($key, is_bool($v) ? (int) $v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Cột đầu tiên của mọi dòng. @return list<mixed> */
    public static function col(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Giá trị ô đầu tiên (null nếu không có dòng). */
    public static function val(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $row */
    public static function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn (string $c) => "`$c`", $cols)),
            implode(', ', array_fill(0, count($cols), '?')),
        );
        self::run($sql, array_values($row));
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $set
     * @param array<string,mixed> $where  so khớp bằng “=” nối AND
     */
    public static function update(string $table, array $set, array $where): int
    {
        if ($set === []) {
            return 0;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', array_map(static fn (string $c) => "`$c` = ?", array_keys($set))),
            implode(' AND ', array_map(static fn (string $c) => "`$c` = ?", array_keys($where))),
        );
        return self::exec($sql, [...array_values($set), ...array_values($where)]);
    }

    /**
     * Danh sách “?” cho mệnh đề IN. Mảng rỗng trả về “NULL” để câu SQL vẫn hợp lệ (không khớp dòng nào).
     * @param list<mixed> $values
     */
    public static function in(array $values): string
    {
        return $values === [] ? 'NULL' : implode(',', array_fill(0, count($values), '?'));
    }

    /**
     * Chạy trong transaction; lồng nhau thì chỉ transaction ngoài cùng commit/rollback.
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$txDepth === 0) {
            $pdo->beginTransaction();
        }
        self::$txDepth++;
        try {
            $result = $fn();
            self::$txDepth--;
            if (self::$txDepth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            self::$txDepth--;
            if (self::$txDepth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
