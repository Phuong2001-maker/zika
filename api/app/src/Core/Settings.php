<?php
declare(strict_types=1);

namespace Zika\Core;

/** Cài đặt hệ thống trong bảng site_settings, luôn trộn với giá trị mặc định (03 §4.5). */
final class Settings
{
    public const DEFAULTS = [
        'site'    => ['name' => 'Zìkǎ', 'slogan' => 'Lật thẻ học chữ Hán, miễn phí cho mọi người', 'logo_path' => null, 'contact_email' => ''],
        'auth'    => ['google' => true, 'email' => true, 'require_email_verify' => true, 'allow_signup' => true],
        'content' => ['default_voice' => 'xiaoxiao', 'show_feedback_button' => true],
        'privacy' => ['visit_log_days' => 90, 'mask_emails' => true, 'privacy_page' => '', 'terms_page' => ''],
        'backup'  => ['daily' => true, 'last_at' => null, 'last_size' => 0],
        'support' => ['enabled' => false],
        'donate'  => ['enabled' => true, 'qr_path' => null, 'bank' => '', 'owner' => '', 'account' => '', 'memo' => 'UNG HO ZIKA'],
    ];

    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    /** @return array<string,mixed> */
    public static function get(string $key): array
    {
        if (!isset(self::$cache[$key])) {
            $raw = Db::val('SELECT v FROM site_settings WHERE k = ?', [$key]);
            $stored = is_string($raw) ? Json::decode($raw, []) : [];
            $defaults = self::DEFAULTS[$key] ?? [];
            // Chỉ giữ khoá đã biết (khoá lạ bỏ qua) khi khối có mặc định.
            self::$cache[$key] = $defaults === []
                ? (is_array($stored) ? $stored : [])
                : array_merge($defaults, array_intersect_key(is_array($stored) ? $stored : [], $defaults));
        }
        return self::$cache[$key];
    }

    public static function value(string $key, string $field, mixed $default = null): mixed
    {
        return self::get($key)[$field] ?? $default;
    }

    /** Gộp thay đổi vào khối cài đặt rồi lưu. @param array<string,mixed> $changes */
    public static function set(string $key, array $changes): void
    {
        $merged = array_merge(self::get($key), $changes);
        Db::exec(
            'INSERT INTO site_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            [$key, Json::encode($merged)],
        );
        self::$cache[$key] = $merged;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
