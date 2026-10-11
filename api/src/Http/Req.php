<?php
declare(strict_types=1);

namespace Zika\Http;

/** Gói request hiện tại: method, path, query, body JSON, header. */
final class Req
{
    public readonly string $method;
    public readonly string $path;
    public readonly array $query;
    private ?array $body = null;

    /** Tham số lấy từ route, ví dụ /lessons/{id} -> ['id' => '12'] */
    public array $params = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Khi chạy sau một thư mục con (vd /zika/api/public), bỏ phần tiền tố
        // để route luôn bắt đầu bằng /api.
        $pos = strpos($path, '/api');
        if ($pos !== false && $pos > 0) {
            $path = substr($path, $pos);
        }
        $this->path  = '/' . trim($path, '/');
        $this->query = $_GET ?? [];
    }

    /** Body JSON đã decode. Form urlencoded cũng được chấp nhận. */
    public function body(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $raw  = file_get_contents('php://input') ?: '';
        $type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if ($raw === '') {
            $this->body = $_POST ?: [];
        } elseif (str_contains($type, 'json') || str_starts_with(ltrim($raw), '{') || str_starts_with(ltrim($raw), '[')) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw ApiError::badRequest('Body không phải JSON hợp lệ.');
            }
            $this->body = $decoded;
        } else {
            parse_str($raw, $parsed);
            $this->body = $parsed;
        }

        return $this->body;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->body()[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->body()[$key] ?? $default;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->body()[$key] ?? $default;
        if (is_bool($v)) {
            return $v;
        }
        if (is_numeric($v)) {
            return (int) $v === 1;
        }
        return in_array(strtolower((string) $v), ['true', 'yes', 'on', '1'], true);
    }

    public function arr(string $key): array
    {
        $v = $this->body()[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    /** Bắt buộc có và không rỗng. */
    public function need(string $key): string
    {
        $v = $this->str($key);
        if ($v === '') {
            throw ApiError::badRequest("Thiếu trường \"$key\".", [$key => 'Không được để trống.']);
        }
        return $v;
    }

    public function q(string $key, ?string $default = null): ?string
    {
        $v = $this->query[$key] ?? $default;
        return is_scalar($v) ? (string) $v : $default;
    }

    public function qInt(string $key, int $default = 0): int
    {
        $v = $this->query[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function param(string $key): string
    {
        return (string) ($this->params[$key] ?? '');
    }

    public function paramInt(string $key): int
    {
        return (int) ($this->params[$key] ?? 0);
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($_SERVER[$key] ?? '');
    }

    public function bearer(): string
    {
        $h = $this->header('Authorization');
        if (stripos($h, 'bearer ') === 0) {
            return trim(substr($h, 7));
        }
        // Một số môi trường (CGI) không chuyển Authorization vào $_SERVER.
        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0 && stripos($v, 'bearer ') === 0) {
                    return trim(substr($v, 7));
                }
            }
        }
        return '';
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public function ua(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
