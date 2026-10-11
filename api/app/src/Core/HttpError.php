<?php
declare(strict_types=1);

namespace Zika\Core;

use RuntimeException;

/** Lỗi trả về cho trình duyệt theo chuẩn { error: { code, message, fields } } (04 §1.1). */
final class HttpError extends RuntimeException
{
    public const MESSAGES = [
        'UNAUTHENTICATED'    => 'Bạn cần đăng nhập để tiếp tục.',
        'BAD_CREDENTIALS'    => 'Email hoặc mật khẩu chưa đúng.',
        'FORBIDDEN'          => 'Bạn không có quyền làm việc này.',
        'ACCOUNT_LOCKED'     => 'Tài khoản của bạn đã bị khoá.',
        'EMAIL_NOT_VERIFIED' => 'Bạn chưa xác nhận email. Kiểm tra hộp thư nhé.',
        'SIGNUP_DISABLED'    => 'Hiện chưa mở đăng ký tài khoản mới.',
        'METHOD_DISABLED'    => 'Cách đăng nhập này đang tạm tắt.',
        'CSRF'               => 'Phiên làm việc đã cũ. Tải lại trang rồi thử lại.',
        'NOT_FOUND'          => 'Không tìm thấy nội dung.',
        'CONFLICT'           => 'Dữ liệu bị trùng.',
        'TEST_EXPIRED'       => 'Đã hết giờ làm bài.',
        'ATTEMPTS_EXCEEDED'  => 'Bạn đã dùng hết số lần làm bài kiểm tra này.',
        'TOKEN_INVALID'      => 'Link đã hết hạn hoặc đã được dùng.',
        'TOO_LARGE'          => 'Tệp quá lớn.',
        'BAD_FILE'           => 'Định dạng tệp không được hỗ trợ.',
        'VALIDATION'         => 'Thông tin chưa hợp lệ.',
        'RATE_LIMITED'       => 'Bạn thao tác quá nhanh. Đợi một chút rồi thử lại.',
        'METHOD_NOT_ALLOWED' => 'Phương thức không được hỗ trợ.',
        'SERVER'             => 'Có lỗi xảy ra, thử lại sau.',
    ];

    /**
     * @param array<string,string> $fields lỗi theo từng trường
     * @param array<string,mixed>  $extra  khoá thêm trong khối error (ví dụ attemptId)
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message = '',
        public readonly array $fields = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($message !== '' ? $message : (self::MESSAGES[$errorCode] ?? self::MESSAGES['SERVER']));
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, 'NOT_FOUND', $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, 'FORBIDDEN', $message);
    }

    /** @param array<string,string> $fields */
    public static function validation(array $fields, string $message = ''): self
    {
        return new self(422, 'VALIDATION', $message, $fields);
    }

    /** Lỗi kiểm tra chỉ có câu thông báo, không gắn trường cụ thể. */
    public static function invalid(string $message): self
    {
        return new self(422, 'VALIDATION', $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, 'CONFLICT', $message);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $err = ['code' => $this->errorCode, 'message' => $this->getMessage()];
        if ($this->fields !== []) {
            $err['fields'] = $this->fields;
        }
        return ['error' => $err + $this->extra];
    }
}
