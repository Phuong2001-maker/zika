<?php
declare(strict_types=1);

namespace Zika\Core;

/** Đọc user-agent ra loại thiết bị, hệ điều hành, trình duyệt (đủ dùng cho thống kê, không cần chính xác tuyệt đối). */
final class Ua
{
    /** @return array{device:string,os:string,browser:string} */
    public static function parse(string $ua): array
    {
        $device = 'desktop';
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|IEMobile|Opera Mini/i', $ua)) {
            $device = 'phone';
        }
        // iPadOS 13+ tự nhận là Macintosh; trình duyệt có cảm ứng mới phân biệt được — bỏ qua, coi là Máy tính.

        $os = match (true) {
            (bool) preg_match('/iPhone|iPod/i', $ua)   => 'iPhone',
            (bool) preg_match('/iPad/i', $ua)          => 'iPad',
            (bool) preg_match('/Android/i', $ua)       => 'Android',
            (bool) preg_match('/Windows/i', $ua)       => 'Windows',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/CrOS/i', $ua)          => 'ChromeOS',
            (bool) preg_match('/Linux/i', $ua)         => 'Linux',
            default                                    => 'Khác',
        };

        $browser = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua)     => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $ua)  => 'Samsung',
            (bool) preg_match('/coc_coc_browser/i', $ua) => 'Cốc Cốc',
            (bool) preg_match('/Zalo/i', $ua)            => 'Zalo',
            (bool) preg_match('/FBAN|FBAV/i', $ua)       => 'Facebook',
            (bool) preg_match('/Firefox|FxiOS/i', $ua)   => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $ua)    => 'Chrome',
            (bool) preg_match('/Safari/i', $ua)          => 'Safari',
            default                                      => 'Khác',
        };

        return ['device' => $device, 'os' => $os, 'browser' => $browser];
    }

    public static function deviceLabel(?string $device): string
    {
        return match ($device) {
            'phone'  => 'Điện thoại',
            'tablet' => 'Tablet',
            'desktop' => 'Máy tính',
            default  => '—',
        };
    }
}
