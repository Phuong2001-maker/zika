<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\Json;
use Zika\Core\Mailer;
use Zika\Core\Settings;
use Zika\Core\Str;

/** Người dùng: dựng đối tượng User cho API (04 §2.2), tạo tài khoản, cài đặt học. */
final class UserService
{
    public const SELF_LEVELS = ['beginner', 'hsk12', 'hsk34', 'hsk5'];
    public const SELF_LEVEL_LABELS = ['beginner' => 'Mới bắt đầu', 'hsk12' => 'HSK 1–2', 'hsk34' => 'HSK 3–4', 'hsk5' => 'HSK 5 trở lên'];
    public const ROLE_LABELS = ['learner' => 'Người học', 'editor' => 'Biên tập nội dung', 'admin' => 'Quản trị viên'];

    /** @param array<string,mixed> $u dòng bảng users @return array<string,mixed> */
    public static function toApi(array $u): array
    {
        $display = (string) $u['display_name'];
        return [
            'id' => (int) $u['id'],
            'email' => (string) $u['email'],
            'displayName' => $display,
            'fullName' => (string) ($u['full_name'] !== '' ? $u['full_name'] : $display),
            'initials' => Str::initials($display !== '' ? $display : (string) $u['full_name']),
            'avatarUrl' => $u['avatar_path'] ?: null,
            'role' => (string) $u['role'],
            'signupMethod' => (string) $u['signup_method'],
            'hasPassword' => !empty($u['password_hash']),
            'selfLevel' => (string) $u['self_level'],
            'dailyGoalMin' => (int) $u['daily_goal_min'],
            'currentLevelId' => $u['current_level_id'] === null ? null : (int) $u['current_level_id'],
            'createdAt' => Clock::iso((string) $u['created_at']),
            'stats' => ['studySec' => (int) $u['total_study_sec'], 'lessonsDone' => (int) $u['lessons_done']],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Db::one('SELECT * FROM users WHERE email = ?', [Str::lower($email)]);
    }

    /**
     * Tạo tài khoản mới. Gửi email chào mừng ngay nếu email đã được xác minh.
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public static function create(array $fields): array
    {
        $id = Db::insert('users', $fields + ['created_at' => Clock::sql()]);
        $user = self::find($id);
        if (!empty($user['email_verified_at'])) {
            self::sendWelcome($user);
        }
        return $user;
    }

    /**
     * Email “Chào mừng bạn mới” — chỉ gửi cho từng người, không bao giờ gửi hàng loạt (04 §10.8).
     * @param array<string,mixed> $user
     */
    public static function sendWelcome(array $user): void
    {
        $a = Db::one("SELECT id, title, body FROM announcements WHERE is_auto_welcome = 1 AND status = 'published' LIMIT 1");
        if ($a === null) {
            return;
        }
        $mail = Mailer::compose((string) $a['title'], [(string) $a['body']], 'Bắt đầu học', Config::appUrl() . '/learn/flashcards');
        Mailer::queue((string) $user['email'], (string) $a['title'], $mail['html'], $mail['text'], 'welcome');
        Db::exec('UPDATE announcements SET emails_sent = emails_sent + 1 WHERE id = ?', [$a['id']]);
    }

    /** Cài đặt học mặc định (03 §4.1); giọng đọc lấy theo cài đặt của admin. @return array<string,mixed> */
    public static function defaultSettings(): array
    {
        return [
            'voice' => (string) Settings::value('content', 'default_voice', 'xiaoxiao'),
            'rate' => 1,
            'autoSpeak' => true,
            'cardFront' => 'vi',
            'shuffle' => true,
            'showExamples' => true,
            'hanziFont' => 'sans',
            'fontSize' => 'm',
            'toneColors' => true,
            'showHanViet' => true,
            'theme' => 'light',
            'remindAt' => '20:00',
            'testBeep' => true,
            'dialogue' => ['showPinyin' => true, 'showVi' => true],
        ];
    }

    /**
     * Trộn với mặc định: khoá thiếu → mặc định, khoá lạ → bỏ, giá trị sai kiểu → mặc định.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalizeSettings(array $input): array
    {
        $d = self::defaultSettings();
        $pick = static fn (string $k, array $allowed) => in_array($input[$k] ?? null, $allowed, true) ? $input[$k] : $d[$k];
        $bool = static fn (string $k) => is_bool($input[$k] ?? null) ? $input[$k] : $d[$k];
        $rateIn = is_numeric($input['rate'] ?? null) ? (float) $input['rate'] : null;
        $dlg = is_array($input['dialogue'] ?? null) ? $input['dialogue'] : [];
        return [
            'voice' => $pick('voice', ['xiaoxiao', 'yunxi', 'browser']),
            'rate' => match ($rateIn) {
                0.75 => 0.75,
                1.25 => 1.25,
                default => 1,
            },
            'autoSpeak' => $bool('autoSpeak'),
            'cardFront' => $pick('cardFront', ['vi', 'hanzi', 'pinyin']),
            'shuffle' => $bool('shuffle'),
            'showExamples' => $bool('showExamples'),
            'hanziFont' => $pick('hanziFont', ['sans', 'serif', 'kai']),
            'fontSize' => $pick('fontSize', ['s', 'm', 'l']),
            'toneColors' => $bool('toneColors'),
            'showHanViet' => $bool('showHanViet'),
            'theme' => $pick('theme', ['light', 'dark', 'system']),
            'remindAt' => array_key_exists('remindAt', $input) && in_array($input['remindAt'], [null, '20:00', '07:00'], true) ? $input['remindAt'] : $d['remindAt'],
            'testBeep' => $bool('testBeep'),
            'dialogue' => [
                'showPinyin' => is_bool($dlg['showPinyin'] ?? null) ? $dlg['showPinyin'] : true,
                'showVi' => is_bool($dlg['showVi'] ?? null) ? $dlg['showVi'] : true,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function settings(int $userId): array
    {
        $raw = Db::val('SELECT data FROM user_settings WHERE user_id = ?', [$userId]);
        return self::normalizeSettings(is_string($raw) ? (array) Json::decode($raw, []) : []);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function saveSettings(int $userId, array $input): array
    {
        $s = self::normalizeSettings($input);
        Db::exec(
            'INSERT INTO user_settings (user_id, data) VALUES (?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data)',
            [$userId, Json::encode($s)],
        );
        return $s;
    }
}
