<?php
declare(strict_types=1);

namespace Zika\Http;

use Zika\Config;

/** Xuất JSON và xử lý CORS. */
final class Res
{
    public static function cors(): void
    {
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = (array) Config::get('cors.origins', []);

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Zika-Device');
            header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
            header('Access-Control-Max-Age: 600');
        }
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        // Dữ liệu học là riêng của từng người -> không cho proxy cache.
        header('Cache-Control: no-store');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    public static function ok(mixed $data = null): never
    {
        self::json($data === null ? ['ok' => true] : $data);
    }

    public static function created(mixed $data): never
    {
        self::json($data, 201);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function error(string $message, int $status = 400, string $code = 'bad_request', array $fields = []): never
    {
        $out = ['error' => ['code' => $code, 'message' => $message]];
        if ($fields !== []) {
            $out['error']['fields'] = $fields;
        }
        self::json($out, $status);
    }
}
