<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Auth;
use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Mailer;
use Zika\Core\RateLimit;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Settings;
use Zika\Core\Str;
use Zika\Core\Validator;
use Zika\Services\EmailTokenService;
use Zika\Services\GoogleAuth;
use Zika\Services\UserService;

/** Đăng ký, đăng nhập (email + Google), quên/đặt lại mật khẩu, xác nhận email, lời mời quản trị. */
final class AuthController
{
    private const LOGIN_MAX = 5;
    private const LOGIN_WINDOW = 900;

    /** POST /auth/register (04 §2.1) */
    public function register(Request $r): Response
    {
        $auth = Settings::get('auth');
        if (!$auth['email']) {
            throw new HttpError(403, 'METHOD_DISABLED');
        }
        if (!$auth['allow_signup']) {
            throw new HttpError(403, 'SIGNUP_DISABLED');
        }

        $v = new Validator($r->body);
        $name = $v->str('displayName', 'Tên hiển thị', min: 2, max: 60);
        $email = $v->email('email');
        $password = (string) $r->input('password', '');
        if (($p = Auth::passwordProblem($password)) !== null) {
            $v->error('password', $p);
        }
        $selfLevel = $v->in('selfLevel', 'Trình độ', UserService::SELF_LEVELS, 'beginner');
        if ($r->input('acceptTerms') !== true) {
            $v->error('acceptTerms', 'Bạn cần đồng ý với Điều khoản và Chính sách riêng tư.');
        }
        $v->done();

        RateLimit::enforce('register:' . $r->ip, 5, 3600);

        if (UserService::findByEmail($email) !== null) {
            throw HttpError::validation(['email' => 'Email này không dùng được. Hãy đăng nhập hoặc dùng email khác.']);
        }

        $verify = (bool) $auth['require_email_verify'];
        $user = UserService::create([
            'email' => $email,
            'password_hash' => Auth::hashPassword($password),
            'full_name' => $name,
            'display_name' => $name,
            'signup_method' => 'email',
            'self_level_initial' => $selfLevel,
            'self_level' => $selfLevel,
            'terms_accepted_at' => Clock::sql(),
            'email_verified_at' => $verify ? null : Clock::sql(),
        ]);

        if ($verify) {
            self::sendVerification($user);
            return Response::json(['verifyRequired' => true, 'emailMasked' => Str::maskEmail($email)], 201);
        }
        $csrf = Auth::login($r, $user, true);
        return Response::json(['user' => UserService::toApi($user), 'csrf' => $csrf], 201);
    }

    /** POST /auth/login */
    public function login(Request $r): array
    {
        if (!Settings::value('auth', 'email', true)) {
            throw new HttpError(403, 'METHOD_DISABLED');
        }
        $v = new Validator($r->body);
        $email = $v->email('email');
        $password = (string) $r->input('password', '');
        if ($password === '') {
            $v->error('password', 'Hãy nhập Mật khẩu.');
        }
        $v->done();

        $key = 'login:' . $r->ip . ':' . $email;
        if (RateLimit::exceeded($key, self::LOGIN_MAX, self::LOGIN_WINDOW)) {
            throw new HttpError(429, 'RATE_LIMITED', 'Bạn thử quá nhiều lần. Đợi 15 phút rồi thử lại.');
        }
        $user = UserService::findByEmail($email);
        if ($user === null || !Auth::verifyPassword($user, $password)) {
            RateLimit::hit($key, self::LOGIN_MAX, self::LOGIN_WINDOW);
            throw new HttpError(401, 'BAD_CREDENTIALS');
        }
        if ($user['status'] === 'locked') {
            throw new HttpError(403, 'ACCOUNT_LOCKED');
        }
        if ($user['email_verified_at'] === null && Settings::value('auth', 'require_email_verify', true)) {
            throw new HttpError(403, 'EMAIL_NOT_VERIFIED');
        }
        RateLimit::clear($key);
        $csrf = Auth::login($r, $user, $r->input('remember', true) !== false);
        return ['user' => UserService::toApi(UserService::find((int) $user['id'])), 'csrf' => $csrf];
    }

    /** POST /auth/logout */
    public function logout(Request $r): Response
    {
        if ($r->session !== null) {
            Db::exec('UPDATE visits SET ended_at = last_seen_at WHERE auth_session_id = ? AND ended_at IS NULL', [$r->session['id']]);
        }
        Auth::logout($r);
        return Response::noContent();
    }

    /** POST /auth/forgot — luôn trả “đã gửi”, không lộ email có tồn tại hay không. */
    public function forgot(Request $r): array
    {
        $v = new Validator($r->body);
        $email = $v->email('email');
        $v->done();
        RateLimit::enforce('forgot-ip:' . $r->ip, 3, 3600);
        RateLimit::enforce('forgot:' . $email, 3, 3600);

        $user = UserService::findByEmail($email);
        if ($user !== null && $user['status'] === 'active') {
            $token = EmailTokenService::create((int) $user['id'], 'reset');
            $mail = Mailer::compose('Đặt lại mật khẩu', [
                'Chào ' . $user['display_name'] . ',',
                'Bạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu Zìkǎ. Link dưới đây có hiệu lực trong 30 phút và chỉ dùng được 1 lần.',
                'Nếu bạn không yêu cầu, hãy bỏ qua email này.',
            ], 'Đặt mật khẩu mới', Config::appUrl() . '/reset?token=' . $token);
            Mailer::queue($email, 'Đặt lại mật khẩu Zìkǎ', $mail['html'], $mail['text'], 'reset', true);
        }
        return ['sent' => true, 'emailMasked' => Str::maskEmail($email)];
    }

    /** POST /auth/reset */
    public function reset(Request $r): array
    {
        $password = (string) $r->input('password', '');
        if (($p = Auth::passwordProblem($password)) !== null) {
            throw HttpError::validation(['password' => $p]);
        }
        $userId = EmailTokenService::consume((string) $r->input('token', ''), 'reset');
        if ($userId === null) {
            throw new HttpError(410, 'TOKEN_INVALID');
        }
        Db::exec(
            'UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?',
            [Auth::hashPassword($password), $userId],
        );
        Auth::revokeAll($userId);
        return ['ok' => true];
    }

    /** POST /auth/verify-email — xác nhận xong đăng nhập luôn. */
    public function verifyEmail(Request $r): array
    {
        $userId = EmailTokenService::consume((string) $r->input('token', ''), 'verify');
        if ($userId === null) {
            throw new HttpError(410, 'TOKEN_INVALID');
        }
        $before = UserService::find($userId);
        if ($before === null) {
            throw new HttpError(410, 'TOKEN_INVALID');
        }
        if ($before['status'] === 'locked') {
            throw new HttpError(403, 'ACCOUNT_LOCKED');
        }
        if ($before['email_verified_at'] === null) {
            Db::exec('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [$userId]);
            UserService::sendWelcome($before);
        }
        $user = UserService::find($userId);
        $csrf = Auth::login($r, $user, true);
        return ['user' => UserService::toApi($user), 'csrf' => $csrf];
    }

    /** POST /auth/resend-verification */
    public function resendVerification(Request $r): array
    {
        $v = new Validator($r->body);
        $email = $v->email('email');
        $v->done();
        RateLimit::enforce('resend:' . $email, 3, 3600);
        RateLimit::enforce('resend-ip:' . $r->ip, 10, 3600);
        $user = UserService::findByEmail($email);
        if ($user !== null && $user['email_verified_at'] === null && $user['status'] === 'active') {
            self::sendVerification($user);
        }
        return ['sent' => true, 'emailMasked' => Str::maskEmail($email)];
    }

    /** GET /auth/google/start?remember=1&next=/learn/flashcards&selfLevel= */
    public function googleStart(Request $r): Response
    {
        if (!Settings::value('auth', 'google', true) || (string) Config::get('google.client_id', '') === '') {
            return Response::redirect('/login?error=method_disabled');
        }
        $selfLevel = (string) $r->q('selfLevel', '');
        $url = GoogleAuth::startUrl([
            'next' => self::safeNext((string) $r->q('next', '/learn/flashcards')),
            'remember' => (string) $r->q('remember', '1') !== '0',
            'selfLevel' => in_array($selfLevel, UserService::SELF_LEVELS, true) ? $selfLevel : null,
        ]);
        return Response::redirect($url);
    }

    /** GET /auth/google/callback?code&state */
    public function googleCallback(Request $r): Response
    {
        if (!Settings::value('auth', 'google', true)) {
            return Response::redirect('/login?error=method_disabled');
        }
        $ctx = GoogleAuth::readState($r, (string) $r->q('state', ''));
        $code = (string) $r->q('code', '');
        if ($ctx === null || $code === '' || $r->q('error') !== null) {
            return Response::redirect('/login?error=google');
        }
        $g = GoogleAuth::safe(static fn () => GoogleAuth::exchange($code, (string) $ctx['nonce']));
        if (!is_array($g) || $g['email'] === '') {
            return Response::redirect('/login?error=google');
        }

        $user = Db::one('SELECT * FROM users WHERE google_sub = ?', [$g['sub']]);
        if ($user === null) {
            $user = UserService::findByEmail($g['email']);
            if ($user !== null) {
                // Liên kết vào tài khoản email sẵn có cùng địa chỉ.
                Db::exec(
                    'UPDATE users SET google_sub = ?, email_verified_at = COALESCE(email_verified_at, NOW()),
                       avatar_path = COALESCE(avatar_path, ?), full_name = IF(full_name = \'\', ?, full_name) WHERE id = ?',
                    [$g['sub'], $g['picture'], $g['name'], $user['id']],
                );
                $user = UserService::find((int) $user['id']);
            }
        }
        if ($user === null) {
            if (!Settings::value('auth', 'allow_signup', true)) {
                return Response::redirect('/login?error=signup_disabled');
            }
            $level = $ctx['selfLevel'] ?? null;
            $user = UserService::create([
                'email' => $g['email'],
                'google_sub' => $g['sub'],
                'full_name' => mb_substr($g['name'] !== '' ? $g['name'] : Str::displayFromFull(''), 0, 120),
                'display_name' => Str::displayFromFull($g['name']),
                'avatar_path' => $g['picture'],
                'signup_method' => 'google',
                'self_level_initial' => $level ?? 'beginner',
                'self_level' => $level ?? 'beginner',
                'email_verified_at' => Clock::sql(),
                'terms_accepted_at' => Clock::sql(),
            ]);
        }
        if ($user['status'] === 'locked') {
            return Response::redirect('/login?error=locked');
        }
        Auth::login($r, $user, (bool) ($ctx['remember'] ?? true));
        return Response::redirect(self::safeNext((string) ($ctx['next'] ?? '/learn/flashcards')));
    }

    /** GET /auth/invite/{token} */
    public function inviteInfo(Request $r): array
    {
        $inv = self::findInvite($r->param('token'));
        return $inv === null
            ? ['valid' => false, 'email' => null, 'role' => null]
            : ['valid' => true, 'email' => (string) $inv['email'], 'role' => (string) $inv['role']];
    }

    /** POST /auth/invite/accept — email đăng nhập phải trùng email được mời. */
    public function inviteAccept(Request $r): array
    {
        $user = $r->requireUser();
        $inv = self::findInvite((string) $r->input('token', ''));
        if ($inv === null) {
            throw new HttpError(410, 'TOKEN_INVALID');
        }
        if (Str::lower((string) $inv['email']) !== Str::lower((string) $user['email'])) {
            throw HttpError::forbidden('Lời mời gửi tới ' . $inv['email'] . '. Hãy đăng nhập bằng email đó.');
        }
        // Không hạ quyền: admin nhận lời mời biên tập vẫn là admin.
        $role = $user['role'] === 'admin' ? 'admin' : (string) $inv['role'];
        Db::tx(static function () use ($inv, $user, $role): void {
            Db::exec('UPDATE users SET role = ? WHERE id = ?', [$role, $user['id']]);
            Db::exec('UPDATE admin_invites SET accepted_at = NOW(), accepted_user_id = ? WHERE id = ?', [$user['id'], $inv['id']]);
        });
        return ['user' => UserService::toApi(UserService::find((int) $user['id']))];
    }

    /** @return array<string,mixed>|null */
    private static function findInvite(string $token): ?array
    {
        if ($token === '' || strlen($token) > 100) {
            return null;
        }
        return Db::one(
            'SELECT * FROM admin_invites WHERE token_hash = ? AND accepted_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token)],
        );
    }

    /** @param array<string,mixed> $user */
    private static function sendVerification(array $user): void
    {
        $token = EmailTokenService::create((int) $user['id'], 'verify');
        $mail = Mailer::compose('Xác nhận email của bạn', [
            'Chào ' . $user['display_name'] . ',',
            'Cảm ơn bạn đã tạo tài khoản Zìkǎ. Bấm nút dưới đây để xác nhận email. Link có hiệu lực trong 24 giờ.',
        ], 'Xác nhận email', Config::appUrl() . '/verify-email?token=' . $token);
        Mailer::queue((string) $user['email'], 'Xác nhận email Zìkǎ', $mail['html'], $mail['text'], 'verify', true);
    }

    /** Chỉ chấp nhận đường dẫn nội bộ bắt đầu bằng “/” (chống chuyển hướng mở). */
    public static function safeNext(string $next): string
    {
        return preg_match('#^/(?![/\\\\])[^\s]*$#', $next) ? $next : '/learn/flashcards';
    }
}
