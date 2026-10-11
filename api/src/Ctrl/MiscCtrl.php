<?php
declare(strict_types=1);

namespace Zika\Ctrl;

use Zika\Auth;
use Zika\Config;
use Zika\Db;
use Zika\Http\ApiError;
use Zika\Http\Req;
use Zika\Http\Res;
use Zika\Settings;
use Zika\Util;

/** Cấu hình web công khai, thông báo, góp ý, link ủng hộ. */
final class MiscCtrl
{
    /**
     * GET /api/config
     *
     * Giao diện gọi đúng một lần khi mở web: biết tên web, bật/tắt đăng nhập
     * Google, có hiện nút Góp ý hay không... Chỉ trả những khoá không nhạy cảm.
     */
    public static function config(Req $req): never
    {
        $s = Settings::all();

        Res::ok([
            'siteName'     => $s['site_name'] ?? 'Zìkǎ',
            'tagline'      => $s['tagline'] ?? 'Chọn bộ thẻ, mỗi ngày lật một ít. Miễn phí.',
            'contactEmail' => $s['contact_email'] ?? '',
            'loginGoogle'  => Settings::flag('login_google', true) && Config::get('google.client_id', '') !== '',
            'loginEmail'   => Settings::flag('login_email', true),
            'allowSignup'  => Settings::flag('allow_signup', true),
            'verifyEmail'  => Settings::flag('verify_email', false),
            'showFeedback' => Settings::flag('show_feedback', true),
            'showSupport'  => Settings::flag('show_support', false),
            'defaultVoice' => $s['default_voice'] ?? 'xiaoxiao',
            'googleClientId' => (string) Config::get('google.client_id', ''),
            'levels' => array_map(static fn($l) => [
                'hsk'     => (int) $l['hsk'],
                'visible' => (bool) $l['visible'],
                'sample'  => $l['sample'],
            ], Db::all('SELECT hsk, visible, sample FROM levels ORDER BY hsk')),
        ]);
    }

    /** GET /api/news — thông báo đang hiện cho người học hiện tại. */
    public static function news(Req $req): never
    {
        $user = Auth::user($req);

        $rows = Db::all(
            "SELECT * FROM news
              WHERE status = 'published'
                AND (start_at IS NULL OR start_at <= NOW())
                AND (end_at   IS NULL OR end_at   >= NOW())
              ORDER BY COALESCE(start_at, created_at) DESC
              LIMIT 5"
        );

        // Lọc theo đối tượng nhận: cần biết cấp đang học và ngày tạo tài khoản.
        $hsk    = null;
        $isNew7 = false;
        if ($user !== null) {
            $stats  = AuthCtrl::statsOf((int) $user['id']);
            $hsk    = $stats['currentHsk'];
            $isNew7 = strtotime((string) $user['created_at']) > time() - 7 * 86400;
        }

        $out = [];
        foreach ($rows as $r) {
            $ok = match ($r['audience']) {
                'all'  => true,
                'hsk1' => $hsk === 1,
                'hsk2' => $hsk === 2,
                'new7' => $isNew7,
                default => true,
            };
            if (!$ok) {
                continue;
            }
            $out[] = [
                'id'       => (int) $r['id'],
                'title'    => $r['title'],
                'body'     => $r['body'],
                'display'  => $r['display'],
                'ctaLabel' => $r['cta_label'],
                'ctaUrl'   => $r['cta_url'],
            ];
        }

        Res::ok($out);
    }

    /**
     * POST /api/feedback
     * body: { kind, body, part, lessonId, question }
     *
     * Nút "Góp ý" có ở mọi màn học và gửi kèm vị trí đang đứng, nhờ vậy admin
     * mở đúng câu để sửa thay vì phải đi tìm.
     */
    public static function sendFeedback(Req $req): never
    {
        $u = Auth::need($req);
        if (!Settings::flag('show_feedback', true)) {
            throw ApiError::forbidden('Mục góp ý đang tắt.');
        }

        $body = $req->need('body');
        if (mb_strlen($body) > 4000) {
            throw ApiError::badRequest('Góp ý dài quá, bạn viết gọn lại giúp mình.');
        }
        Auth::throttle('fb:' . $u['id'], 10, 3600);

        $kinds = ['noi_dung', 'tinh_nang', 'phat_am', 'hien_thi', 'khac'];
        $kind  = $req->str('kind', 'khac');
        [$device] = Util::device($req->ua());

        $part = $req->str('part');
        $id   = Db::insert('feedback', [
            'user_id'       => $u['id'],
            'kind'          => in_array($kind, $kinds, true) ? $kind : 'khac',
            'body'          => $body,
            'ctx_part'      => in_array($part, ContentCtrl::PARTS, true) ? $part : '',
            'ctx_lesson_id' => $req->int('lessonId') ?: null,
            'ctx_question'  => $req->int('question') ?: null,
            'device'        => $device,
        ]);

        Res::created(['id' => $id]);
    }

    /** GET /api/support-links?at=home — link ủng hộ / Shopee đang bật. */
    public static function supportLinks(Req $req): never
    {
        if (!Settings::flag('show_support', false)) {
            Res::ok(['links' => [], 'donate' => null]);
        }

        $at   = $req->q('at', 'home');
        $rows = Db::all(
            "SELECT * FROM support_links
              WHERE active = 1
                AND (start_at IS NULL OR start_at <= CURDATE())
                AND (end_at   IS NULL OR end_at   >= CURDATE())
              ORDER BY sort, id"
        );

        $links = [];
        foreach ($rows as $r) {
            $places = array_filter(explode(',', (string) $r['placements']));
            if ($places !== [] && !in_array((string) $at, $places, true)) {
                continue;
            }
            $links[] = [
                'id'    => (int) $r['id'],
                'name'  => $r['name'],
                'image' => $r['image'],
                // Không trả url thật: người học bấm qua /api/support-links/{id}/go
                // để đếm lượt bấm, đồng thời tránh dò toàn bộ link tiếp thị.
                'go'    => '/api/support-links/' . (int) $r['id'] . '/go',
            ];
        }

        if ($links !== []) {
            $ids = implode(',', array_map(static fn($l) => (int) $l['id'], $links));
            Db::run("UPDATE support_links SET views = views + 1 WHERE id IN ($ids)");
        }

        $donate = Settings::json('donate', null);
        Res::ok([
            'links'  => $links,
            'donate' => (is_array($donate) && ($donate['enabled'] ?? false)) ? $donate : null,
        ]);
    }

    /** GET /api/support-links/{id}/go — đếm lượt bấm rồi chuyển hướng. */
    public static function supportGo(Req $req): never
    {
        $row = Db::one('SELECT * FROM support_links WHERE id = ? AND active = 1', [$req->paramInt('id')]);
        if ($row === null) {
            throw ApiError::notFound('Link không còn hiệu lực.');
        }

        Db::run('UPDATE support_links SET clicks = clicks + 1 WHERE id = ?', [$row['id']]);

        header('Location: ' . $row['url'], true, 302);
        // rel=noopener cho trường hợp client tự mở thẻ mới thay vì theo redirect.
        header('Referrer-Policy: no-referrer');
        exit;
    }
}
