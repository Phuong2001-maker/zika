<?php
declare(strict_types=1);

/**
 * Cấu hình API Zika.
 *
 * Sao file này thành config.php rồi sửa cho máy của bạn.
 * config.php KHÔNG commit vào git (xem .gitignore).
 *
 * Mọi khoá đều có thể ghi đè bằng biến môi trường cùng tên dạng ZIKA_*,
 * ví dụ ZIKA_DB_PASS — tiện khi chạy trong Docker hoặc CI.
 */
return [
    // ── Kết nối CSDL ─────────────────────────────────────────────
    'db' => [
        'host'    => getenv('ZIKA_DB_HOST') ?: '127.0.0.1',
        'port'    => (int) (getenv('ZIKA_DB_PORT') ?: 3306),
        'name'    => getenv('ZIKA_DB_NAME') ?: 'zika',
        'user'    => getenv('ZIKA_DB_USER') ?: 'root',
        'pass'    => getenv('ZIKA_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    // ── Phiên đăng nhập ──────────────────────────────────────────
    // Token ngẫu nhiên 32 byte, chỉ lưu bản sha256 trong CSDL.
    'auth' => [
        'token_days'    => 30,   // token sống bao lâu
        'reset_minutes' => 30,   // link đặt lại mật khẩu sống bao lâu
    ],

    // ── CORS ─────────────────────────────────────────────────────
    // Khi dev, Vite chạy ở cổng khác nên phải cho phép origin đó.
    // Khi deploy chung một domain thì để mảng rỗng là an toàn nhất.
    'cors' => [
        'origins' => [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:4173',
        ],
    ],

    // ── Email ────────────────────────────────────────────────────
    // 'log' = ghi ra storage/mail.log thay vì gửi thật (tiện khi dev).
    'mail' => [
        'driver' => getenv('ZIKA_MAIL_DRIVER') ?: 'log',
        'from'   => 'no-reply@zika.local',
    ],

    // ── Đăng nhập Google ─────────────────────────────────────────
    // Để trống thì nút "Tiếp tục với Google" bị tắt ở giao diện.
    'google' => [
        'client_id' => getenv('ZIKA_GOOGLE_CLIENT_ID') ?: '',
    ],

    'debug' => (bool) (getenv('ZIKA_DEBUG') ?: false),
];
