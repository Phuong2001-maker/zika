<?php
declare(strict_types=1);

namespace Zika\Ctrl;

use Zika\Auth;
use Zika\Db;
use Zika\Http\ApiError;
use Zika\Http\Req;
use Zika\Http\Res;
use Zika\Settings;
use Zika\Util;

/** Khu Quản trị: tổng quan, người dùng, phiên truy cập, thông báo, góp ý, ủng hộ, cài đặt. */
final class AdminCtrl
{
    /** Phiên còn được coi là "đang online" nếu ping trong vòng 3 phút. */
    private const LIVE_SQL = 'ended_at IS NULL AND last_ping_at > (NOW() - INTERVAL 180 SECOND)';

    /** GET /api/admin/overview?days=7 */
    public static function overview(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);
        $days = in_array($req->qInt('days', 7), [1, 7, 30], true) ? $req->qInt('days', 7) : 7;

        $kpi = [
            'users'   => (int) Db::val('SELECT COUNT(*) FROM users'),
            'online'  => (int) Db::val('SELECT COUNT(DISTINCT user_id) FROM study_sessions WHERE ' . self::LIVE_SQL),
            'newWeek' => (int) Db::val('SELECT COUNT(*) FROM users WHERE created_at > (NOW() - INTERVAL 7 DAY)'),
            'locked'  => (int) Db::val("SELECT COUNT(*) FROM users WHERE status = 'locked'"),
            'sessionsToday' => (int) Db::val('SELECT COUNT(*) FROM study_sessions WHERE DATE(started_at) = CURDATE()'),
            'avgMinutes'    => (int) round(((float) Db::val(
                'SELECT COALESCE(AVG(seconds), 0) FROM study_sessions WHERE started_at > (NOW() - INTERVAL ? DAY)',
                [$days]
            )) / 60),
        ];

        // Nhiệt đồ 7 ngày × 24 giờ: WEEKDAY() cho 0 = Thứ Hai, khớp nhãn T2..CN.
        $raw = Db::all(
            'SELECT WEEKDAY(started_at) AS d, HOUR(started_at) AS h, COUNT(DISTINCT user_id) AS n
               FROM study_sessions
              WHERE started_at > (NOW() - INTERVAL ? DAY)
              GROUP BY d, h',
            [max(7, $days)]
        );
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($raw as $r) {
            $grid[(int) $r['d']][(int) $r['h']] = (int) $r['n'];
        }

        // 30 ngày tài khoản mới
        $newRows = Db::all(
            'SELECT DATE(created_at) AS d, COUNT(*) AS n
               FROM users
              WHERE created_at > (NOW() - INTERVAL 30 DAY)
              GROUP BY d ORDER BY d'
        );
        $byDay = [];
        foreach ($newRows as $r) {
            $byDay[(string) $r['d']] = (int) $r['n'];
        }
        $bars = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $bars[] = ['date' => $d, 'n' => $byDay[$d] ?? 0];
        }

        // Ai đang online và đang làm gì
        $online = Db::all(
            'SELECT s.user_id, u.name, s.started_at, s.seconds, s.is_phone, s.last_part, s.last_lesson_id,
                    l.hsk, l.no
               FROM study_sessions s
               JOIN users u ON u.id = s.user_id
               LEFT JOIN lessons l ON l.id = s.last_lesson_id
              WHERE ' . self::LIVE_SQL . '
              ORDER BY s.started_at DESC LIMIT 20'
        );

        // Phân bố người học theo cấp
        $lvRows = Db::all(
            'SELECT lv.hsk, lv.visible, lv.total_words,
                    (SELECT COUNT(*) FROM lessons l WHERE l.hsk = lv.hsk) AS lessons,
                    (SELECT COUNT(*) FROM words w JOIN lessons l2 ON l2.id = w.lesson_id WHERE l2.hsk = lv.hsk) AS entered,
                    (SELECT COUNT(DISTINCT p.user_id) FROM user_part_progress p
                       JOIN lessons l3 ON l3.id = p.lesson_id
                      WHERE l3.hsk = lv.hsk AND p.position > 0) AS learners,
                    (SELECT COUNT(*) FROM (
                        SELECT p2.user_id
                          FROM user_part_progress p2
                          JOIN lessons l4 ON l4.id = p2.lesson_id
                         WHERE l4.hsk = lv.hsk AND p2.part = \'study\' AND p2.done_at IS NOT NULL
                         GROUP BY p2.user_id
                        HAVING COUNT(*) >= (SELECT COUNT(*) FROM lessons l5 WHERE l5.hsk = lv.hsk)
                     ) AS t) AS finished
               FROM levels lv ORDER BY lv.hsk'
        );

        Res::ok([
            'days'    => $days,
            'kpi'     => $kpi,
            'heatmap' => $grid,
            'newUsers' => $bars,
            'online'  => array_map(static fn($r) => [
                'userId'  => (int) $r['user_id'],
                'name'    => $r['name'],
                'initials' => Util::initials($r['name']),
                'since'   => date('H:i', strtotime((string) $r['started_at'])),
                'minutes' => (int) floor((int) $r['seconds'] / 60),
                'isPhone' => (bool) $r['is_phone'],
                'part'    => $r['last_part'],
                'hsk'     => $r['hsk'] === null ? null : (int) $r['hsk'],
                'lessonNo' => $r['no'] === null ? null : (int) $r['no'],
            ], $online),
            'levels' => array_map(static fn($r) => [
                'hsk'        => (int) $r['hsk'],
                'visible'    => (bool) $r['visible'],
                'lessons'    => (int) $r['lessons'],
                'entered'    => (int) $r['entered'],
                'totalWords' => (int) $r['total_words'],
                'learners'   => (int) $r['learners'],
                'finished'   => (int) $r['finished'],
            ], $lvRows),
        ]);
    }

    /** GET /api/admin/users?q=&provider=&sort=&status=&page= */
    public static function users(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);

        $page    = max(1, $req->qInt('page', 1));
        $perPage = min(100, max(10, $req->qInt('perPage', 10)));
        $q       = trim((string) $req->q('q', ''));
        $prov    = (string) $req->q('provider', '');
        $status  = (string) $req->q('status', '');
        $sort    = (string) $req->q('sort', 'recent');

        $where  = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if (in_array($prov, ['email', 'google'], true)) {
            $where[]  = 'u.provider = ?';
            $params[] = $prov;
        }
        if ($status === 'locked') {
            $where[] = "u.status = 'locked'";
        } elseif ($status === 'online') {
            $where[] = 'EXISTS (SELECT 1 FROM study_sessions s WHERE s.user_id = u.id AND ' . self::LIVE_SQL . ')';
        } elseif ($status === 'new7') {
            $where[] = 'u.created_at > (NOW() - INTERVAL 7 DAY)';
        }

        $orderBy = match ($sort) {
            'most'   => 'u.total_seconds DESC',
            'newest' => 'u.created_at DESC',
            default  => 'u.last_seen_at IS NULL, u.last_seen_at DESC',
        };

        $sqlWhere = implode(' AND ', $where);
        $total    = (int) Db::val("SELECT COUNT(*) FROM users u WHERE $sqlWhere", $params);

        $rows = Db::all(
            "SELECT u.*,
                    EXISTS (SELECT 1 FROM study_sessions s WHERE s.user_id = u.id AND " . self::LIVE_SQL . ") AS is_online,
                    (SELECT COUNT(DISTINCT p.lesson_id) FROM user_part_progress p
                      WHERE p.user_id = u.id AND p.part = 'study' AND p.done_at IS NOT NULL) AS done_lessons,
                    (SELECT l.hsk FROM user_part_progress p2
                       JOIN lessons l ON l.id = p2.lesson_id
                      WHERE p2.user_id = u.id ORDER BY p2.updated_at DESC LIMIT 1) AS cur_hsk,
                    (SELECT CONCAT(DATE_FORMAT(s2.started_at, '%H:%i'), '|',
                                   COALESCE(DATE_FORMAT(s2.ended_at, '%H:%i'), ''), '|', s2.seconds)
                       FROM study_sessions s2 WHERE s2.user_id = u.id
                      ORDER BY s2.started_at DESC LIMIT 1) AS last_session
               FROM users u
              WHERE $sqlWhere
              ORDER BY $orderBy
              LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );

        $mask = Settings::flag('mask_email', false);

        Res::ok([
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'counts'  => [
                'all'    => (int) Db::val('SELECT COUNT(*) FROM users'),
                'online' => (int) Db::val('SELECT COUNT(DISTINCT user_id) FROM study_sessions WHERE ' . self::LIVE_SQL),
                'new7'   => (int) Db::val('SELECT COUNT(*) FROM users WHERE created_at > (NOW() - INTERVAL 7 DAY)'),
                'locked' => (int) Db::val("SELECT COUNT(*) FROM users WHERE status = 'locked'"),
            ],
            'users' => array_map(static function ($u) use ($mask) {
                [$in, $out, $secs] = array_pad(explode('|', (string) ($u['last_session'] ?? '')), 3, '');
                return [
                    'id'          => (int) $u['id'],
                    'name'        => $u['name'],
                    'initials'    => Util::initials($u['name']),
                    'email'       => $mask ? Util::maskEmail($u['email']) : $u['email'],
                    'provider'    => $u['provider'],
                    'role'        => $u['role'],
                    'status'      => $u['status'],
                    'online'      => (bool) $u['is_online'],
                    'createdAt'   => $u['created_at'],
                    'lastSeenAt'  => $u['last_seen_at'],
                    'totalSeconds' => (int) $u['total_seconds'],
                    'doneLessons' => (int) $u['done_lessons'],
                    'curHsk'      => $u['cur_hsk'] === null ? null : (int) $u['cur_hsk'],
                    'lastSession' => $in === '' ? null : ['in' => $in, 'out' => $out, 'seconds' => (int) $secs],
                ];
            }, $rows),
        ]);
    }

    /** GET /api/admin/users/{id} — trang Chi tiết người dùng. */
    public static function userDetail(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);
        $id = $req->paramInt('id');

        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($u === null) {
            throw ApiError::notFound('Không có người dùng này.');
        }

        // Giờ học theo 24 giờ trong ngày (gộp 30 ngày gần nhất)
        $hourRows = Db::all(
            'SELECT HOUR(started_at) AS h, SUM(seconds) AS s
               FROM study_sessions
              WHERE user_id = ? AND started_at > (NOW() - INTERVAL 30 DAY)
              GROUP BY h',
            [$id]
        );
        $hours = array_fill(0, 24, 0);
        foreach ($hourRows as $r) {
            $hours[(int) $r['h']] = (int) round((int) $r['s'] / 60);
        }

        // Các phiên gần đây, gom theo ngày
        $sessions = Db::all(
            'SELECT s.*, l.hsk, l.no, l.title
               FROM study_sessions s
               LEFT JOIN lessons l ON l.id = s.last_lesson_id
              WHERE s.user_id = ?
              ORDER BY s.started_at DESC LIMIT 60',
            [$id]
        );
        $byDay = [];
        foreach ($sessions as $s) {
            $day = date('Y-m-d', strtotime((string) $s['started_at']));
            $byDay[$day] ??= ['date' => $day, 'count' => 0, 'seconds' => 0, 'rows' => []];
            $byDay[$day]['count']++;
            $byDay[$day]['seconds'] += (int) $s['seconds'];
            $byDay[$day]['rows'][] = [
                'in'      => date('H:i', strtotime((string) $s['started_at'])),
                'out'     => $s['ended_at'] === null ? null : date('H:i', strtotime((string) $s['ended_at'])),
                'seconds' => (int) $s['seconds'],
                'isPhone' => (bool) $s['is_phone'],
                'platform' => $s['platform'],
                'parts'   => array_values(array_filter(explode(',', (string) $s['parts']))),
                'hsk'     => $s['hsk'] === null ? null : (int) $s['hsk'],
                'lessonNo' => $s['no'] === null ? null : (int) $s['no'],
                'lessonTitle' => $s['title'],
            ];
        }

        // Tiến độ theo từng cấp
        $prog = Db::all(
            "SELECT l.hsk,
                    COUNT(DISTINCT l.id) AS lessons,
                    COUNT(DISTINCT CASE WHEN p.done_at IS NOT NULL THEN p.lesson_id END) AS done
               FROM lessons l
               LEFT JOIN user_part_progress p
                      ON p.lesson_id = l.id AND p.user_id = ? AND p.part = 'study'
              GROUP BY l.hsk ORDER BY l.hsk",
            [$id]
        );

        $attempts = Db::all(
            'SELECT a.*, l.hsk, l.no, l.title
               FROM test_attempts a
               LEFT JOIN lessons l ON l.id = a.lesson_id
              WHERE a.user_id = ? ORDER BY a.created_at DESC LIMIT 20',
            [$id]
        );

        Res::ok([
            'user'  => Auth::publicUser($u) + [
                'status'     => $u['status'],
                'lastSeenAt' => $u['last_seen_at'],
            ],
            'stats'    => AuthCtrl::statsOf($id),
            'hours'    => $hours,
            'days'     => array_values($byDay),
            'progress' => array_map(static fn($p) => [
                'hsk'     => (int) $p['hsk'],
                'lessons' => (int) $p['lessons'],
                'done'    => (int) $p['done'],
            ], $prog),
            'attempts' => array_map(static fn($a) => [
                'id'      => (int) $a['id'],
                'bank'    => $a['bank'],
                'score'   => (int) $a['score'],
                'total'   => (int) $a['total'],
                'seconds' => (int) $a['duration_sec'],
                'at'      => $a['created_at'],
                'hsk'     => $a['hsk'] === null ? null : (int) $a['hsk'],
                'lessonNo' => $a['no'] === null ? null : (int) $a['no'],
                'lessonTitle' => $a['title'],
            ], $attempts),
        ]);
    }

    /** PATCH /api/admin/users/{id} — khoá / mở khoá / đổi quyền. */
    public static function updateUser(Req $req): never
    {
        $me = Auth::needStaff($req, adminOnly: true);
        $id = $req->paramInt('id');

        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($u === null) {
            throw ApiError::notFound('Không có người dùng này.');
        }

        $data = [];
        if (($st = $req->str('status')) !== '' && in_array($st, ['active', 'locked'], true)) {
            if ((int) $u['id'] === (int) $me['id'] && $st === 'locked') {
                throw ApiError::badRequest('Không tự khoá tài khoản của mình được.');
            }
            $data['status'] = $st;
        }
        if (($role = $req->str('role')) !== '' && in_array($role, ['user', 'editor', 'admin'], true)) {
            if ((int) $u['id'] === (int) $me['id'] && $role !== 'admin') {
                throw ApiError::badRequest('Không tự hạ quyền của mình được.');
            }
            $data['role'] = $role;
        }

        if ($data === []) {
            throw ApiError::badRequest('Không có gì để cập nhật.');
        }

        Db::update('users', $data, 'id', $id);

        // Khoá tài khoản thì cắt luôn mọi thiết bị đang đăng nhập.
        if (($data['status'] ?? '') === 'locked') {
            Db::run('DELETE FROM auth_tokens WHERE user_id = ?', [$id]);
            Db::run('UPDATE study_sessions SET ended_at = NOW() WHERE user_id = ? AND ended_at IS NULL', [$id]);
        }

        Res::ok();
    }

    /** GET /api/admin/sessions?days=1&device=&part=&page= */
    public static function sessions(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);

        $days    = in_array($req->qInt('days', 1), [1, 7, 30], true) ? $req->qInt('days', 1) : 1;
        $device  = (string) $req->q('device', '');
        $part    = (string) $req->q('part', '');
        $page    = max(1, $req->qInt('page', 1));
        $perPage = min(100, max(10, $req->qInt('perPage', 20)));

        $where  = ['s.started_at > (NOW() - INTERVAL ? DAY)'];
        $params = [$days];

        if ($device === 'phone') {
            $where[] = 's.is_phone = 1';
        } elseif ($device === 'pc') {
            $where[] = 's.is_phone = 0';
        }
        if (in_array($part, ContentCtrl::PARTS, true)) {
            $where[]  = 'FIND_IN_SET(?, s.parts) > 0';
            $params[] = $part;
        }

        $sqlWhere = implode(' AND ', $where);
        $total    = (int) Db::val("SELECT COUNT(*) FROM study_sessions s WHERE $sqlWhere", $params);

        $rows = Db::all(
            "SELECT s.*, u.name
               FROM study_sessions s
               JOIN users u ON u.id = s.user_id
              WHERE $sqlWhere
              ORDER BY s.started_at DESC
              LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );

        $topPart = Db::val(
            'SELECT last_part FROM study_sessions
              WHERE started_at > (NOW() - INTERVAL ? DAY) AND last_part <> ""
              GROUP BY last_part ORDER BY COUNT(*) DESC LIMIT 1',
            [$days]
        );

        Res::ok([
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'kpi'     => [
                'today'   => (int) Db::val('SELECT COUNT(*) FROM study_sessions WHERE DATE(started_at) = CURDATE()'),
                'online'  => (int) Db::val('SELECT COUNT(DISTINCT user_id) FROM study_sessions WHERE ' . self::LIVE_SQL),
                'avgMinutes' => (int) round(((float) Db::val(
                    'SELECT COALESCE(AVG(seconds), 0) FROM study_sessions WHERE started_at > (NOW() - INTERVAL ? DAY)',
                    [$days]
                )) / 60),
                'topPart' => $topPart,
            ],
            'sessions' => array_map(static fn($s) => [
                'id'       => (int) $s['id'],
                'userId'   => (int) $s['user_id'],
                'name'     => $s['name'],
                'initials' => Util::initials($s['name']),
                'in'       => date('H:i', strtotime((string) $s['started_at'])),
                'out'      => $s['ended_at'] === null ? null : date('H:i', strtotime((string) $s['ended_at'])),
                'date'     => date('Y-m-d', strtotime((string) $s['started_at'])),
                'seconds'  => (int) $s['seconds'],
                'isPhone'  => (bool) $s['is_phone'],
                'platform' => $s['platform'],
                'parts'    => array_values(array_filter(explode(',', (string) $s['parts']))),
                'live'     => $s['ended_at'] === null
                              && strtotime((string) $s['last_ping_at']) > time() - 180,
            ], $rows),
        ]);
    }

    // ───────────────────────── Thông báo ─────────────────────────

    /** GET /api/admin/news */
    public static function newsList(Req $req): never
    {
        Auth::needStaff($req);
        Res::ok(Db::all('SELECT * FROM news ORDER BY created_at DESC'));
    }

    /** POST /api/admin/news, PATCH /api/admin/news/{id} */
    public static function saveNews(Req $req): never
    {
        Auth::needStaff($req);
        $id = $req->paramInt('id');

        $displays  = ['banner', 'modal', 'email'];
        $audiences = ['all', 'hsk1', 'hsk2', 'new7'];
        $statuses  = ['draft', 'scheduled', 'published'];

        $data = [
            'title'     => $req->need('title'),
            'body'      => $req->str('body'),
            'display'   => in_array($req->str('display'), $displays, true) ? $req->str('display') : 'banner',
            'audience'  => in_array($req->str('audience'), $audiences, true) ? $req->str('audience') : 'all',
            'start_at'  => self::dt($req->str('startAt')),
            'end_at'    => self::dt($req->str('endAt')),
            'cta_label' => $req->str('ctaLabel'),
            'cta_url'   => $req->str('ctaUrl'),
            'status'    => in_array($req->str('status'), $statuses, true) ? $req->str('status') : 'draft',
        ];

        if ($id > 0) {
            Db::update('news', $data, 'id', $id);
            Res::ok(['id' => $id]);
        }
        Res::created(['id' => Db::insert('news', $data)]);
    }

    /** DELETE /api/admin/news/{id} */
    public static function deleteNews(Req $req): never
    {
        Auth::needStaff($req);
        Db::run('DELETE FROM news WHERE id = ?', [$req->paramInt('id')]);
        Res::ok();
    }

    // ───────────────────────── Góp ý ─────────────────────────

    /** GET /api/admin/feedback?status= */
    public static function feedbackList(Req $req): never
    {
        Auth::needStaff($req);

        $status = (string) $req->q('status', '');
        $where  = in_array($status, ['new', 'doing', 'done'], true) ? 'WHERE f.status = ?' : '';
        $params = $where === '' ? [] : [$status];

        $rows = Db::all(
            "SELECT f.*, u.name, u.email, l.hsk, l.no, l.title
               FROM feedback f
               LEFT JOIN users u   ON u.id = f.user_id
               LEFT JOIN lessons l ON l.id = f.ctx_lesson_id
               $where
              ORDER BY f.created_at DESC LIMIT 200",
            $params
        );

        Res::ok([
            'counts' => [
                'new'   => (int) Db::val("SELECT COUNT(*) FROM feedback WHERE status = 'new'"),
                'doing' => (int) Db::val("SELECT COUNT(*) FROM feedback WHERE status = 'doing'"),
                'done'  => (int) Db::val("SELECT COUNT(*) FROM feedback WHERE status = 'done'"),
                'byKind' => Db::all('SELECT kind, COUNT(*) AS n FROM feedback GROUP BY kind'),
            ],
            'items' => array_map(static fn($f) => [
                'id'       => (int) $f['id'],
                'kind'     => $f['kind'],
                'body'     => $f['body'],
                'status'   => $f['status'],
                'assignee' => $f['assignee'],
                'note'     => $f['note'],
                'reply'    => $f['reply'],
                'device'   => $f['device'],
                'createdAt' => $f['created_at'],
                'user'     => $f['name'] === null ? null : [
                    'id'       => (int) $f['user_id'],
                    'name'     => $f['name'],
                    'email'    => $f['email'],
                    'initials' => Util::initials((string) $f['name']),
                ],
                'ctx' => [
                    'part'     => $f['ctx_part'],
                    'lessonId' => $f['ctx_lesson_id'] === null ? null : (int) $f['ctx_lesson_id'],
                    'hsk'      => $f['hsk'] === null ? null : (int) $f['hsk'],
                    'lessonNo' => $f['no'] === null ? null : (int) $f['no'],
                    'lessonTitle' => $f['title'],
                    'question' => $f['ctx_question'] === null ? null : (int) $f['ctx_question'],
                ],
            ], $rows),
        ]);
    }

    /** PATCH /api/admin/feedback/{id} */
    public static function updateFeedback(Req $req): never
    {
        $me = Auth::needStaff($req);
        $id = $req->paramInt('id');

        $data = [];
        if (($s = $req->str('status')) !== '' && in_array($s, ['new', 'doing', 'done'], true)) {
            $data['status'] = $s;
        }
        if (array_key_exists('note', $req->body())) {
            $data['note'] = $req->str('note');
        }
        if (array_key_exists('reply', $req->body())) {
            $data['reply'] = $req->str('reply');
        }
        if (array_key_exists('assignee', $req->body())) {
            $data['assignee'] = $req->str('assignee') ?: $me['name'];
        }

        if ($data === []) {
            throw ApiError::badRequest('Không có gì để cập nhật.');
        }

        Db::update('feedback', $data, 'id', $id);
        Res::ok();
    }

    // ───────────────────────── Ủng hộ & Shopee ─────────────────────────

    /** GET /api/admin/support-links */
    public static function supportList(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);

        $rows = Db::all('SELECT * FROM support_links ORDER BY sort, id');
        $sum  = Db::one('SELECT COALESCE(SUM(views),0) AS v, COALESCE(SUM(clicks),0) AS c FROM support_links');

        Res::ok([
            'enabled' => Settings::flag('show_support', false),
            'donate'  => Settings::json('donate', [
                'enabled' => false, 'bank' => '', 'owner' => '', 'number' => '', 'memo' => '', 'qr' => '',
            ]),
            'kpi' => [
                'views'  => (int) $sum['v'],
                'clicks' => (int) $sum['c'],
                'ctr'    => ((int) $sum['v']) > 0 ? round((int) $sum['c'] / (int) $sum['v'] * 100, 1) : 0.0,
                'active' => (int) Db::val('SELECT COUNT(*) FROM support_links WHERE active = 1'),
                'total'  => count($rows),
            ],
            'links' => array_map(static fn($r) => [
                'id'         => (int) $r['id'],
                'name'       => $r['name'],
                'url'        => $r['url'],
                'image'      => $r['image'],
                'placements' => array_values(array_filter(explode(',', (string) $r['placements']))),
                'views'      => (int) $r['views'],
                'clicks'     => (int) $r['clicks'],
                'ctr'        => ((int) $r['views']) > 0 ? round((int) $r['clicks'] / (int) $r['views'] * 100, 1) : 0.0,
                'active'     => (bool) $r['active'],
                'startAt'    => $r['start_at'],
                'endAt'      => $r['end_at'],
                'sort'       => (int) $r['sort'],
            ], $rows),
        ]);
    }

    /** POST /api/admin/support-links, PATCH /api/admin/support-links/{id} */
    public static function saveSupport(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);
        $id = $req->paramInt('id');

        $url = $req->need('url');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw ApiError::badRequest('Link không đúng dạng.', ['url' => 'Link không đúng dạng.']);
        }

        $places = array_values(array_intersect(
            array_map('strval', $req->arr('placements')),
            ['home', 'summary', 'account']
        ));

        $data = [
            'name'       => $req->need('name'),
            'url'        => $url,
            'image'      => $req->str('image'),
            'placements' => implode(',', $places),
            'active'     => $req->bool('active') ? 1 : 0,
            'start_at'   => self::dt($req->str('startAt'), 'Y-m-d'),
            'end_at'     => self::dt($req->str('endAt'), 'Y-m-d'),
            'sort'       => $req->int('sort'),
        ];

        if ($id > 0) {
            Db::update('support_links', $data, 'id', $id);
            Res::ok(['id' => $id]);
        }
        Res::created(['id' => Db::insert('support_links', $data)]);
    }

    /** DELETE /api/admin/support-links/{id} */
    public static function deleteSupport(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);
        Db::run('DELETE FROM support_links WHERE id = ?', [$req->paramInt('id')]);
        Res::ok();
    }

    // ───────────────────────── Cài đặt chung ─────────────────────────

    /** GET /api/admin/settings */
    public static function settingsGet(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);

        Res::ok([
            'siteName'     => Settings::get('site_name', 'Zìkǎ'),
            'tagline'      => Settings::get('tagline', ''),
            'contactEmail' => Settings::get('contact_email', ''),
            'siteUrl'      => Settings::get('site_url', 'http://localhost:5173'),
            'loginGoogle'  => Settings::flag('login_google', true),
            'loginEmail'   => Settings::flag('login_email', true),
            'verifyEmail'  => Settings::flag('verify_email', false),
            'allowSignup'  => Settings::flag('allow_signup', true),
            'showFeedback' => Settings::flag('show_feedback', true),
            'showSupport'  => Settings::flag('show_support', false),
            'maskEmail'    => Settings::flag('mask_email', false),
            'defaultVoice' => Settings::get('default_voice', 'xiaoxiao'),
            'logKeepDays'  => (int) (Settings::get('log_keep_days', '90') ?? 90),
            'backupDaily'  => Settings::flag('backup_daily', true),
            'levels'       => Db::all('SELECT hsk, visible, sample, total_words FROM levels ORDER BY hsk'),
            'staff'        => Db::all(
                "SELECT id, name, email, role FROM users WHERE role IN ('admin','editor') ORDER BY role, name"
            ),
        ]);
    }

    /** PUT /api/admin/settings */
    public static function settingsPut(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);
        $b = $req->body();

        $strings = [
            'siteName' => 'site_name', 'tagline' => 'tagline', 'contactEmail' => 'contact_email',
            'siteUrl' => 'site_url', 'defaultVoice' => 'default_voice', 'logKeepDays' => 'log_keep_days',
        ];
        $flags = [
            'loginGoogle' => 'login_google', 'loginEmail' => 'login_email', 'verifyEmail' => 'verify_email',
            'allowSignup' => 'allow_signup', 'showFeedback' => 'show_feedback', 'showSupport' => 'show_support',
            'maskEmail' => 'mask_email', 'backupDaily' => 'backup_daily',
        ];

        foreach ($strings as $in => $key) {
            if (array_key_exists($in, $b)) {
                Settings::set($key, $req->str($in));
            }
        }
        foreach ($flags as $in => $key) {
            if (array_key_exists($in, $b)) {
                Settings::set($key, $req->bool($in));
            }
        }

        // Bật/tắt từng cấp HSK cho người học
        foreach ($req->arr('levels') as $lv) {
            $hsk = (int) ($lv['hsk'] ?? 0);
            if ($hsk < 1 || $hsk > 6) {
                continue;
            }
            $data = ['visible' => !empty($lv['visible']) ? 1 : 0];
            if (isset($lv['sample'])) {
                $data['sample'] = mb_substr((string) $lv['sample'], 0, 60);
            }
            if (isset($lv['totalWords'])) {
                $data['total_words'] = max(0, (int) $lv['totalWords']);
            }
            Db::update('levels', $data, 'hsk', $hsk);
        }

        // Thông tin ủng hộ trực tiếp (số tài khoản, QR)
        if (array_key_exists('donate', $b) && is_array($b['donate'])) {
            $d = $b['donate'];
            Settings::set('donate', [
                'enabled' => !empty($d['enabled']),
                'bank'    => mb_substr((string) ($d['bank'] ?? ''), 0, 80),
                'owner'   => mb_substr((string) ($d['owner'] ?? ''), 0, 120),
                'number'  => mb_substr((string) ($d['number'] ?? ''), 0, 40),
                'memo'    => mb_substr((string) ($d['memo'] ?? ''), 0, 140),
                'qr'      => mb_substr((string) ($d['qr'] ?? ''), 0, 255),
            ]);
        }

        Res::ok();
    }

    /** POST /api/admin/staff — mời/đổi quyền biên tập viên theo email. */
    public static function inviteStaff(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);

        $email = mb_strtolower($req->need('email'));
        $role  = $req->str('role', 'editor');
        if (!in_array($role, ['editor', 'admin', 'user'], true)) {
            throw ApiError::badRequest('Quyền không hợp lệ.');
        }

        $u = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u === null) {
            throw ApiError::notFound('Chưa có tài khoản nào dùng email này. Nhờ họ đăng ký trước.');
        }

        Db::update('users', ['role' => $role], 'id', $u['id']);
        Res::ok();
    }

    /** Chuẩn hoá chuỗi ngày/giờ từ input về định dạng CSDL; rỗng -> null. */
    private static function dt(string $v, string $format = 'Y-m-d H:i:s'): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $ts = strtotime($v);
        return $ts === false ? null : date($format, $ts);
    }
}
