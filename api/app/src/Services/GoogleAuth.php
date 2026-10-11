<?php
declare(strict_types=1);

namespace Zika\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;
use Throwable;
use Zika\Core\Config;
use Zika\Core\Cookie;
use Zika\Core\FileCache;
use Zika\Core\Json;
use Zika\Core\Request;
use Zika\Core\Str;

/**
 * Đăng nhập Google: OAuth 2.0 / OpenID Connect, luồng mã uỷ quyền phía server (02 §6.1).
 * Trạng thái (state, nonce, next…) giữ trong cookie zk_gstate ký HMAC bằng app_key, sống 10 phút.
 */
final class GoogleAuth
{
    public const STATE_COOKIE = 'zk_gstate';
    private const STATE_TTL = 600;
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public static function redirectUri(): string
    {
        return Config::appUrl() . '/api/auth/google/callback';
    }

    /** @param array{next:string,remember:bool,selfLevel:?string} $ctx */
    public static function startUrl(array $ctx): string
    {
        $state = Str::token(24);
        $nonce = Str::token(24);
        $payload = Json::encode($ctx + ['state' => $state, 'nonce' => $nonce, 'exp' => time() + self::STATE_TTL]);
        $value = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        Cookie::queue(self::STATE_COOKIE, $value . '.' . self::sign($value), time() + self::STATE_TTL);

        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => (string) Config::get('google.client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Đọc lại cookie trạng thái và kiểm tra state. Trả ngữ cảnh đã lưu, hoặc null nếu không hợp lệ.
     * @return array<string,mixed>|null
     */
    public static function readState(Request $r, string $state): ?array
    {
        $raw = $r->cookie(self::STATE_COOKIE);
        Cookie::forget(self::STATE_COOKIE);
        if ($raw === null || !str_contains($raw, '.')) {
            return null;
        }
        [$value, $sig] = explode('.', $raw, 2);
        if (!hash_equals(self::sign($value), $sig)) {
            return null;
        }
        $ctx = Json::decode((string) base64_decode(strtr($value, '-_', '+/')), null);
        if (!is_array($ctx) || ($ctx['exp'] ?? 0) < time() || !hash_equals((string) ($ctx['state'] ?? ''), $state)) {
            return null;
        }
        return $ctx;
    }

    /**
     * Đổi code lấy id_token rồi xác thực chữ ký + các trường bắt buộc.
     * @return array{sub:string,email:string,name:string,picture:?string}
     */
    public static function exchange(string $code, string $nonce): array
    {
        $resp = self::http(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => (string) Config::get('google.client_id'),
            'client_secret' => (string) Config::get('google.client_secret'),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        $idToken = (string) ($resp['id_token'] ?? '');
        if ($idToken === '') {
            throw new RuntimeException('Google không trả id_token.');
        }

        JWT::$leeway = 60;
        $claims = (array) JWT::decode($idToken, JWK::parseKeySet(self::jwks(), 'RS256'));

        $iss = (string) ($claims['iss'] ?? '');
        if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new RuntimeException('iss không hợp lệ.');
        }
        if (($claims['aud'] ?? '') !== (string) Config::get('google.client_id')) {
            throw new RuntimeException('aud không khớp.');
        }
        if (!hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('nonce không khớp.');
        }
        if (($claims['email_verified'] ?? false) !== true && ($claims['email_verified'] ?? '') !== 'true') {
            throw new RuntimeException('Email Google chưa xác minh.');
        }
        return [
            'sub' => (string) $claims['sub'],
            'email' => Str::lower((string) ($claims['email'] ?? '')),
            'name' => trim((string) ($claims['name'] ?? '')),
            'picture' => isset($claims['picture']) ? (string) $claims['picture'] : null,
        ];
    }

    /** Khoá công khai của Google, cache file theo Cache-Control max-age (mặc định 1 giờ). @return array<string,mixed> */
    private static function jwks(): array
    {
        $jwks = FileCache::remember('google', 'jwks', 3600, static fn () => self::http(self::JWKS_URL));
        if (!is_array($jwks) || !isset($jwks['keys'])) {
            FileCache::forget('google', 'jwks');
            throw new RuntimeException('Không tải được khoá Google.');
        }
        return $jwks;
    }

    /** @param array<string,string>|null $post @return array<string,mixed> */
    private static function http(string $url, ?array $post = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        if ($body === false || $status >= 400) {
            throw new RuntimeException("Gọi Google thất bại ($status) $err");
        }
        $data = Json::decode((string) $body, null);
        if (!is_array($data)) {
            throw new RuntimeException('Google trả dữ liệu không hợp lệ.');
        }
        return $data;
    }

    private static function sign(string $value): string
    {
        $key = (string) Config::get('app_key', '');
        if (strlen($key) < 32) {
            throw new RuntimeException('Chưa đặt app_key (≥ 32 ký tự) trong config.php.');
        }
        return hash_hmac('sha256', $value, $key);
    }

    /** Có lỗi gì khi gọi Google thì ghi log và báo chung. */
    public static function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            \Zika\Core\Log::warn('google.auth', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
