<?php
// Router cho máy dev, giả lập .htaccess:  php -S 127.0.0.1:8080 api/dev-router.php
// /api/* và /go/* → API; /uploads/* → thư mục uploads dev; còn lại 404 (web chạy ở Vite :5173).
declare(strict_types=1);

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if (str_starts_with($path, '/uploads/')) {
    require_once __DIR__ . '/app/bootstrap.php';
    $file = Zika\Core\Config::uploadsDir(rawurldecode(substr($path, strlen('/uploads/'))));
    $real = realpath($file);
    $root = realpath(Zika\Core\Config::uploadsDir());
    if ($real === false || $root === false || !str_starts_with($real, $root) || !is_file($real)) {
        http_response_code(404);
        return true;
    }
    $types = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'mp3' => 'audio/mpeg', 'json' => 'application/json', 'svg' => 'image/svg+xml'];
    header('Content-Type: ' . ($types[strtolower(pathinfo($real, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=2592000');
    readfile($real);
    return true;
}

if (str_starts_with($path, '/api/') || $path === '/api' || preg_match('#^/go/\d+/?$#', $path)) {
    require_once __DIR__ . '/app/bootstrap.php';
    Zika\Core\App::run();
    return true;
}

http_response_code(404);
echo 'Zika dev API: dùng /api/... (web chạy ở http://localhost:5173)';
return true;
