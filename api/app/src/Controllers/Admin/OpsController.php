<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Mailer;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Settings;
use Zika\Core\Str;
use Zika\Core\Validator;
use Zika\Services\AnnouncementService;

/** Quản trị · Thông báo, Góp ý, Ủng hộ & Shopee, Cài đặt (04 §10.8–§10.11). */
final class OpsController
{
    /* ------------------------------------------------------------------ */
    /* Thông báo (A)                                                       */
    /* ------------------------------------------------------------------ */

    /** GET /admin/announcements */
    public function announcements(Request $r): array
    {
        return array_map(static fn (array $a): array => [
            'id' => (int) $a['id'],
            'title' => (string) $a['title'],
            'body' => (string) $a['body'],
            'kind' => (string) $a['kind'],
            'audience' => (string) $a['audience'],
            'audienceLevel' => $a['audience_level'] === null ? null : (int) $a['audience_level'],
            'startsAt' => $a['starts_at'],
            'endsAt' => $a['ends_at'],
            'ctaText' => $a['cta_text'],
            'ctaRoute' => $a['cta_route'],
            'status' => (string) $a['status'],
            'isAutoWelcome' => (bool) $a['is_auto_welcome'],
            'views' => (int) $a['views'],
            'clicks' => (int) $a['clicks'],
            'emailsSent' => (int) $a['emails_sent'],
        ], Db::all("SELECT * FROM announcements WHERE audience <> 'user' ORDER BY created_at DESC"));
    }

    /** POST /admin/announcements · PUT /admin/announcements/{id} */
    public function saveAnnouncement(Request $r): Response
    {
        $id = $r->id();
        $cur = $id > 0 ? Db::one('SELECT * FROM announcements WHERE id = ?', [$id]) : null;
        if ($id > 0 && $cur === null) {
            throw HttpError::notFound('Không có thông báo này.');
        }

        $v = new Validator($r->body);
        $title = $v->str('title', 'Tiêu đề', min: 1, max: 160);
        $body = $v->str('body', 'Nội dung', min: 1, max: 8000);
        $kind = (string) $v->in('kind', 'Kiểu hiện', ['banner', 'modal', 'email'], 'banner');
        $audience = (string) $v->in('audience', 'Ai thấy', ['all', 'level', 'new7d'], 'all');
        $level = $v->int('audienceLevel', 'Cấp', 1, 6, false);
        $startsAt = $v->datetime('startsAt', 'Hiện từ');
        $endsAt = $v->datetime('endsAt', 'Đến');
        $cta = $v->strOrNull('ctaText', 'Nút trong thông báo', 40);
        $route = $v->strOrNull('ctaRoute', 'Đích của nút', 160);
        $action = (string) $v->in('action', 'Hành động', ['draft', 'schedule', 'publish'], 'draft');

        if ($audience === 'level' && $level === null) {
            $v->error('audienceLevel', 'Chọn cấp HSK.');
        }
        if ($action === 'schedule' && ($startsAt === null || strtotime($startsAt) <= time())) {
            $v->error('startsAt', 'Lên lịch thì “Hiện từ” phải ở tương lai.');
        }
        $v->done();

        // Thông báo chào mừng tự động chỉ được sửa chữ, không đổi kiểu/đối tượng/trạng thái.
        if ($cur !== null && (int) $cur['is_auto_welcome'] === 1) {
            Db::update('announcements', ['title' => $title, 'body' => $body], ['id' => $id]);
            Audit::log($r, 'announcement.update', 'announcement', $id);
            return Response::json(['id' => $id]);
        }

        $status = match ($action) {
            'publish' => 'published',
            'schedule' => 'scheduled',
            default => 'draft',
        };

        $row = [
            'title' => $title,
            'body' => $body,
            'kind' => $kind,
            'audience' => $audience,
            'audience_level' => $audience === 'level' ? $level : null,
            'starts_at' => $action === 'publish' ? ($startsAt ?? date('Y-m-d H:i:s')) : $startsAt,
            'ends_at' => $endsAt,
            'cta_text' => $cta,
            'cta_route' => $route,
            'status' => $status,
        ];

        if ($cur === null) {
            $row['created_by'] = $r->userId();
            $id = Db::insert('announcements', $row);
        } else {
            Db::update('announcements', $row, ['id' => $id]);
        }

        // Kiểu email thì đưa vào hàng đợi ngay khi đăng; cron gửi dần.
        $queued = 0;
        if ($status === 'published' && $kind === 'email') {
            $queued = AnnouncementService::queueEmails(Db::one('SELECT * FROM announcements WHERE id = ?', [$id]) ?? []);
        }

        Audit::log($r, $cur === null ? 'announcement.create' : 'announcement.update', 'announcement', $id, ['status' => $status]);
        return Response::json(['id' => $id, 'queued' => $queued], $cur === null ? 201 : 200);
    }

    /** DELETE /admin/announcements/{id} */
    public function deleteAnnouncement(Request $r): Response
    {
        $a = Db::one('SELECT * FROM announcements WHERE id = ?', [$r->id()]);
        if ($a === null) {
            throw HttpError::notFound('Không có thông báo này.');
        }
        if ((int) $a['is_auto_welcome'] === 1) {
            throw HttpError::invalid('Thông báo chào mừng tự động không xoá được.');
        }
        Db::exec('DELETE FROM announcements WHERE id = ?', [(int) $a['id']]);
        Audit::log($r, 'announcement.delete', 'announcement', (int) $a['id']);
        return Response::noContent();
    }

    /** GET /admin/announcements/targets — các đích cho nút trong thông báo. */
    public function targets(Request $r): array
    {
        $parts = [
            'lt' => ['Lật thẻ', 'flashcards'], 'bt' => ['Bài tập', 'exercises'], 'kt' => ['Kiểm tra', 'tests'],
            'lv' => ['Luyện viết', 'writing'], 'ht' => ['Hội thoại', 'dialogues'], 'np' => ['Ngữ pháp', 'grammar'],
        ];
        $out = [];
        foreach (Db::col('SELECT id FROM levels WHERE is_visible = 1 ORDER BY id') as $lv) {
            foreach ($parts as [$name, $slug]) {
                $out[] = ['label' => "$name · HSK $lv", 'route' => "/learn/$slug/hsk/$lv"];
            }
        }
        $out[] = ['label' => 'Tài khoản', 'route' => '/account'];
        $out[] = ['label' => 'Cài đặt học', 'route' => '/settings'];
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Góp ý (E/A)                                                         */
    /* ------------------------------------------------------------------ */

    private const ERR_KINDS = ['content_error', 'pronunciation', 'display'];
    private const IDEA_KINDS = ['feature', 'content_suggestion', 'other'];
    /** Biên tập chỉ thấy góp ý về nội dung. */
    private const EDITOR_KINDS = ['content_error', 'pronunciation', 'content_suggestion'];

    /** GET /admin/feedback?tab=&page= */
    public function feedback(Request $r): Response
    {
        $where = ['1 = 1'];
        $params = [];

        if (!$this->isAdmin($r)) {
            $where[] = 'f.category IN (' . Db::in(self::EDITOR_KINDS) . ')';
            array_push($params, ...self::EDITOR_KINDS);
        }

        switch ((string) $r->q('tab', 'unread')) {
            case 'errors':
                $where[] = 'f.category IN (' . Db::in(self::ERR_KINDS) . ") AND f.status <> 'done'";
                array_push($params, ...self::ERR_KINDS);
                break;
            case 'ideas':
                $where[] = 'f.category IN (' . Db::in(self::IDEA_KINDS) . ") AND f.status <> 'done'";
                array_push($params, ...self::IDEA_KINDS);
                break;
            case 'done':
                $where[] = "f.status = 'done'";
                break;
            default:
                $where[] = 'f.is_read = 0';
        }

        $sql = implode(' AND ', $where);
        $per = $r->qInt('per', 30, 1, 100);
        $page = $r->qInt('page', 1, 1);

        $rows = Db::all(
            "SELECT f.*, u.display_name, u.email FROM feedback f LEFT JOIN users u ON u.id = f.user_id
              WHERE $sql ORDER BY f.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per),
            $params,
        );

        return Response::json(
            ['items' => array_map(static fn (array $f): array => self::feedbackRow($f), $rows)],
            200,
            ['counts' => $this->feedbackCounts()],
        );
    }

    /** GET /admin/feedback/{id} — mở là đánh dấu đã đọc. */
    public function feedbackDetail(Request $r): array
    {
        $f = Db::one(
            'SELECT f.*, u.display_name, u.email FROM feedback f LEFT JOIN users u ON u.id = f.user_id WHERE f.id = ?',
            [$r->id()],
        );
        if ($f === null) {
            throw HttpError::notFound('Không có góp ý này.');
        }
        if (!$this->isAdmin($r) && !in_array((string) $f['category'], self::EDITOR_KINDS, true)) {
            throw HttpError::forbidden();
        }

        Db::exec('UPDATE feedback SET is_read = 1 WHERE id = ? AND is_read = 0', [(int) $f['id']]);

        $ctx = is_string($f['ctx']) ? (json_decode($f['ctx'], true) ?: []) : [];
        return self::feedbackRow($f) + [
            'message' => (string) $f['message'],
            'screenshotUrl' => $f['screenshot_path'],
            'internalNote' => $f['internal_note'],
            'reply' => $f['reply'],
            'repliedAt' => $f['replied_at'],
            'ctx' => $ctx,
            'editLink' => self::editLink($ctx),
        ];
    }

    /** PATCH /admin/feedback/{id} */
    public function updateFeedback(Request $r): array
    {
        $id = $r->id();
        if (Db::val('SELECT id FROM feedback WHERE id = ?', [$id]) === null) {
            throw HttpError::notFound('Không có góp ý này.');
        }

        $v = new Validator($r->body);
        $set = [];
        if ($v->present('status')) {
            $set['status'] = $v->in('status', 'Trạng thái', ['new', 'doing', 'done']);
        }
        if ($v->present('assigneeId')) {
            $set['assignee_id'] = $v->int('assigneeId', 'Người xử lý', 1, null, false);
        }
        if ($v->present('internalNote')) {
            $set['internal_note'] = $v->strOrNull('internalNote', 'Ghi chú nội bộ', 4000);
        }
        $v->done();

        if ($set !== []) {
            Db::update('feedback', $set, ['id' => $id]);
            Audit::log($r, 'feedback.update', 'feedback', $id, $set);
        }
        return ['ok' => true];
    }

    /** POST /admin/feedback/{id}/reply */
    public function replyFeedback(Request $r): array
    {
        $f = Db::one('SELECT f.*, u.email, u.display_name FROM feedback f LEFT JOIN users u ON u.id = f.user_id WHERE f.id = ?', [$r->id()]);
        if ($f === null) {
            throw HttpError::notFound('Không có góp ý này.');
        }

        $v = new Validator($r->body);
        $reply = $v->str('reply', 'Nội dung trả lời', min: 1, max: 4000);
        $v->done();

        Db::update('feedback', [
            'reply' => $reply,
            'replied_at' => date('Y-m-d H:i:s'),
            'status' => 'done',
        ], ['id' => (int) $f['id']]);

        if (($f['email'] ?? '') !== '') {
            Mailer::queue(
                (string) $f['email'],
                'Phản hồi góp ý của bạn · ' . Settings::value('site', 'name', 'Zìkǎ'),
                nl2br(htmlspecialchars($reply, ENT_QUOTES, 'UTF-8')),
                $reply,
                'reply',
            );
        }

        Audit::log($r, 'feedback.reply', 'feedback', (int) $f['id']);
        return ['ok' => true];
    }

    /** @return array<string,int> */
    private function feedbackCounts(): array
    {
        return [
            'unread' => (int) Db::val('SELECT COUNT(*) FROM feedback WHERE is_read = 0'),
            'errors' => (int) Db::val(
                'SELECT COUNT(*) FROM feedback WHERE category IN (' . Db::in(self::ERR_KINDS) . ") AND status <> 'done'",
                self::ERR_KINDS,
            ),
            'ideas' => (int) Db::val(
                'SELECT COUNT(*) FROM feedback WHERE category IN (' . Db::in(self::IDEA_KINDS) . ") AND status <> 'done'",
                self::IDEA_KINDS,
            ),
            'done' => (int) Db::val("SELECT COUNT(*) FROM feedback WHERE status = 'done'"),
        ];
    }

    /** @param array<string,mixed> $f @return array<string,mixed> */
    private static function feedbackRow(array $f): array
    {
        return [
            'id' => (int) $f['id'],
            'category' => (string) $f['category'],
            'preview' => Str::limit((string) $f['message'], 90),
            'status' => (string) $f['status'],
            'isRead' => (bool) $f['is_read'],
            'createdAt' => $f['created_at'],
            'user' => $f['display_name'] === null ? null : [
                'id' => (int) $f['user_id'],
                'initials' => Str::initials((string) $f['display_name']),
                'name' => (string) $f['display_name'],
                'email' => (string) $f['email'],
            ],
        ];
    }

    /** Đường dẫn mở đúng chỗ cần sửa trong trình soạn. @param array<string,mixed> $ctx */
    private static function editLink(array $ctx): ?string
    {
        $lessonId = (int) ($ctx['lessonId'] ?? 0);
        if ($lessonId <= 0) {
            return null;
        }
        $slug = [
            'lt' => 'words', 'bt' => 'exercises', 'kt' => 'tests',
            'lv' => 'writing', 'ht' => 'dialogue', 'np' => 'grammar',
        ][(string) ($ctx['part'] ?? '')] ?? null;

        if ($slug === null) {
            return "/admin/lessons/$lessonId/exercises";
        }
        if ($slug === 'words') {
            return '/admin/words?level=' . (int) ($ctx['levelId'] ?? 1) . "&lesson=$lessonId";
        }
        return "/admin/lessons/$lessonId/$slug";
    }

    /* ------------------------------------------------------------------ */
    /* Ủng hộ & Shopee (A)                                                 */
    /* ------------------------------------------------------------------ */

    private const SHOPEE_HOSTS = ['shopee.vn', 's.shopee.vn', 'shope.ee'];

    /** GET /admin/support */
    public function support(Request $r): array
    {
        $links = Db::all('SELECT * FROM affiliate_links ORDER BY sort_order, id');
        $stat = Db::one('SELECT COALESCE(SUM(views),0) v, COALESCE(SUM(clicks),0) c FROM affiliate_daily WHERE d > (CURDATE() - INTERVAL 7 DAY)')
            ?? ['v' => 0, 'c' => 0];

        $per = [];
        foreach (Db::all(
            'SELECT link_id, COALESCE(SUM(views),0) v, COALESCE(SUM(clicks),0) c
               FROM affiliate_daily WHERE d > (CURDATE() - INTERVAL 7 DAY) GROUP BY link_id',
        ) as $x) {
            $per[(int) $x['link_id']] = $x;
        }

        return [
            'enabled' => (bool) Settings::value('support', 'enabled', false),
            'kpi' => [
                'views' => (int) $stat['v'],
                'clicks' => (int) $stat['c'],
                'ctr' => ((int) $stat['v']) > 0 ? round(((int) $stat['c']) / ((int) $stat['v']) * 100, 1) : 0.0,
                'active' => (int) Db::val('SELECT COUNT(*) FROM affiliate_links WHERE is_active = 1'),
                'total' => count($links),
            ],
            'links' => array_map(static function (array $l) use ($per): array {
                $s = $per[(int) $l['id']] ?? ['v' => 0, 'c' => 0];
                return [
                    'id' => (int) $l['id'],
                    'name' => (string) $l['name'],
                    'url' => (string) $l['url'],
                    'imageUrl' => $l['image_path'],
                    'placeDesk' => (bool) $l['place_desk'],
                    'placeDone' => (bool) $l['place_done'],
                    'startsOn' => $l['starts_on'],
                    'endsOn' => $l['ends_on'],
                    'active' => (bool) $l['is_active'],
                    'views' => (int) $s['v'],
                    'clicks' => (int) $s['c'],
                    'ctr' => ((int) $s['v']) > 0 ? round(((int) $s['c']) / ((int) $s['v']) * 100, 1) : 0.0,
                ];
            }, $links),
            'donate' => Settings::get('donate'),
        ];
    }

    /** PATCH /admin/support — công tắc tổng. */
    public function toggleSupport(Request $r): array
    {
        $v = new Validator($r->body);
        $on = $v->bool('enabled');
        $v->done();

        Settings::set('support', ['enabled' => $on]);
        Audit::log($r, 'support.toggle', 'settings', null, ['enabled' => $on]);
        return ['enabled' => $on];
    }

    /** POST /admin/support/links · PUT /admin/support/links/{id} */
    public function saveLink(Request $r): Response
    {
        $id = $r->id();
        $v = new Validator($r->body);
        $name = $v->str('name', 'Tên hiển thị', min: 1, max: 160);
        $url = $v->str('url', 'Link', min: 1, max: 500);
        $image = $v->strOrNull('imagePath', 'Ảnh', 255);
        $deskPlace = $v->bool('placeDesk');
        $donePlace = $v->bool('placeDone');
        $startsOn = $v->date('startsOn', 'Hiện từ ngày');
        $endsOn = $v->date('endsOn', 'Đến ngày');
        $active = $v->bool('active', true);

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!str_starts_with($url, 'https://') || !in_array($host, self::SHOPEE_HOSTS, true)) {
            $v->error('url', 'Link phải là https:// thuộc shopee.vn, s.shopee.vn hoặc shope.ee.');
        }
        $v->done();

        $row = [
            'name' => $name,
            'url' => $url,
            'image_path' => $image,
            'place_desk' => $deskPlace ? 1 : 0,
            'place_done' => $donePlace ? 1 : 0,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => $active ? 1 : 0,
        ];

        if ($id > 0) {
            if (Db::val('SELECT id FROM affiliate_links WHERE id = ?', [$id]) === null) {
                throw HttpError::notFound('Không có link này.');
            }
            Db::update('affiliate_links', $row, ['id' => $id]);
            Audit::log($r, 'support.link.update', 'affiliate_link', $id);
            return Response::json(['id' => $id]);
        }

        $row['sort_order'] = 1 + (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM affiliate_links');
        $newId = Db::insert('affiliate_links', $row);
        Audit::log($r, 'support.link.create', 'affiliate_link', $newId);
        return Response::json(['id' => $newId], 201);
    }

    /** PATCH /admin/support/links/{id} — bật/tắt nhanh. */
    public function toggleLink(Request $r): array
    {
        $v = new Validator($r->body);
        $on = $v->bool('active');
        $v->done();

        Db::update('affiliate_links', ['is_active' => $on ? 1 : 0], ['id' => $r->id()]);
        return ['active' => $on];
    }

    /** DELETE /admin/support/links/{id} */
    public function deleteLink(Request $r): Response
    {
        Db::exec('DELETE FROM affiliate_links WHERE id = ?', [$r->id()]);
        Audit::log($r, 'support.link.delete', 'affiliate_link', $r->id());
        return Response::noContent();
    }

    /** PUT /admin/support/links/order */
    public function reorderLinks(Request $r): Response
    {
        $v = new Validator($r->body);
        $ids = $v->ids('ids', 'Danh sách link');
        $v->done();

        foreach ($ids as $i => $id) {
            Db::exec('UPDATE affiliate_links SET sort_order = ? WHERE id = ?', [$i + 1, $id]);
        }
        return Response::noContent();
    }

    /** PUT /admin/support/donate */
    public function donate(Request $r): array
    {
        $v = new Validator($r->body);
        $data = [
            'enabled' => $v->bool('enabled'),
            'bank' => $v->str('bank', 'Ngân hàng', false, 0, 80),
            'owner' => $v->str('owner', 'Chủ tài khoản', false, 0, 120),
            'account' => $v->str('account', 'Số tài khoản', false, 0, 40),
            'memo' => $v->str('memo', 'Nội dung chuyển khoản', false, 0, 140),
            'qr_path' => $v->strOrNull('qrPath', 'Mã QR', 255),
        ];
        $v->done();

        Settings::set('donate', $data);
        Audit::log($r, 'support.donate', 'settings');
        return $data;
    }

    /* ------------------------------------------------------------------ */
    /* Cài đặt (A)                                                         */
    /* ------------------------------------------------------------------ */

    /** GET /admin/settings */
    public function settings(Request $r): array
    {
        return [
            'site' => Settings::get('site'),
            'auth' => Settings::get('auth'),
            'content' => Settings::get('content'),
            'privacy' => Settings::get('privacy'),
            'backup' => Settings::get('backup'),
            'levels' => array_map(static fn (array $l): array => [
                'id' => (int) $l['id'],
                'name' => (string) $l['name'],
                'visible' => (bool) $l['is_visible'],
            ], Db::all('SELECT id, name, is_visible FROM levels ORDER BY id')),
            'staff' => array_map(static fn (array $u): array => [
                'id' => (int) $u['id'],
                'name' => (string) $u['display_name'],
                'email' => (string) $u['email'],
                'role' => (string) $u['role'],
                'isMe' => (int) $u['id'] === $r->userId(),
            ], Db::all("SELECT id, display_name, email, role FROM users WHERE role IN ('admin','editor') ORDER BY role, display_name")),
            'invites' => array_map(static fn (array $i): array => [
                'id' => (int) $i['id'],
                'email' => (string) $i['email'],
                'role' => (string) $i['role'],
                'expiresAt' => $i['expires_at'],
            ], Db::all('SELECT * FROM admin_invites WHERE accepted_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC')),
        ];
    }

    /** PUT /admin/settings */
    public function saveSettings(Request $r): array
    {
        $b = $r->body;

        if (isset($b['site']) && is_array($b['site'])) {
            Settings::set('site', [
                'name' => Str::limit(trim((string) ($b['site']['name'] ?? 'Zìkǎ')), 60),
                'slogan' => Str::limit(trim((string) ($b['site']['slogan'] ?? '')), 160),
                'contact_email' => Str::limit(trim((string) ($b['site']['contact_email'] ?? '')), 190),
            ]);
        }

        if (isset($b['auth']) && is_array($b['auth'])) {
            $google = !empty($b['auth']['google']);
            $email = !empty($b['auth']['email']);
            if (!$google && !$email) {
                throw HttpError::validation(['auth' => 'Không thể tắt cùng lúc cả hai cách đăng nhập.']);
            }
            Settings::set('auth', [
                'google' => $google,
                'email' => $email,
                'require_email_verify' => !empty($b['auth']['require_email_verify']),
                'allow_signup' => !empty($b['auth']['allow_signup']),
            ]);
        }

        if (isset($b['content']) && is_array($b['content'])) {
            $voice = (string) ($b['content']['default_voice'] ?? 'xiaoxiao');
            Settings::set('content', [
                'default_voice' => in_array($voice, ['xiaoxiao', 'yunxi'], true) ? $voice : 'xiaoxiao',
                'show_feedback_button' => !empty($b['content']['show_feedback_button']),
            ]);
        }

        if (isset($b['privacy']) && is_array($b['privacy'])) {
            $days = (int) ($b['privacy']['visit_log_days'] ?? 90);
            Settings::set('privacy', [
                'visit_log_days' => in_array($days, [30, 90, 365], true) ? $days : 90,
                'mask_emails' => !empty($b['privacy']['mask_emails']),
                'privacy_page' => (string) ($b['privacy']['privacy_page'] ?? ''),
                'terms_page' => (string) ($b['privacy']['terms_page'] ?? ''),
            ]);
        }

        if (isset($b['backup']) && is_array($b['backup'])) {
            Settings::set('backup', ['daily' => !empty($b['backup']['daily'])]);
        }

        // Bật/tắt từng cấp cho người học.
        foreach ((array) ($b['levels'] ?? []) as $lv) {
            $id = (int) ($lv['id'] ?? 0);
            if ($id >= 1 && $id <= 6) {
                Db::update('levels', ['is_visible' => !empty($lv['visible']) ? 1 : 0], ['id' => $id]);
                \Zika\Services\ContentBuilder::forgetLevel($id);
            }
        }

        Audit::log($r, 'settings.update', 'settings');
        return $this->settings($r);
    }

    /** DELETE /admin/staff/{id} — gỡ quyền về người học. */
    public function removeStaff(Request $r): Response
    {
        $id = $r->id();
        if ($id === $r->userId()) {
            throw HttpError::invalid('Không tự gỡ quyền của mình được.');
        }
        if ((int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1
            && Db::val('SELECT role FROM users WHERE id = ?', [$id]) === 'admin') {
            throw HttpError::invalid('Phải còn ít nhất một quản trị viên.');
        }

        Db::update('users', ['role' => 'learner'], ['id' => $id]);
        Audit::log($r, 'staff.remove', 'user', $id);
        return Response::noContent();
    }

    private function isAdmin(Request $r): bool
    {
        return $r->role() === 'admin';
    }
}
