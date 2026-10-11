<?php
declare(strict_types=1);

/**
 * Bảng định tuyến = ghép các tệp trong src/routes/ theo thứ tự tên.
 * Mỗi dòng: [method, pattern, [Controller::class, 'method'], quyền P|L|E|A, tuỳ chọn?]
 * Tuỳ chọn: ['csrf' => false] cho các endpoint không thể gửi header (install, go).
 */
$routes = [];
$files = glob(__DIR__ . '/routes/*.php') ?: [];
sort($files, SORT_STRING);
foreach ($files as $file) {
    $routes = [...$routes, ...(require $file)];
}
return $routes;
