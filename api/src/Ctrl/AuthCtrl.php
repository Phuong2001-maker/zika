<?php
declare(strict_types=1);

namespace Zika\Ctrl;

use Zika\Auth;
use Zika\Config;
use Zika\Db;
use Zika\Http\ApiError;
use Zika\Http\Req;
use Zika\Http\Res;
use Zika\Mail;
use Zika\Util;

/** Đăng ký, đăng nhập, quên mật khẩu, trang Tài khoản. */
final class AuthCtrl
{
    /** POST /api/auth/register */
    public static function register(Req $req): never
    {
        if (!self::siteFlag('allow_signup', true)) {
            throw ApiError::forbidden('Web đang tạm không nhận tài khoản mới.');
        }
        if (!self::siteFlag('login_email', true)) {
            throw ApiError::forbidden('Web chỉ cho đăng nhập bằng Google.');
        }

        $name  = $req->need('name');
        $email = mb_strtolower($req->need('email'));
        $pass  = $req->need('password');
        $level = $req->str('levelHint', 'new');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiError::badRequest('Email không đúng dạng.', ['email' => 'Email không đúng dạng.']);
        }
        if (mb_strlen($pass) < 8) {
            throw ApiError::badRequest('Mật khẩu cần ít nhất 8 ký tự.', ['password' => 'Cần ít nhất 8 ký tự.']);
        }
        if (!$req->bool('agree')) {
            throw ApiError::badRequest('Bạn cần đồng ý Điều khoản và Chính sách riêng tư.', ['agree' => 'Chưa đồng ý.']);
        }
        if (!in_array($level, ['new', 'hsk12', 'hsk34', 'hsk5'], true)) {
            $level = 'new';
        }
        if (Db::val('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            throw ApiError::conflict('Email này đã có tài khoản. Bạn thử đăng nhập xem.');
        }

        $userId = Db::tx(static function () use ($name, $email, $pass, $level): int {
            $id = Db::insert('users', [
                'name'       => $name,
                'email'      => $email,
                'pass_hash'  => Auth::hash($pass),
                'provider'   => 'email',
                'level_hint' => $level,
            ]);
            Db::insert('user_settings', ['user_id' => $id]);
            return $id;
        });

        $token = Auth::issueToken($userId, $req);
        $user  = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);

        Res::created(['token' => $token, 'user' => Auth::publicUser($user)]);
    }

    /** POST /api/auth/login */
    public static function login(Req $req): never
    {
        $email = mb_strtolower($req->need('email'));
        $pass  = $req->need('password');
        $key   = 'login:' . $req->ip() . ':' . $email;

        Auth::throttle($key);

        $user = Db::one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        // Cùng một câu báo lỗi cho "không có email" và "sai mật khẩu" để không
        // tiết lộ email nào đã đăng ký.
        if ($user === null || !Auth::verify($pass, $user['pass_hash'])) {
            throw new ApiError('Email hoặc mật khẩu không đúng.', 401, 'bad_credentials');
        }
        if ($user['status'] === 'locked') {
            throw ApiError::forbidden('Tài khoản của bạn đang bị khoá.');
        }

        Auth::clearThrottle($key);
        $token = Auth::issueToken((int) $user['id'], $req);

        Res::ok(['token' => $token, 'user' => Auth::publicUser($user)]);
    }

    /**
     * POST /api/auth/google
     *
     * Nhận id_token của Google Identity Services, xác minh qua tokeninfo rồi
     * tạo/tìm tài khoản. Nếu config chưa có client_id thì trả 501 để giao diện
     * tự ẩn nút Google.
     */
    public static function google(Req $req): never
    {
        $clientId = (string) Config::get('google.client_id', '');
        if ($clientId === '') {
            throw new ApiError('Web chưa cấu hình đăng nhập Google.', 501, 'not_configured');
        }
        if (!self::siteFlag('login_google', true)) {
            throw ApiError::forbidden('Đăng nhập Google đang tắt.');
        }

        $idToken = $req->need('idToken');
        $raw = @file_get_contents(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken),
            false,
            stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]])
        );
        $info = Util::jsonDecode($raw ?: '', []);

        if (!is_array($info) || ($info['aud'] ?? '') !== $clientId || empty($info['email'])) {
            throw ApiError::unauthorized('Không xác minh được tài khoản Google.');
        }
        if (($info['email_verified'] ?? 'false') !== 'true' && ($info['email_verified'] ?? false) !== true) {
            throw ApiError::unauthorized('Email Google này chưa được xác minh.');
        }

        $email = mb_strtolower((string) $info['email']);
        $sub   = (string) ($info['sub'] ?? '');
        $name  = (string) ($info['name'] ?? $email);

        $user = Db::one('SELECT * FROM users WHERE google_sub = ? OR email = ? LIMIT 1', [$sub, $email]);

        if ($user === null) {
            if (!self::siteFlag('allow_signup', true)) {
                throw ApiError::forbidden('Web đang tạm không nhận tài khoản mới.');
            }
            $id = Db::tx(static function () use ($name, $email, $sub): int {
                $id = Db::insert('users', [
                    'name'              => $name,
                    'email'             => $email,
                    'provider'          => 'google',
                    'google_sub'        => $sub,
                    'email_verified_at' => date('Y-m-d H:i:s'),
                ]);
                Db::insert('user_settings', ['user_id' => $id]);
                return $id;
            });
            $user = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        } elseif (empty($user['google_sub'])) {
            // Tài khoản email cũ, giờ đăng nhập bằng Google -> gắn sub lại.
            Db::update('users', ['google_sub' => $sub], 'id', $user['id']);
        }

        if ($user['status'] === 'locked') {
            throw ApiError::forbidden('Tài khoản của bạn đang bị khoá.');
        }

        $token = Auth::issueToken((int) $user['id'], $req);
        Res::ok(['token' => $token, 'user' => Auth::publicUser($user)]);
    }

    /** POST /api/auth/logout */
    public static function logout(Req $req): never
    {
        $token = $req->bearer();
        if ($token !== '') {
            Auth::revokeToken($token);
        }
        Res::ok();
    }

    /** POST /api/auth/forgot */
    public static function forgot(Req $req): never
    {
        $email = mb_strtolower($req->need('email'));
        Auth::throttle('forgot:' . $req->ip(), 6, 900);

        $user = Db::one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        // Luôn trả về như đã gửi, để không ai dò được email nào tồn tại.
        if ($user !== null && !empty($user['pass_hash'])) {
            $token = bin2hex(random_bytes(32));
            Db::insert('password_resets', [
                'user_id'    => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 60 * (int) Config::get('auth.reset_minutes', 30)),
            ]);
            Mail::sendReset($email, $token);
        }

        Res::ok(['sent' => true, 'maskedEmail' => Util::maskEmail($email)]);
    }

    /** POST /api/auth/reset */
    public static function reset(Req $req): never
    {
        $token = $req->need('token');
        $pass  = $req->need('password');

        if (mb_strlen($pass) < 8) {
            throw ApiError::badRequest('Mật khẩu cần ít nhất 8 ký tự.', ['password' => 'Cần ít nhất 8 ký tự.']);
        }

        $row = Db::one(
            'SELECT * FROM password_resets
              WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
              LIMIT 1',
            [hash('sha256', $token)]
        );
        if ($row === null) {
            throw ApiError::badRequest('Link đặt lại mật khẩu đã hết hạn hoặc đã dùng rồi.');
        }

        Db::tx(static function () use ($row, $pass): void {
            Db::update('users', ['pass_hash' => Auth::hash($pass)], 'id', $row['user_id']);
            Db::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id', $row['id']);
            // Đổi mật khẩu thì mọi thiết bị khác phải đăng nhập lại.
            Db::run('DELETE FROM auth_tokens WHERE user_id = ?', [$row['user_id']]);
        });

        Res::ok();
    }

    /** GET /api/me */
    public static function me(Req $req): never
    {
        $u = Auth::need($req);
        Res::ok([
            'user'     => Auth::publicUser($u),
            'settings' => self::settingsOf((int) $u['id']),
            'stats'    => self::statsOf((int) $u['id']),
        ]);
    }

    /** PATCH /api/me */
    public static function updateMe(Req $req): never
    {
        $u    = Auth::need($req);
        $data = [];

        if (($name = $req->str('name')) !== '') {
            $data['name'] = mb_substr($name, 0, 120);
        }
        if (($lv = $req->str('levelHint')) !== '' && in_array($lv, ['new', 'hsk12', 'hsk34', 'hsk5'], true)) {
            $data['level_hint'] = $lv;
        }
        if (($goal = $req->int('dailyGoalMin')) > 0) {
            $data['daily_goal_min'] = min(600, $goal);
        }

        if ($data !== []) {
            Db::update('users', $data, 'id', $u['id']);
        }

        $fresh = Db::one('SELECT * FROM users WHERE id = ?', [$u['id']]);
        Res::ok(['user' => Auth::publicUser($fresh)]);
    }

    /** POST /api/me/password — đặt mật khẩu lần đầu hoặc đổi mật khẩu. */
    public static function setPassword(Req $req): never
    {
        $u   = Auth::need($req);
        $new = $req->need('password');

        if (mb_strlen($new) < 8) {
            throw ApiError::badRequest('Mật khẩu cần ít nhất 8 ký tự.', ['password' => 'Cần ít nhất 8 ký tự.']);
        }
        // Đã có mật khẩu thì phải nhập đúng mật khẩu cũ.
        if (!empty($u['pass_hash']) && !Auth::verify($req->str('current'), $u['pass_hash'])) {
            throw ApiError::badRequest('Mật khẩu hiện tại không đúng.', ['current' => 'Không đúng.']);
        }

        Db::update('users', ['pass_hash' => Auth::hash($new)], 'id', $u['id']);
        Res::ok();
    }

    /** GET /api/me/settings */
    public static function getSettings(Req $req): never
    {
        $u = Auth::need($req);
        Res::ok(self::settingsOf((int) $u['id']));
    }

    /** PUT /api/me/settings */
    public static function putSettings(Req $req): never
    {
        $u = Auth::need($req);
        $b = $req->body();

        // Danh sách trắng: chỉ nhận đúng các khoá có trong bảng, giá trị phải
        // thuộc tập cho phép — tránh chèn giá trị lạ vào cột ENUM.
        $enums = [
            'front_side' => ['vi', 'hz', 'py'],
            'hz_font'    => ['sans', 'serif', 'brush'],
            'hz_size'    => ['sm', 'md', 'lg'],
            'theme'      => ['light', 'dark', 'system'],
            'voice'      => ['xiaoxiao', 'yunxi', 'browser'],
        ];
        $bools = ['autoplay', 'shuffle', 'show_examples', 'pinyin_color', 'show_hv', 'test_sound'];
        $map   = [
            'voice' => 'voice', 'speed' => 'speed', 'autoplay' => 'autoplay',
            'frontSide' => 'front_side', 'shuffle' => 'shuffle', 'showExamples' => 'show_examples',
            'hzFont' => 'hz_font', 'hzSize' => 'hz_size', 'pinyinColor' => 'pinyin_color',
            'showHv' => 'show_hv', 'theme' => 'theme', 'remindAt' => 'remind_at',
            'testSound' => 'test_sound',
        ];

        $data = [];
        foreach ($map as $in => $col) {
            if (!array_key_exists($in, $b)) {
                continue;
            }
            $v = $b[$in];
            if (in_array($col, $bools, true)) {
                $data[$col] = $req->bool($in) ? 1 : 0;
            } elseif ($col === 'speed') {
                $f = (float) $v;
                $data[$col] = in_array($f, [0.75, 1.0, 1.25], true) ? $f : 1.0;
            } elseif ($col === 'remind_at') {
                $s = trim((string) $v);
                $data[$col] = preg_match('/^\d{2}:\d{2}$/', $s) ? $s : null;
            } elseif (isset($enums[$col])) {
                if (in_array((string) $v, $enums[$col], true)) {
                    $data[$col] = (string) $v;
                }
            }
        }

        if ($data !== []) {
            // user_settings có thể chưa có dòng (tài khoản seed cũ).
            Db::run('INSERT IGNORE INTO user_settings (user_id) VALUES (?)', [$u['id']]);
            Db::update('user_settings', $data, 'user_id', $u['id']);
        }

        Res::ok(self::settingsOf((int) $u['id']));
    }

    /** GET /api/me/devices */
    public static function devices(Req $req): never
    {
        $u       = Auth::need($req);
        $current = hash('sha256', $req->bearer());

        $rows = Db::all(
            'SELECT id, device, platform, is_phone, token_hash, last_used_at, created_at
               FROM auth_tokens WHERE user_id = ? AND expires_at > NOW()
              ORDER BY last_used_at DESC',
            [$u['id']]
        );

        Res::ok(array_map(static fn($r) => [
            'id'       => (int) $r['id'],
            'device'   => $r['device'] !== '' ? $r['device'] : 'Thiết bị khác',
            'platform' => $r['platform'],
            'isPhone'  => (bool) $r['is_phone'],
            'current'  => hash_equals($current, $r['token_hash']),
            'lastUsed' => $r['last_used_at'],
            'since'    => $r['created_at'],
        ], $rows));
    }

    /** DELETE /api/me/devices/{id} */
    public static function revokeDevice(Req $req): never
    {
        $u = Auth::need($req);
        Db::run('DELETE FROM auth_tokens WHERE id = ? AND user_id = ?', [$req->paramInt('id'), $u['id']]);
        Res::ok();
    }

    /** DELETE /api/me — xoá tài khoản và toàn bộ tiến độ (ON DELETE CASCADE). */
    public static function deleteMe(Req $req): never
    {
        $u = Auth::need($req);

        // Có mật khẩu thì bắt nhập lại để chắc chắn đúng người.
        if (!empty($u['pass_hash']) && !Auth::verify($req->str('password'), $u['pass_hash'])) {
            throw ApiError::badRequest('Mật khẩu không đúng.', ['password' => 'Không đúng.']);
        }
        if ($u['role'] === 'admin') {
            throw ApiError::forbidden('Tài khoản quản trị không tự xoá được. Nhờ một admin khác đổi quyền trước.');
        }

        Db::run('DELETE FROM users WHERE id = ?', [$u['id']]);
        Res::ok();
    }

    // ─────────────────────────── helper ───────────────────────────

    public static function settingsOf(int $userId): array
    {
        $r = Db::one('SELECT * FROM user_settings WHERE user_id = ?', [$userId]);
        if ($r === null) {
            Db::run('INSERT IGNORE INTO user_settings (user_id) VALUES (?)', [$userId]);
            $r = Db::one('SELECT * FROM user_settings WHERE user_id = ?', [$userId]);
        }
        return [
            'voice'        => $r['voice'],
            'speed'        => (float) $r['speed'],
            'autoplay'     => (bool) $r['autoplay'],
            'frontSide'    => $r['front_side'],
            'shuffle'      => (bool) $r['shuffle'],
            'showExamples' => (bool) $r['show_examples'],
            'hzFont'       => $r['hz_font'],
            'hzSize'       => $r['hz_size'],
            'pinyinColor'  => (bool) $r['pinyin_color'],
            'showHv'       => (bool) $r['show_hv'],
            'theme'        => $r['theme'],
            'remindAt'     => $r['remind_at'],
            'testSound'    => (bool) $r['test_sound'],
        ];
    }

    /** Ba con số trên trang Tài khoản: giờ đã học, bài xong, cấp đang học. */
    public static function statsOf(int $userId): array
    {
        $secs = (int) (Db::val('SELECT total_seconds FROM users WHERE id = ?', [$userId]) ?? 0);

        $doneLessons = (int) (Db::val(
            'SELECT COUNT(DISTINCT lesson_id) FROM user_part_progress
              WHERE user_id = ? AND part = ? AND done_at IS NOT NULL',
            [$userId, 'study']
        ) ?? 0);

        $curHsk = Db::val(
            'SELECT l.hsk FROM user_part_progress p
               JOIN lessons l ON l.id = p.lesson_id
              WHERE p.user_id = ?
              ORDER BY p.updated_at DESC LIMIT 1',
            [$userId]
        );

        return [
            'hours'       => (int) floor($secs / 3600),
            'minutes'     => (int) floor(($secs % 3600) / 60),
            'doneLessons' => $doneLessons,
            'currentHsk'  => $curHsk === null ? null : (int) $curHsk,
        ];
    }

    /** Đọc một cờ bật/tắt trong bảng settings. */
    private static function siteFlag(string $key, bool $default): bool
    {
        $v = Db::val('SELECT v FROM settings WHERE k = ?', [$key]);
        if ($v === null) {
            return $default;
        }
        return in_array((string) $v, ['1', 'true', 'yes'], true);
    }
}
