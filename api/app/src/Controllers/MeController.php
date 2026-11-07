<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Auth;
use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Csrf;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\RateLimit;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Settings;
use Zika\Core\Str;
use Zika\Core\Upload;
use Zika\Core\Validator;
use Zika\Services\AnnouncementService;
use Zika\Services\UserService;
use Zika\Services\VisitService;

/** Tài khoản của tôi (04 §3). */
final class MeController
{
    /** GET /me — gọi khi mở app. */
    public function show(Request $r): array
    {
        $user = $r->requireUser();
        return [
            'user' => UserService::toApi($user),
            'settings' => UserService::settings((int) $user['id']),
            'csrf' => Csrf::ensure($r),
            'support' => ['enabled' => (bool) Settings::value('support', 'enabled', false)],
            'announcements' => AnnouncementService::activeFor($user),
        ];
    }

    /** PATCH /me */
    public function update(Request $r): array
    {
        $v = new Validator($r->body);
        $set = [];
        if ($v->present('displayName')) {
            $set['display_name'] = $v->str('displayName', 'Tên hiển thị', min: 2, max: 60);
        }
        if ($v->present('selfLevel')) {
            $set['self_level'] = $v->in('selfLevel', 'Trình độ hiện tại', UserService::SELF_LEVELS);
        }
        if ($v->present('dailyGoalMin')) {
            $goal = $v->int('dailyGoalMin', 'Mục tiêu mỗi ngày');
            if (!in_array($goal, [10, 15, 30], true)) {
                $v->error('dailyGoalMin', 'Mục tiêu mỗi ngày chỉ được 10, 15 hoặc 30 phút.');
            }
            $set['daily_goal_min'] = $goal;
        }
        $v->done();
        Db::update('users', $set, ['id' => $r->userId()]);
        return ['user' => UserService::toApi(UserService::find($r->userId()))];
    }

    /** POST /me/avatar (multipart file) → WebP 192×192, xoá tệp ảnh cũ. */
    public function avatar(Request $r): array
    {
        // Mỗi lần đổi là một tệp mới trên ổ của hosting (dung lượng có hạn): chặn bấm đổi liên tục.
        RateLimit::enforce('avatar:' . $r->userId(), 10, 3600, 'Bạn đổi ảnh nhiều quá, thử lại sau ít phút nhé.');
        $cu = (string) Db::val('SELECT avatar_path FROM users WHERE id = ?', [$r->userId()]);
        $img = Upload::image($r->files['file'] ?? null, 'avatar');
        Db::update('users', ['avatar_path' => $img['path']], ['id' => $r->userId()]);
        if ($cu !== $img['path']) {
            self::xoaAnhDaiDien($cu);
        }
        return ['avatarUrl' => $img['url']];
    }

    /** DELETE /me/avatar — về lại chữ cái đầu tên. */
    public function removeAvatar(Request $r): array
    {
        $cu = (string) Db::val('SELECT avatar_path FROM users WHERE id = ?', [$r->userId()]);
        Db::update('users', ['avatar_path' => null], ['id' => $r->userId()]);
        self::xoaAnhDaiDien($cu);
        return ['avatarUrl' => null];
    }

    /**
     * Xoá tệp ảnh đại diện không còn ai dùng. Chỉ đụng tệp trong uploads/avatar/ (ảnh Google là URL
     * ngoài; ảnh cũ trước đây nằm chung uploads/img/ với ảnh bài học nên để nguyên). Tên tệp theo
     * sha1 nội dung nên hai người chọn cùng một ảnh sẽ dùng chung tệp — còn người dùng thì giữ.
     */
    public static function xoaAnhDaiDien(string $path): void
    {
        $goc = Config::uploadsUrl('avatar/');
        if (!str_starts_with($path, $goc) || str_contains($path, '..')) {
            return;
        }
        if ((int) Db::val('SELECT COUNT(*) FROM users WHERE avatar_path = ?', [$path]) > 0) {
            return;
        }
        $tep = Config::uploadsDir('avatar/' . substr($path, strlen($goc)));
        if (is_file($tep)) {
            @unlink($tep);
        }
    }

    /** PUT /me/password — bắt buộc mật khẩu hiện tại nếu đã có; thu hồi các phiên khác. */
    public function password(Request $r): array
    {
        $user = $r->requireUser();
        $new = (string) $r->input('newPassword', '');
        $fields = [];
        if (!empty($user['password_hash']) && !Auth::verifyPassword($user, (string) $r->input('currentPassword', ''))) {
            $fields['currentPassword'] = 'Mật khẩu hiện tại chưa đúng.';
        }
        if (($p = Auth::passwordProblem($new)) !== null) {
            $fields['newPassword'] = $p;
        }
        if ($fields !== []) {
            throw HttpError::validation($fields);
        }
        Db::update('users', ['password_hash' => Auth::hashPassword($new)], ['id' => (int) $user['id']]);
        Auth::revokeAll((int) $user['id'], (int) $r->session['id']);
        return ['user' => UserService::toApi(UserService::find((int) $user['id']))];
    }

    /** GET /me/sessions — thiết bị đang đăng nhập. */
    public function sessions(Request $r): array
    {
        $rows = Db::all(
            'SELECT id, device_type, os, browser, last_used_at FROM auth_sessions
             WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW() ORDER BY last_used_at DESC',
            [$r->userId()],
        );
        return array_map(static fn (array $s) => [
            'id' => (int) $s['id'],
            'deviceType' => (string) $s['device_type'],
            'os' => (string) $s['os'],
            'browser' => (string) $s['browser'],
            'lastUsedAt' => Clock::iso((string) $s['last_used_at']),
            'current' => (int) $s['id'] === (int) $r->session['id'],
        ], $rows);
    }

    /** DELETE /me/sessions/{id} */
    public function revokeSession(Request $r): Response
    {
        $n = Db::exec(
            'UPDATE auth_sessions SET revoked_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [$r->id(), $r->userId()],
        );
        if ($n === 0) {
            throw HttpError::notFound();
        }
        Db::exec('UPDATE visits SET ended_at = last_seen_at WHERE auth_session_id = ? AND ended_at IS NULL', [$r->id()]);
        return Response::noContent();
    }

    /** DELETE /me — gõ “XOÁ”/“XÓA” để xác nhận; có mật khẩu thì bắt buộc nhập đúng. */
    public function destroy(Request $r): Response
    {
        $user = $r->requireUser();
        $confirm = mb_strtoupper((string) preg_replace('/\s+/u', '', Str::nfc((string) $r->input('confirm', ''))));
        // Màn Tài khoản bảo gõ "XOA" (bàn phím không dấu vẫn gõ được) — nhận cả có dấu lẫn không.
        if (!in_array($confirm, ['XOA', Str::nfc('XOÁ'), Str::nfc('XÓA')], true)) {
            throw HttpError::validation(['confirm' => 'Hãy gõ XOÁ để xác nhận.']);
        }
        if (!empty($user['password_hash']) && !Auth::verifyPassword($user, (string) $r->input('password', ''))) {
            throw HttpError::validation(['password' => 'Mật khẩu chưa đúng.']);
        }
        if ($user['role'] === 'admin' && UserService::activeAdminCount([(int) $user['id']]) < 1) {
            throw HttpError::invalid('Bạn là quản trị viên duy nhất. Hãy mời người khác làm quản trị trước khi xoá tài khoản.');
        }
        // Khoá ngoại ON DELETE CASCADE xoá sạch tiến độ, phiên, cài đặt; góp ý được giữ lại ẩn danh.
        Db::exec('DELETE FROM users WHERE id = ?', [(int) $user['id']]);
        self::xoaAnhDaiDien((string) ($user['avatar_path'] ?? ''));
        Auth::logout($r);
        return Response::noContent();
    }

    /** GET /me/settings */
    public function settings(Request $r): array
    {
        return UserService::settings($r->userId());
    }

    /** PUT /me/settings */
    public function saveSettings(Request $r): array
    {
        return UserService::saveSettings($r->userId(), $r->body);
    }

    /** POST /visit/ping { activity } */
    public function ping(Request $r): array
    {
        $activity = $r->input('activity');
        VisitService::ping($r, is_array($activity) ? $activity : null);
        return [
            'serverTime' => Clock::now()->format('Y-m-d\TH:i:s.vP'),
            'announcements' => AnnouncementService::activeFor($r->requireUser()),
            // Số trên chuông cập nhật theo nhịp ping, admin vừa đăng là học viên thấy trong ~1 phút.
            'unreadNotifications' => AnnouncementService::inbox($r->requireUser(), 0)['unread'],
        ];
    }

    /** GET /me/notifications — hộp thư chuông thông báo. */
    public function notifications(Request $r): array
    {
        return AnnouncementService::inbox($r->requireUser());
    }

    /** POST /me/notifications/read-all */
    public function readAllNotifications(Request $r): array
    {
        AnnouncementService::readAll($r->requireUser());
        return AnnouncementService::inbox($r->requireUser());
    }

    /** POST /visit/leave (sendBeacon, form csrf=…) */
    public function leave(Request $r): Response
    {
        VisitService::leave($r);
        return Response::noContent();
    }

    /** POST /announcements/{id}/{seen|dismiss|click} */
    public function announcement(Request $r): Response
    {
        $id = $r->id();
        if (Db::val('SELECT id FROM announcements WHERE id = ?', [$id]) === null) {
            throw HttpError::notFound();
        }
        AnnouncementService::mark($id, $r->userId(), $r->param('action'));
        return Response::noContent();
    }
}
