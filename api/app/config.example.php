<?php
// Sao chép thành config.php rồi điền giá trị thật. config.php KHÔNG được commit.
return [
    'app_url'  => 'https://zika.example.vn',   // không có dấu / cuối
    'env'      => 'production',                // 'local' khi dev
    'timezone' => 'Asia/Ho_Chi_Minh',
    'db' => [
        'host' => 'localhost', 'port' => 3306,
        'name' => 'xxx_zika', 'user' => 'xxx_zika', 'pass' => '',
    ],
    'cookie' => [
        'domain' => '',       // '' = domain hiện tại; tách domain thì '.zika.example.vn'
        'secure' => true,
    ],
    'cors_origins' => [],     // để trống khi frontend và API cùng domain
    'app_key' => '',          // 64 ký tự ngẫu nhiên: php -r "echo bin2hex(random_bytes(32));"
    'google' => [
        'client_id'     => '',
        'client_secret' => '',
    ],
    'mail' => [
        'host' => '', 'port' => 587, 'secure' => 'tls',
        'user' => '', 'pass' => '',
        'from' => 'no-reply@zika.example.vn', 'from_name' => 'Zìkǎ',
    ],
    'tts' => [                // [P3] để trống key = chỉ dùng giọng của trình duyệt
        'provider' => 'azure', 'key' => '', 'region' => 'southeastasia',
    ],
    'paths' => [
        'uploads_dir' => '/home/<user>/domains/<domain>/public_html/uploads',
        'uploads_url' => '/uploads',
        'storage_dir' => __DIR__ . '/storage',
    ],
    'install_token' => '',    // chuỗi ngẫu nhiên chỉ đặt khi cài lần đầu, xoá sau khi cài
];
