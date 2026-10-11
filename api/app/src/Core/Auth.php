<?php
declare(strict_types=1);

namespace Zika\Core;

/**
 * Phiên đăng nhập bằng cookie zk_sid (02 §6.1).
 * Cookie chứa 32 byte ngẫu nhiên; DB chỉ lưu sha256 của nó.
 */
final class Auth
{
    public const COOKIE = 'zk_sid';
    public const REMEMBER_SEC = 30 * 86400; // “Ghi nhớ trên máy này”: 30 ngày, gia hạn trượt
    public const SHORT_SEC = 12 * 3600;     // không ghi nhớ: 12 giờ không dùng thì hết hạn
    private const TOUCH_EVERY_SEC = 60;     // giảm số lần ghi DB: chỉ cập nhật last_used_at mỗi 60 giây

    /**
     * Đọc cookie, gắn user + session vào request. Không ném lỗi (route công khai vẫn chạy);
     * nếu phiên thuộc tài khoản đã bị khoá → ghi nhận để App trả ACCOUNT_LOCKED cho route cần đăng nhập.
     */
    public static function attach(Request $r): void
    {
        $token = $r->cookie(self::COOKIE);
        if ($token === null) {
            return;
        }
        $row = Db::one(
            'SELECT s.id AS s_id, s.user_id, s.remember, s.device_type, s.os, s.browser, s.last_used_at,
                    s.expires_at, s.revoked_at, u.*
             FROM auth_sessions s JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ?',
            [hash('sha256', $token)],
        );
        if ($row === null) {
            Cookie::forget(self::COOKIE);
            return;
        }
        if ($row['status'] === 'locked') {
            Cookie::forget(self::COOKIE);
            $r->lockedAccount = true;
            return;
        }
        $now = Clock::now();
        if ($row['revoked_at'] !== null || Clock::parse((string) $row['expires_at']) <= $now) {
            Cookie::forget(self::COOKIE);
            return;
        }

        $session = [
            'id' => (int) $row['s_id'], 'remember' => (int) $row['remember'], 'device_type' => $row['device_type'],
            'os' => $row['os'], 'browser' => $row['browser'],
        ];
        $user = $row;
        foreach (['s_id', 'user_id', 'remember', 'device_type', 'os', 'browser', 'last_used_at', 'expires_at', 'revoked_at'] as $k) {
            unset($user[$k]);
        }
        $r->user = $user;
        $r->session = $session;
        Log::$userId = (int) $user['id'];

        // Gia hạn trượt.
        if ($now->getTimestamp() - Clock::ts((string) $row['last_used_at']) >= self::TOUCH_EVERY_SEC) {
            $ttl = $session['remember'] ? self::REMEMBER_SEC : self::SHORT_SEC;
            Db::update('auth_sessions', [
                'last_used_at' => Clock::sql($now),
                'expires_at' => Clock::sql(Clock::addSeconds($ttl, $now)),
            ], ['id' => $session['id']]);
            if ($session['remember']) {
                Cookie::queue(self::COOKIE, $token, $now->getTimestamp() + self::REMEMBER_SEC);
            }
        }
    }

    /**
     * Tạo phiên mới cho user, đặt cookie phiên và token CSRF mới.
     * @param array<string,mixed> $user
     * @return string token CSRF mới
     */
    public static function login(Request $r, array $user, bool $remember): string
    {
        $token = Str::token();
        $ua = Ua::parse($r->userAgent());
        $now = Clock::now();
        $sessionId = Db::insert('auth_sessions', [
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'remember' => $remember ? 1 : 0,
            'device_type' => $ua['device'],
            'os' => $ua['os'],
            'browser' => $ua['browser'],
            'ip' => substr($r->ip, 0, 45),
            'created_at' => Clock::sql($now),
            'last_used_at' => Clock::sql($now),
            'expires_at' => Clock::sql(Clock::addSeconds($remember ? self::REMEMBER_SEC : self::SHORT_SEC, $now)),
        ]);
        Cookie::queue(self::COOKIE, $token, $remember ? $now->getTimestamp() + self::REMEMBER_SEC : 0);
        $r->user = $user;
        $r->session = ['id' => $sessionId, 'remember' => $remember ? 1 : 0, 'device_type' => $ua['device'], 'os' => $ua['os'], 'browser' => $ua['browser']];
        return Csrf::ensure($r, true);
    }

    public static function logout(Request $r): void
    {
        if ($r->session !== null) {
            Db::exec('UPDATE auth_sessions SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL', [$r->session['id']]);
        }
        Cookie::forget(self::COOKIE);
        $r->user = null;
        $r->session = null;
    }

    /** Thu hồi mọi phiên của người dùng (trừ phiên $exceptSessionId nếu có). */
    public static function revokeAll(int $userId, ?int $exceptSessionId = null): void
    {
        if ($exceptSessionId === null) {
            Db::exec('UPDATE auth_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
            return;
        }
        Db::exec(
            'UPDATE auth_sessions SET revoked_at = NOW() WHERE user_id = ? AND id <> ? AND revoked_at IS NULL',
            [$userId, $exceptSessionId],
        );
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    /** Kiểm tra mật khẩu; tự băm lại nếu thuật toán đã cũ. @param array<string,mixed> $user */
    public static function verifyPassword(array $user, string $password): bool
    {
        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        if (password_needs_rehash($hash, $algo)) {
            Db::update('users', ['password_hash' => self::hashPassword($password)], ['id' => (int) $user['id']]);
        }
        return true;
    }

    /** Quy tắc mật khẩu (02 §6.1): ≥ 8 ký tự, có cả chữ và số, ≤ 72 ký tự. Trả câu lỗi hoặc null. */
    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < 8 || !preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
            return 'Mật khẩu cần ít nhất 8 ký tự, có cả chữ và số.';
        }
        if (strlen($password) > 72) {
            return 'Mật khẩu dài tối đa 72 ký tự.';
        }
        return null;
    }
}
