<?php
declare(strict_types=1);

/**
 * Cấu hình cho MÁY DEV NÀY. Không commit file này.
 * Thông tin MariaDB lấy từ D:\zika-devtools\README-dev.txt
 */
return [
    'db' => [
        'host'    => getenv('ZIKA_DB_HOST') ?: '127.0.0.1',
        'port'    => (int) (getenv('ZIKA_DB_PORT') ?: 3306),
        'name'    => getenv('ZIKA_DB_NAME') ?: 'zika',
        'user'    => getenv('ZIKA_DB_USER') ?: 'root',
        'pass'    => getenv('ZIKA_DB_PASS') ?: '562d8e5cb38d10771ed28891',
        'charset' => 'utf8mb4',
    ],

    'auth' => [
        'token_days'    => 30,
        'reset_minutes' => 30,
    ],

    'cors' => [
        'origins' => [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:4173',
            'http://127.0.0.1:4173',
        ],
    ],

    'mail' => [
        'driver' => 'log',
        'from'   => 'no-reply@zika.local',
    ],

    'google' => [
        'client_id' => getenv('ZIKA_GOOGLE_CLIENT_ID') ?: '',
    ],

    // Bật khi dev để lỗi 500 trả kèm chỗ sai; TẮT khi deploy.
    'debug' => true,
];
