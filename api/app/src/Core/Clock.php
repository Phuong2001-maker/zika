<?php
declare(strict_types=1);

namespace Zika\Core;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Nguồn thời gian duy nhất của app (giờ Việt Nam, +07:00).
 * Kiểm thử có thể “đóng băng” thời gian bằng Clock::freeze().
 */
final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    public static function tz(): DateTimeZone
    {
        static $tz = null;
        return $tz ??= new DateTimeZone((string) Config::get('timezone', 'Asia/Ho_Chi_Minh'));
    }

    public static function now(): DateTimeImmutable
    {
        return self::$frozen ?? new DateTimeImmutable('now', self::tz());
    }

    public static function freeze(?DateTimeImmutable $at): void
    {
        self::$frozen = $at;
    }

    /** Chuỗi DATETIME cho MySQL: 2026-10-04 20:41:00 */
    public static function sql(?DateTimeInterface $t = null): string
    {
        return ($t ?? self::now())->format('Y-m-d H:i:s');
    }

    /** DATETIME(3) có mili giây. */
    public static function sqlMs(?DateTimeInterface $t = null): string
    {
        return ($t ?? self::now())->format('Y-m-d H:i:s.v');
    }

    public static function parse(string $sql): DateTimeImmutable
    {
        return new DateTimeImmutable($sql, self::tz());
    }

    /** ISO 8601 có múi giờ cho API: 2026-10-04T20:41:00+07:00 (null giữ null). */
    public static function iso(?string $sql): ?string
    {
        return $sql === null || $sql === '' ? null : self::parse($sql)->format('Y-m-d\TH:i:sP');
    }

    /** ISO 8601 có mili giây (dùng cho đồng hồ bài kiểm tra). */
    public static function isoMs(?string $sql): ?string
    {
        return $sql === null || $sql === '' ? null : self::parse($sql)->format('Y-m-d\TH:i:s.vP');
    }

    public static function ts(string $sql): int
    {
        return self::parse($sql)->getTimestamp();
    }

    /** Thời điểm hiện tại tính bằng mili giây (cho so sánh hạn bài kiểm tra). */
    public static function ms(?DateTimeInterface $t = null): int
    {
        $t ??= self::now();
        return (int) $t->format('U') * 1000 + (int) $t->format('v');
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function addSeconds(int $seconds, ?DateTimeImmutable $from = null): DateTimeImmutable
    {
        return ($from ?? self::now())->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
    }
}
