<?php
declare(strict_types=1);

namespace Zika\Core;

use Normalizer;

/** Tiện ích chuỗi dùng chung. */
final class Str
{
    /** Chuỗi ngẫu nhiên base64url (mặc định 32 byte → 43 ký tự). */
    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function nfc(string $s): string
    {
        return class_exists(Normalizer::class) ? (string) Normalizer::normalize($s, Normalizer::FORM_C) : $s;
    }

    /** Bỏ dấu tiếng Việt nhưng GIỮ chữ Đ/đ (dùng cho chữ viết tắt ảnh đại diện). */
    public static function stripMarks(string $s): string
    {
        if (!class_exists(Normalizer::class)) {
            return $s;
        }
        $d = (string) Normalizer::normalize($s, Normalizer::FORM_D);
        return (string) Normalizer::normalize((string) preg_replace('/\p{Mn}+/u', '', $d), Normalizer::FORM_C);
    }

    /**
     * Chữ viết tắt: chữ cái đầu của 2 từ cuối, in hoa, bỏ dấu (04 §2.2).
     * “Minh Anh” → “MA”, “Phạm Đức Long” → “ĐL”, “Lan” → “LA”.
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return '?';
        }
        if (count($words) === 1) {
            return mb_strtoupper(self::stripMarks(mb_substr($words[0], 0, 2)));
        }
        $last = array_slice($words, -2);
        return mb_strtoupper(self::stripMarks(mb_substr($last[0], 0, 1) . mb_substr($last[1], 0, 1)));
    }

    /** “Nguyễn Minh Anh” → “Minh Anh” (2 từ cuối, dùng khi tạo tài khoản Google). */
    public static function displayFromFull(string $full): string
    {
        $words = preg_split('/\s+/u', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $name = implode(' ', array_slice($words, -2));
        return mb_substr($name !== '' ? $name : 'Bạn mới', 0, 60);
    }

    /** minhanh@example.com → m•••@example.com */
    public static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return $email;
        }
        return mb_substr($email, 0, 1) . '•••' . substr($email, $at);
    }

    public static function lower(string $s): string
    {
        return mb_strtolower(trim($s));
    }

    /** Chỉ toàn chữ Hán (cho phép 儿). */
    public static function isHan(string $s): bool
    {
        return $s !== '' && (bool) preg_match('/^\p{Han}+$/u', $s);
    }

    /** Thời lượng kiểu VN: “3 phút”, “1 giờ 10 phút”. */
    public static function duration(int $sec): string
    {
        $min = intdiv(max(0, $sec), 60);
        $h = intdiv($min, 60);
        $m = $min % 60;
        if ($h === 0) {
            return $m . ' phút';
        }
        return $m === 0 ? $h . ' giờ' : $h . ' giờ ' . $m . ' phút';
    }

    /** Số kiểu VN: 1.284 */
    public static function num(int|float $n, int $decimals = 0): string
    {
        return number_format($n, $decimals, ',', '.');
    }

    public static function limit(string $s, int $max): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max - 1) . '…';
    }
}
