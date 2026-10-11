<?php
declare(strict_types=1);

namespace Zika;

/** Các hàm dùng chung, không phụ thuộc request. */
final class Util
{
    /**
     * Xác định thanh điệu của một âm tiết pinyin có dấu.
     * Trả về 1..4, hoặc 5 cho thanh nhẹ (không dấu).
     *
     * Giao diện tô màu pinyin theo thanh (class t1..t5), nên chỗ nào cũng cần
     * hàm này; bản JS ở web/src/lib/pinyin.js phải cho cùng kết quả.
     */
    public static function tone(string $syllable): int
    {
        // Mỗi nhóm là một nguyên âm với 4 dấu theo thứ tự thanh 1,2,3,4.
        $marks = [
            1 => ['ā', 'ē', 'ī', 'ō', 'ū', 'ǖ', 'Ā', 'Ē', 'Ī', 'Ō', 'Ū'],
            2 => ['á', 'é', 'í', 'ó', 'ú', 'ǘ', 'Á', 'É', 'Í', 'Ó', 'Ú'],
            3 => ['ǎ', 'ě', 'ǐ', 'ǒ', 'ǔ', 'ǚ', 'Ǎ', 'Ě', 'Ǐ', 'Ǒ', 'Ǔ'],
            4 => ['à', 'è', 'ì', 'ò', 'ù', 'ǜ', 'À', 'È', 'Ì', 'Ò', 'Ù'],
        ];
        foreach ($marks as $tone => $chars) {
            foreach ($chars as $c) {
                if (mb_strpos($syllable, $c) !== false) {
                    return $tone;
                }
            }
        }
        return 5;
    }

    /**
     * Tách chuỗi pinyin 'jī dàn' thành [['t' => 'jī', 'c' => 1], ...]
     * để giao diện tô màu từng âm.
     */
    public static function pinyinParts(string $pinyin): array
    {
        $out = [];
        foreach (preg_split('/\s+/u', trim($pinyin), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $syl) {
            $out[] = ['t' => $syl, 'c' => self::tone($syl)];
        }
        return $out;
    }

    /** Đoán thiết bị từ User-Agent: ['Android · Chrome', 'Android', true] */
    public static function device(string $ua): array
    {
        $isPhone  = (bool) preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua);
        $platform = 'Khác';
        foreach ([
            'iPhone'  => '/iPhone|iPod/i',
            'iPad'    => '/iPad/i',
            'Android' => '/Android/i',
            'Windows' => '/Windows NT/i',
            'macOS'   => '/Macintosh|Mac OS X/i',
            'Linux'   => '/Linux/i',
        ] as $name => $re) {
            if (preg_match($re, $ua)) {
                $platform = $name;
                break;
            }
        }

        $browser = 'Trình duyệt';
        foreach ([
            'Edge'    => '/Edg\//i',
            'Chrome'  => '/Chrome\//i',
            'Safari'  => '/Safari\//i',
            'Firefox' => '/Firefox\//i',
        ] as $name => $re) {
            if (preg_match($re, $ua)) {
                $browser = $name;
                break;
            }
        }

        return [$platform . ' · ' . $browser, $platform, $isPhone];
    }

    /** Chữ đầu của tên, dùng cho avatar: 'Nguyễn Minh Anh' -> 'MA' */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '?';
        }
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }
        $last = array_slice($parts, -2);
        return mb_strtoupper(mb_substr($last[0], 0, 1) . mb_substr($last[1], 0, 1));
    }

    /** Che email cho trang quản trị khi bật tuỳ chọn che bớt email. */
    public static function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_substr($user, 0, 1);
        return $keep . str_repeat('•', max(3, mb_strlen($user) - 1)) . '@' . $domain;
    }

    public static function jsonDecode(?string $raw, mixed $fallback = []): mixed
    {
        if ($raw === null || $raw === '') {
            return $fallback;
        }
        $v = json_decode($raw, true);
        return $v === null ? $fallback : $v;
    }

    public static function jsonEncode(mixed $v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /** So sánh câu trả lời dạng dịch: bỏ dấu câu, hoa thường, khoảng trắng. */
    public static function normalizeText(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[\p{P}\p{S}]+/u', ' ', $s) ?? $s;
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    public static function storagePath(string $sub = ''): string
    {
        $base = dirname(__DIR__) . '/storage';
        if (!is_dir($base)) {
            @mkdir($base, 0777, true);
        }
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }
}
