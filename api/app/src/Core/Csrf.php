<?php
declare(strict_types=1);

namespace Zika\Core;

/**
 * Chống CSRF kiểu “double submit cookie” (02 §6.2):
 * cookie zk_csrf (không HttpOnly) + header X-CSRF-Token phải trùng nhau.
 * sendBeacon không gửi được header → nhận token trong body form `csrf`.
 */
final class Csrf
{
    public const COOKIE = 'zk_csrf';

    /** Trả token hiện có, hoặc tạo mới (luôn tạo mới khi $rotate, ví dụ lúc đăng nhập). */
    public static function ensure(Request $r, bool $rotate = false): string
    {
        $current = $r->cookie(self::COOKIE);
        if (!$rotate && $current !== null && preg_match('/^[A-Za-z0-9_-]{43}$/', $current)) {
            return $current;
        }
        $token = Str::token();
        Cookie::queue(self::COOKIE, $token, 0, false);
        return $token;
    }

    public static function verify(Request $r): void
    {
        $cookie = $r->cookie(self::COOKIE);
        $sent = $r->header('x-csrf-token');
        if ($sent === '' && is_string($r->input('csrf'))) {
            $sent = (string) $r->input('csrf');
        }
        if ($cookie === null || $sent === '' || !hash_equals($cookie, $sent)) {
            throw new HttpError(403, 'CSRF');
        }
    }
}
