<?php
declare(strict_types=1);

namespace Zika;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Lớp bọc PDO mỏng.
 *
 * Vì sao không dùng ORM: API chỉ có vài chục truy vấn, phần lớn là đọc danh sách
 * theo lesson_id. Prepared statement thẳng vừa nhanh vừa đọc được ngay SQL thật,
 * tránh một tầng trừu tượng không cần thiết cho dự án cỡ này.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = Config::get('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Bắt buộc tắt emulate để prepared statement là thật, số trả về
                // đúng kiểu int chứ không phải string.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // Không ném thẳng message của PDO ra ngoài: nó chứa user/host CSDL.
            throw new RuntimeException('Không kết nối được CSDL: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /** Chạy một câu lệnh và trả về statement đã execute. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Lấy mọi dòng. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Lấy một dòng, hoặc null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Lấy ô đầu tiên của dòng đầu tiên, hoặc null. */
    public static function val(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** INSERT và trả về id mới. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn($c) => "`$c`", $cols)),
            implode(', ', array_map(static fn($c) => ":$c", $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** UPDATE theo điều kiện id = ?, trả về số dòng bị ảnh hưởng. */
    public static function update(string $table, array $data, string $whereCol, mixed $whereVal): int
    {
        if ($data === []) {
            return 0;
        }
        $set = implode(', ', array_map(static fn($c) => "`$c` = :$c", array_keys($data)));
        $sql = sprintf('UPDATE `%s` SET %s WHERE `%s` = :__w', $table, $set, $whereCol);
        $st  = self::run($sql, $data + ['__w' => $whereVal]);
        return $st->rowCount();
    }

    public static function begin(): void
    {
        self::pdo()->beginTransaction();
    }

    public static function commit(): void
    {
        self::pdo()->commit();
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /**
     * Chạy một khối trong transaction, tự rollback nếu có lỗi.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function tx(callable $fn): mixed
    {
        self::begin();
        try {
            $out = $fn();
            self::commit();
            return $out;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }
}
