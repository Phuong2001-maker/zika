<?php
declare(strict_types=1);

namespace Zika;

use Zika\Http\ApiError;
use Zika\Http\Req;

/**
 * Đăng nhập bằng token ngẫu nhiên gửi qua header Authorization: Bearer.
 *
 * Vì sao Bearer + localStorage chứ không phải cookie httpOnly:
 * frontend là SPA tĩnh, khi dev chạy ở cổng 5173 còn API ở 8000 nên cookie
 * cross-site cần SameSite=None + HTTPS, rất vướng. Đánh đổi: token nằm trong
 * localStorage nên nếu web bị XSS thì token có thể bị đọc. Bù lại:
 *   - token có hạn (mặc định 30 ngày) và lưu trong CSDL dưới dạng sha256,
 *   - người học xem và thu hồi được từng thiết bị ở trang Tài khoản,
 *   - mọi dữ liệu nhập đều được escape ở phía Svelte (không dùng @html).
 * Khi deploy chung một domain, có thể chuyển sang cookie httpOnly mà chỉ phải
 * sửa hàm tokenFromRequest() và lớp api.js ở frontend.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    /** Tạo token mới cho user, trả về token thật (chỉ lần này mới thấy được). */
    public static function issueToken(int $userId, Req $req): string
    {
        $token = bin2hex(random_bytes(32));
        [$device, $platform, $isPhone] = Util::device($req->ua());

        Db::insert('auth_tokens', [
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $token),
            'device'     => $device,
            'platform'   => $platform,
            'is_phone'   => $isPhone ? 1 : 0,
            'ip'         => $req->ip(),
            'expires_at' => date('Y-m-d H:i:s', time() + 86400 * (int) Config::get('auth.token_days', 30)),
        ]);

        return $token;
    }

    public static function revokeToken(string $token): void
    {
        Db::run('DELETE FROM auth_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
    }

    /** Người dùng hiện tại, hoặc null nếu chưa đăng nhập. */
    public static function user(Req $req): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $token = $req->bearer();
        if ($token === '') {
            return null;
        }

        $row = Db::one(
            'SELECT u.*, t.id AS token_id
               FROM auth_tokens t
               JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = ? AND t.expires_at > NOW()
              LIMIT 1',
            [hash('sha256', $token)]
        );

        if ($row === null) {
            return null;
        }
        if ($row['status'] === 'locked') {
            throw ApiError::forbidden('Tài khoản của bạn đang bị khoá.');
        }

        // Cập nhật dấu vết, nhưng không quá 1 lần/phút để đỡ ghi CSDL liên tục.
        Db::run(
            'UPDATE auth_tokens SET last_used_at = NOW()
              WHERE id = ? AND last_used_at < (NOW() - INTERVAL 60 SECOND)',
            [$row['token_id']]
        );
        Db::run(
            'UPDATE users SET last_seen_at = NOW()
              WHERE id = ? AND (last_seen_at IS NULL OR last_seen_at < (NOW() - INTERVAL 60 SECOND))',
            [$row['id']]
        );

        self::$user = $row;
        return $row;
    }

    /** Bắt buộc đã đăng nhập. */
    public static function need(Req $req): array
    {
        $u = self::user($req);
        if ($u === null) {
            throw ApiError::unauthorized();
        }
        return $u;
    }

    /** Bắt buộc là admin (toàn quyền) hoặc editor (chỉ soạn nội dung). */
    public static function needStaff(Req $req, bool $adminOnly = false): array
    {
        $u = self::need($req);
        if ($u['role'] === 'admin') {
            return $u;
        }
        if (!$adminOnly && $u['role'] === 'editor') {
            return $u;
        }
        throw ApiError::forbidden('Mục này chỉ dành cho quản trị viên.');
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function verify(string $plain, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }
        return password_verify($plain, $hash);
    }

    /**
     * Chống dò mật khẩu: đếm số lần thất bại theo IP + email trong 15 phút.
     * Dùng file thay vì bảng CSDL để không phải thêm migration cho một việc
     * vốn chỉ cần tạm thời; thư mục storage/throttle tự dọn khi quá hạn.
     */
    public static function throttle(string $key, int $max = 8, int $windowSec = 900): void
    {
        $dir = Util::storagePath('throttle');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $now  = time();
        $hits = [];

        if (is_file($file)) {
            $hits = Util::jsonDecode(@file_get_contents($file) ?: '', []);
            $hits = array_values(array_filter(
                is_array($hits) ? $hits : [],
                static fn($t) => is_numeric($t) && ($now - (int) $t) < $windowSec
            ));
        }

        if (count($hits) >= $max) {
            throw ApiError::tooMany();
        }

        $hits[] = $now;
        @file_put_contents($file, Util::jsonEncode($hits), LOCK_EX);
    }

    public static function clearThrottle(string $key): void
    {
        $file = Util::storagePath('throttle') . '/' . hash('sha256', $key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Dạng user trả về cho frontend (không bao giờ có pass_hash). */
    public static function publicUser(array $u): array
    {
        return [
            'id'           => (int) $u['id'],
            'name'         => $u['name'],
            'email'        => $u['email'],
            'initials'     => Util::initials($u['name']),
            'provider'     => $u['provider'],
            'role'         => $u['role'],
            'levelHint'    => $u['level_hint'],
            'dailyGoalMin' => (int) $u['daily_goal_min'],
            'hasPassword'  => !empty($u['pass_hash']),
            'createdAt'    => $u['created_at'],
            'totalSeconds' => (int) $u['total_seconds'],
        ];
    }
}
