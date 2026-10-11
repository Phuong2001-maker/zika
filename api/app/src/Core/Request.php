<?php
declare(strict_types=1);

namespace Zika\Core;

/** Một request HTTP đã được phân tích. Controller chỉ đọc dữ liệu qua lớp này. */
final class Request
{
    public const MAX_JSON_BYTES = 1_048_576; // 1 MB (02 §4.1)

    /** @var array<string,string> tham số lấy từ đường dẫn, ví dụ {id} */
    public array $params = [];

    /** @var array<string,mixed>|null người đang đăng nhập (dòng users) */
    public ?array $user = null;

    /** @var array<string,mixed>|null phiên đăng nhập hiện tại (dòng auth_sessions) */
    public ?array $session = null;

    /** Cookie phiên thuộc tài khoản đã bị khoá → route cần đăng nhập trả ACCOUNT_LOCKED thay vì 401. */
    public bool $lockedAccount = false;

    /**
     * @param array<string,mixed>  $query
     * @param array<string,mixed>  $body
     * @param array<string,mixed>  $files
     * @param array<string,string> $cookies
     * @param array<string,string> $headers khoá viết thường
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $files = [],
        public readonly array $cookies = [],
        public readonly array $headers = [],
        public readonly string $ip = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?? '/'));
        // /api/... → /...; riêng /go/{id} đi thẳng (04 §1).
        if (str_starts_with($path, '/api/') || $path === '/api') {
            $path = substr($path, 4) ?: '/';
        }
        $path = '/' . trim($path, '/');

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $body = [];
        $ctype = strtolower($headers['content-type'] ?? '');
        if (str_contains($ctype, 'application/json')) {
            $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($len > self::MAX_JSON_BYTES) {
                throw new HttpError(413, 'TOO_LARGE', 'Dữ liệu gửi lên quá lớn.');
            }
            $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_JSON_BYTES + 1);
            if (strlen($raw) > self::MAX_JSON_BYTES) {
                throw new HttpError(413, 'TOO_LARGE', 'Dữ liệu gửi lên quá lớn.');
            }
            if (trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw HttpError::invalid('Dữ liệu JSON không hợp lệ.');
                }
                $body = $decoded;
            }
        } elseif ($method !== 'GET') {
            $body = $_POST;
        }

        return new self(
            $method,
            $path,
            $_GET,
            $body,
            $_FILES,
            array_map('strval', $_COOKIE),
            $headers,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );
    }

    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function cookie(string $name): ?string
    {
        $v = $this->cookies[$name] ?? null;
        return $v === null || $v === '' ? null : $v;
    }

    public function userAgent(): string
    {
        return $this->header('user-agent');
    }

    /** Giá trị trong body (JSON hoặc form). */
    public function input(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->body) ? $this->body[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body);
    }

    /** Giá trị trên query string. */
    public function q(string $key, mixed $default = null): mixed
    {
        $v = $this->query[$key] ?? null;
        return $v === null || $v === '' ? $default : $v;
    }

    public function qInt(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $v = $this->query[$key] ?? null;
        $n = is_numeric($v) ? (int) $v : $default;
        if ($min !== null) {
            $n = max($min, $n);
        }
        if ($max !== null) {
            $n = min($max, $n);
        }
        return $n;
    }

    /** Tham số số nguyên trên đường dẫn ({id}). */
    public function id(string $name = 'id'): int
    {
        return (int) ($this->params[$name] ?? 0);
    }

    public function param(string $name): string
    {
        return (string) ($this->params[$name] ?? '');
    }

    /** @return array<string,mixed> */
    public function requireUser(): array
    {
        if ($this->user === null) {
            throw new HttpError(401, 'UNAUTHENTICATED');
        }
        return $this->user;
    }

    public function userId(): int
    {
        return (int) $this->requireUser()['id'];
    }

    public function role(): string
    {
        return (string) ($this->user['role'] ?? 'guest');
    }

    public function isStaff(): bool
    {
        return in_array($this->role(), ['editor', 'admin'], true);
    }

    /** E/A gửi ?preview=1 → xem cả bài Nháp, không lưu tiến độ (04 §4). Người học gửi thì bị bỏ qua. */
    public function isPreview(): bool
    {
        return $this->isStaff() && (string) ($this->query['preview'] ?? '') === '1';
    }
}
