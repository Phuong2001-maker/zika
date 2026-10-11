<?php
declare(strict_types=1);

namespace Zika\Core;

/**
 * Tiện ích pinyin phía server (06 §7). Bản trình duyệt: web/src/lib/utils/pinyin.ts.
 * Chỉ làm phần server thật sự cần: xác định thanh điệu để trả kèm dữ liệu chữ Hán.
 */
final class Pinyin
{
    /** Nguyên âm mang dấu, xếp theo thanh 1 → 4. */
    private const MARKS = [
        1 => 'āēīōūǖĀĒĪŌŪǕ',
        2 => 'áéíóúǘÁÉÍÓÚǗ',
        3 => 'ǎěǐǒǔǚǍĚǏǑǓǙ',
        4 => 'àèìòùǜÀÈÌÒÙǛ',
    ];

    /** Thanh điệu của một âm tiết: 1–4, hoặc 5 khi không có dấu (thanh nhẹ). */
    public static function tone(string $syllable): int
    {
        foreach (self::MARKS as $tone => $chars) {
            foreach (preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
                if (mb_strpos($syllable, $c) !== false) {
                    return $tone;
                }
            }
        }
        return 5;
    }

    /** Chuỗi chỉ gồm chữ Hán (cho phép 儿) — dùng khi kiểm tra dữ liệu admin nhập (04 §10.3). */
    public static function isHanzi(string $s): bool
    {
        return $s !== '' && (bool) preg_match('/^\p{Han}+$/u', $s);
    }

    /** Tách một chuỗi thành từng chữ Hán, bỏ trùng, giữ thứ tự xuất hiện. */
    public static function chars(string $s): array
    {
        $out = [];
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
            if (preg_match('/^\p{Han}$/u', $c) && !in_array($c, $out, true)) {
                $out[] = $c;
            }
        }
        return $out;
    }
}
