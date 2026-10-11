<?php
declare(strict_types=1);

namespace Zika\Http;

use RuntimeException;

/**
 * Lỗi "có chủ đích" của API: được bắt ở index.php và trả ra JSON đúng mã HTTP.
 * Mọi Throwable khác bị coi là bug và trả 500.
 */
class ApiError extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $code = 'bad_request',
        public readonly array $fields = []
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $msg, array $fields = []): self
    {
        return new self($msg, 400, 'bad_request', $fields);
    }

    public static function unauthorized(string $msg = 'Bạn cần đăng nhập.'): self
    {
        return new self($msg, 401, 'unauthorized');
    }

    public static function forbidden(string $msg = 'Bạn không có quyền làm việc này.'): self
    {
        return new self($msg, 403, 'forbidden');
    }

    public static function notFound(string $msg = 'Không tìm thấy.'): self
    {
        return new self($msg, 404, 'not_found');
    }

    public static function conflict(string $msg): self
    {
        return new self($msg, 409, 'conflict');
    }

    public static function tooMany(string $msg = 'Bạn thử hơi nhiều lần. Chờ một lát rồi làm lại.'): self
    {
        return new self($msg, 429, 'too_many');
    }
}
