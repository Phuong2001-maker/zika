<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Settings;
use Zika\Core\Str;
use Zika\Core\Validator;
use Zika\Services\UserService;

/** Quản trị · Tổng quan, Người dùng, Phiên truy cập (04 §10.2). Quyền A. */
final class StatsController
{
    /** Coi là đang online nếu có hoạt động trong 180 giây. */
    private const LIVE = 180;

    /* ------------------------------------------------------------------ */
    /* Tổng quan                                                           */
    /* ------------------------------------------------------------------ */

    /** GET /admin/overview?range=today|7d|30d */
    public function overview(Request $r): array
    {
        $range = (string) $r->q('range', '7d');
        $days = match ($range) { 'today' => 1, '30d' => 30, default => 7 };

        $accounts = (int) Db::val('SELECT COUNT(*) FROM users');
        $online = $this->onlineCount();
        $activeToday = (int) Db::val('SELECT COUNT(DISTINCT user_id) FROM visits WHERE DATE(started_at) = CURDATE()');

        $peak = Db::one('SELECT online, ts FROM online_samples WHERE DATE(ts) = CURDATE() ORDER BY online DESC, ts LIMIT 1');

        // Phút học trung bình mỗi người mỗi ngày có học trong khoảng.
        $avg = Db::one(
            'SELECT COALESCE(SUM(duration_sec), 0) AS secs, COUNT(DISTINCT user_id, DATE(started_at)) AS person_days
               FROM visits WHERE started_at > (NOW() - INTERVAL ? DAY)',
            [$days],
        ) ?? ['secs' => 0, 'person_days' => 0];
        $avgMin = ((int) $avg['person_days']) > 0 ? (int) round(((int) $avg['secs']) / (int) $avg['person_days'] / 60) : 0;

        $aff = Db::one(
            'SELECT COALESCE(SUM(views), 0) AS v, COALESCE(SUM(clicks), 0) AS c
               FROM affiliate_daily WHERE d > (CURDATE() - INTERVAL ? DAY)',
            [$days],
        ) ?? ['v' => 0, 'c' => 0];

        return [
            'updatedAt' => date('c'),
            'kpi' => [
                'accounts' => $accounts,
                'newThisWeek' => (int) Db::val('SELECT COUNT(*) FROM users WHERE created_at > (NOW() - INTERVAL 7 DAY)'),
                'online' => $online,
                'peakToday' => [
                    'count' => (int) ($peak['online'] ?? 0),
                    'at' => $peak === null ? null : date('H:i', strtotime((string) $peak['ts'])),
                ],
                'activeToday' => $activeToday,
                'activeTodayPct' => $accounts > 0 ? round($activeToday / $accounts * 100, 1) : 0.0,
                'avgStudyMin' => $avgMin,
                'affClicks' => (int) $aff['c'],
                'affCtr' => ((int) $aff['v']) > 0 ? round(((int) $aff['c']) / ((int) $aff['v']) * 100, 1) : 0.0,
            ],
            'heatmap' => $this->heatmap($days),
            'online' => $this->onlineList(),
            'newUsersDaily' => $this->newUsersDaily(),
            'signup' => [
                'google' => (int) Db::val("SELECT COUNT(*) FROM users WHERE signup_method = 'google'"),
                'email' => (int) Db::val("SELECT COUNT(*) FROM users WHERE signup_method = 'email'"),
            ],
            'devices' => $this->devices($days),
            'todo' => [
                'unreadFeedback' => (int) Db::val('SELECT COUNT(*) FROM feedback WHERE is_read = 0'),
                'inactiveLinks' => (int) Db::val('SELECT COUNT(*) FROM affiliate_links WHERE is_active = 0'),
            ],
            'levels' => $this->levelStats(),
        ];
    }

    /** 7 hàng (T2…CN) × 24 cột, số người online trung bình theo giờ. @return array<string,mixed> */
    private function heatmap(int $days): array
    {
        $rows = Db::all(
            'SELECT WEEKDAY(started_at) AS d, HOUR(started_at) AS h, COUNT(DISTINCT user_id) AS n
               FROM visits WHERE started_at > (NOW() - INTERVAL ? DAY) GROUP BY d, h',
            [$days],
        );

        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        $max = 0;
        foreach ($rows as $x) {
            $v = (int) $x['n'];
            $grid[(int) $x['d']][(int) $x['h']] = $v;
            $max = max($max, $v);
        }

        $labels = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
        $out = [];
        foreach ($labels as $i => $label) {
            $out[] = ['label' => $label, 'cells' => $grid[$i]];
        }

        return ['rows' => $out, 'max' => $max, 'summary' => $this->heatSummary($grid)];
    }

    /** “Đông nhất 20:00–22:00 các tối trong tuần.” @param list<list<int>> $grid */
    private function heatSummary(array $grid): string
    {
        $best = -1;
        $bestH = 20;
        for ($h = 0; $h < 23; $h++) {
            $sum = 0;
            for ($d = 0; $d < 5; $d++) {      // T2–T6
                $sum += $grid[$d][$h] + $grid[$d][$h + 1];
            }
            if ($sum > $best) {
                $best = $sum;
                $bestH = $h;
            }
        }
        if ($best <= 0) {
            return 'Chưa đủ dữ liệu để thấy khung giờ đông nhất.';
        }
        $buoi = match (true) {
            $bestH >= 5 && $bestH <= 10 => 'các buổi sáng',
            $bestH >= 11 && $bestH <= 13 => 'các buổi trưa',
            $bestH >= 14 && $bestH <= 17 => 'các buổi chiều',
            default => 'các tối',
        };
        return sprintf('Đông nhất %02d:00–%02d:00 %s trong tuần.', $bestH, $bestH + 2, $buoi);
    }

    /**
     * Mỗi người đúng một dòng. Một người mở nhiều tab hoặc nhiều máy thì có nhiều phiên còn
     * mở cùng lúc; lấy phiên hoạt động gần nhất để danh sách khớp số “đang online” — con số
     * đó đếm theo người (`onlineCount()`), không đếm theo phiên.
     * @return list<array<string,mixed>>
     */
    private function onlineList(): array
    {
        $rows = Db::all(
            'SELECT user_id, started_at, duration_sec, activity, device_type, display_name
               FROM (SELECT v.user_id, v.started_at, v.duration_sec, v.activity, v.device_type, u.display_name,
                            ROW_NUMBER() OVER (PARTITION BY v.user_id ORDER BY v.last_seen_at DESC, v.id DESC) AS rn
                       FROM visits v JOIN users u ON u.id = v.user_id
                      WHERE v.ended_at IS NULL AND v.last_seen_at > (NOW() - INTERVAL ? SECOND)) t
              WHERE rn = 1
              ORDER BY started_at DESC LIMIT 20',
            [self::LIVE],
        );
        return array_map(static fn (array $v): array => [
            'userId' => (int) $v['user_id'],
            'initials' => Str::initials((string) $v['display_name']),
            'name' => (string) $v['display_name'],
            'activity' => (string) $v['activity'],
            'device' => (string) $v['device_type'],
            'since' => date('H:i', strtotime((string) $v['started_at'])),
            'durationMin' => (int) floor(((int) $v['duration_sec']) / 60),
        ], $rows);
    }

    /** 30 ngày tài khoản mới. @return list<array{date:string,count:int}> */
    private function newUsersDaily(): array
    {
        $byDay = [];
        foreach (Db::all(
            'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM users WHERE created_at > (NOW() - INTERVAL 30 DAY) GROUP BY d',
        ) as $x) {
            $byDay[(string) $x['d']] = (int) $x['n'];
        }
        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $out[] = ['date' => $d, 'count' => $byDay[$d] ?? 0];
        }
        return $out;
    }

    /** Tỉ lệ thiết bị theo thời lượng phiên. @return array<string,int> */
    private function devices(int $days): array
    {
        $rows = Db::all(
            'SELECT device_type, COALESCE(SUM(duration_sec), 0) AS secs
               FROM visits WHERE started_at > (NOW() - INTERVAL ? DAY) GROUP BY device_type',
            [$days],
        );
        $total = array_sum(array_map(static fn (array $x): int => (int) $x['secs'], $rows));
        $out = ['phone' => 0, 'desktop' => 0, 'tablet' => 0];
        foreach ($rows as $x) {
            $out[(string) $x['device_type']] = $total > 0 ? (int) round(((int) $x['secs']) / $total * 100) : 0;
        }
        return $out;
    }

    /**
     * Mỗi cấp: bao nhiêu người đang học, bao nhiêu người học xong cả cấp.
     * Gom ở PHP thay vì lồng truy vấn con, vì MariaDB không cho bảng dẫn xuất
     * tham chiếu ra cột của truy vấn ngoài.
     * @return list<array<string,mixed>>
     */
    private function levelStats(): array
    {
        $levels = Db::all('SELECT id, is_visible FROM levels ORDER BY id');

        $entered = [];
        foreach (Db::all('SELECT level_id, COUNT(*) AS n FROM words GROUP BY level_id') as $x) {
            $entered[(int) $x['level_id']] = (int) $x['n'];
        }

        $published = [];
        foreach (Db::all("SELECT level_id, COUNT(*) AS n FROM lessons WHERE status = 'published' GROUP BY level_id") as $x) {
            $published[(int) $x['level_id']] = (int) $x['n'];
        }

        // Mỗi (cấp, người học): đã xong bao nhiêu bài phần Lật thẻ.
        $learning = [];
        $completed = [];
        foreach (Db::all(
            "SELECT l.level_id, p.user_id,
                    COUNT(*) AS touched,
                    SUM(p.status = 'done') AS done
               FROM lesson_progress p
               JOIN lessons l ON l.id = p.lesson_id
              WHERE p.part = 'lt'
              GROUP BY l.level_id, p.user_id",
        ) as $x) {
            $lv = (int) $x['level_id'];
            $learning[$lv] = ($learning[$lv] ?? 0) + 1;
            $need = $published[$lv] ?? 0;
            if ($need > 0 && (int) $x['done'] >= $need) {
                $completed[$lv] = ($completed[$lv] ?? 0) + 1;
            }
        }

        return array_map(static fn (array $v): array => [
            'levelId' => (int) $v['id'],
            'visible' => (bool) $v['is_visible'],
            'learning' => $learning[(int) $v['id']] ?? 0,
            'completed' => $completed[(int) $v['id']] ?? 0,
            'enteredWords' => $entered[(int) $v['id']] ?? 0,
        ], $levels);
    }

    /* ------------------------------------------------------------------ */
    /* Người dùng                                                          */
    /* ------------------------------------------------------------------ */

    /** GET /admin/users */
    public function users(Request $r): Response
    {
        $where = ['1 = 1'];
        $params = [];

        if (($q = trim((string) $r->q('q', ''))) !== '') {
            $where[] = '(u.display_name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        if (in_array($m = (string) $r->q('method', 'all'), ['google', 'email'], true)) {
            $where[] = 'u.signup_method = ?';
            $params[] = $m;
        }
        switch ((string) $r->q('tab', 'all')) {
            case 'online':
                $where[] = 'u.last_seen_at > (NOW() - INTERVAL ' . self::LIVE . ' SECOND)';
                break;
            case 'new':
                $where[] = 'u.created_at > (NOW() - INTERVAL 7 DAY)';
                break;
            case 'locked':
                $where[] = "u.status = 'locked'";
                break;
        }

        $order = match ((string) $r->q('sort', 'recent')) {
            'study' => 'u.total_study_sec DESC',
            'new' => 'u.created_at DESC',
            default => 'u.last_seen_at IS NULL, u.last_seen_at DESC',
        };

        $sql = implode(' AND ', $where);
        $per = $r->qInt('per', 10, 1, 100);
        $page = $r->qInt('page', 1, 1);
        $total = (int) Db::val("SELECT COUNT(*) FROM users u WHERE $sql", $params);

        $rows = Db::all(
            "SELECT u.*,
                    (SELECT CONCAT(DATE_FORMAT(v.started_at, '%H:%i'), '|',
                                   COALESCE(DATE_FORMAT(v.ended_at, '%H:%i'), ''), '|', v.duration_sec)
                       FROM visits v WHERE v.user_id = u.id ORDER BY v.started_at DESC LIMIT 1) AS last_visit
               FROM users u WHERE $sql ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per),
            $params,
        );

        $mask = Settings::value('privacy', 'mask_emails', true);

        return Response::json(
            array_map(static function (array $u) use ($mask): array {
                [$in, $out, $secs] = array_pad(explode('|', (string) ($u['last_visit'] ?? '')), 3, '');
                $live = $u['last_seen_at'] !== null && strtotime((string) $u['last_seen_at']) > time() - self::LIVE;
                return [
                    'id' => (int) $u['id'],
                    'initials' => Str::initials((string) $u['display_name']),
                    'name' => (string) $u['display_name'],
                    'email' => $mask ? Str::maskEmail((string) $u['email']) : (string) $u['email'],
                    'isNew' => strtotime((string) $u['created_at']) > time() - 7 * 86400,
                    'method' => (string) $u['signup_method'],
                    'role' => (string) $u['role'],
                    'createdAt' => date('Y-m-d', strtotime((string) $u['created_at'])),
                    'lastSeenAt' => $u['last_seen_at'] === null ? null : date('c', strtotime((string) $u['last_seen_at'])),
                    'lastVisit' => $in === '' ? null : ['start' => $in, 'end' => $out !== '' ? $out : null, 'durationMin' => (int) round(((int) $secs) / 60)],
                    'totalStudySec' => (int) $u['total_study_sec'],
                    'currentLevelId' => $u['current_level_id'] === null ? null : (int) $u['current_level_id'],
                    'lessonsDone' => (int) $u['lessons_done'],
                    'status' => $u['status'] === 'locked' ? 'locked' : ($live ? 'online' : 'offline'),
                ];
            }, $rows),
            200,
            [
                'total' => $total,
                'page' => $page,
                'per' => $per,
                'counts' => [
                    'all' => (int) Db::val('SELECT COUNT(*) FROM users'),
                    'online' => $this->onlineCount(),
                    'new' => (int) Db::val('SELECT COUNT(*) FROM users WHERE created_at > (NOW() - INTERVAL 7 DAY)'),
                    'locked' => (int) Db::val("SELECT COUNT(*) FROM users WHERE status = 'locked'"),
                ],
            ],
        );
    }

    /** GET /admin/users/{id} */
    public function user(Request $r): array
    {
        $u = $this->find($r->id());

        $mins = array_fill(0, 24, 0);
        foreach (Db::all(
            'SELECT HOUR(started_at) AS h, COALESCE(SUM(duration_sec), 0) AS s
               FROM visits WHERE user_id = ? AND started_at > (NOW() - INTERVAL 30 DAY) GROUP BY h',
            [(int) $u['id']],
        ) as $x) {
            $mins[(int) $x['h']] = (int) round(((int) $x['s']) / 60);
        }

        $visits = Db::one(
            'SELECT COUNT(*) AS n, COALESCE(AVG(duration_sec), 0) AS avg_sec FROM visits WHERE user_id = ?',
            [(int) $u['id']],
        ) ?? ['n' => 0, 'avg_sec' => 0];

        $prog = Db::all(
            "SELECT l.level_id,
                    COUNT(DISTINCT l.id) AS total,
                    COUNT(DISTINCT CASE WHEN p.status = 'done' THEN p.lesson_id END) AS done
               FROM lessons l
               LEFT JOIN lesson_progress p ON p.lesson_id = l.id AND p.user_id = ? AND p.part = 'lt'
               JOIN levels v ON v.id = l.level_id
              WHERE l.status = 'published' AND v.is_visible = 1
              GROUP BY l.level_id ORDER BY l.level_id",
            [(int) $u['id']],
        );

        $devices = Db::all(
            'SELECT device_type, os, browser, COALESCE(SUM(duration_sec), 0) AS secs
               FROM visits WHERE user_id = ? GROUP BY device_type, os, browser ORDER BY secs DESC',
            [(int) $u['id']],
        );
        $totalSecs = array_sum(array_map(static fn (array $d): int => (int) $d['secs'], $devices));

        return [
            'user' => UserService::toApi($u) + [
                'status' => $u['status'] === 'locked'
                    ? 'locked'
                    : (($u['last_seen_at'] !== null && strtotime((string) $u['last_seen_at']) > time() - self::LIVE) ? 'online' : 'offline'),
                'device' => $u['last_device'],
                'selfLevelInitial' => (string) $u['self_level_initial'],
                'lockedReason' => $u['locked_reason'],
            ],
            'kpi' => [
                'studySec' => (int) $u['total_study_sec'],
                'visits' => (int) $visits['n'],
                'avgVisitMin' => (int) round(((float) $visits['avg_sec']) / 60),
                'currentLevelId' => $u['current_level_id'] === null ? null : (int) $u['current_level_id'],
                'lessonsDone' => (int) $u['lessons_done'],
            ],
            'progressByLevel' => array_map(static fn (array $p): array => [
                'levelId' => (int) $p['level_id'],
                'done' => (int) $p['done'],
                'total' => (int) $p['total'],
            ], $prog),
            'minutesByHour' => $mins,
            'hourSummary' => $this->hourSummary($mins),
            'devices' => array_map(static fn (array $d): array => [
                'type' => (string) $d['device_type'],
                'label' => ['phone' => 'Điện thoại', 'tablet' => 'Máy tính bảng', 'desktop' => 'Máy tính'][(string) $d['device_type']] ?? 'Khác',
                'detail' => trim(((string) $d['browser']) . ' · ' . ((string) $d['os']), ' ·'),
                'pct' => $totalSecs > 0 ? (int) round(((int) $d['secs']) / $totalSecs * 100) : 0,
            ], array_slice($devices, 0, 4)),
        ];
    }

    /** @param list<int> $mins */
    private function hourSummary(array $mins): string
    {
        $best = 0;
        $bestH = 0;
        for ($h = 0; $h < 23; $h++) {
            $s = $mins[$h] + $mins[$h + 1];
            if ($s > $best) {
                $best = $s;
                $bestH = $h;
            }
        }
        if ($best === 0) {
            return 'Chưa có dữ liệu giờ học.';
        }
        return sprintf('Học nhiều nhất %02d:00–%02d:00.', $bestH, $bestH + 2);
    }

    /** GET /admin/users/{id}/visits?range=7d|30d|all&before= */
    public function visits(Request $r): array
    {
        $u = $this->find($r->id());
        $days = match ((string) $r->q('range', '7d')) { '30d' => 30, 'all' => 3650, default => 7 };

        $params = [(int) $u['id'], $days];
        $beforeSql = '';
        if (($before = (string) $r->q('before', '')) !== '' && strtotime($before) !== false) {
            $beforeSql = ' AND DATE(started_at) < ?';
            $params[] = date('Y-m-d', strtotime($before));
        }

        $rows = Db::all(
            "SELECT * FROM visits
              WHERE user_id = ? AND started_at > (NOW() - INTERVAL ? DAY)$beforeSql
              ORDER BY started_at DESC LIMIT 60",
            $params,
        );

        $byDay = [];
        foreach ($rows as $v) {
            $day = date('Y-m-d', strtotime((string) $v['started_at']));
            if (!isset($byDay[$day]) && count($byDay) >= 7) {
                continue;   // mỗi lần trả tối đa 7 ngày có dữ liệu
            }
            $byDay[$day] ??= ['date' => $day, 'label' => Str::dayLabel($day), 'count' => 0, 'totalMin' => 0, 'blocks' => [], 'rows' => []];
            $byDay[$day]['count']++;
            $byDay[$day]['totalMin'] += (int) round(((int) $v['duration_sec']) / 60);
            $byDay[$day]['blocks'][] = [
                'start' => date('H:i', strtotime((string) $v['started_at'])),
                'end' => date('H:i', strtotime((string) ($v['ended_at'] ?? $v['last_seen_at']))),
            ];
            $byDay[$day]['rows'][] = [
                'start' => date('H:i', strtotime((string) $v['started_at'])),
                'end' => $v['ended_at'] === null ? null : date('H:i', strtotime((string) $v['ended_at'])),
                'durationMin' => (int) round(((int) $v['duration_sec']) / 60),
                'device' => (string) $v['device_type'],
                'deviceLabel' => ['phone' => 'Điện thoại', 'tablet' => 'Máy tính bảng', 'desktop' => 'Máy tính'][(string) $v['device_type']] ?? 'Khác',
                'summary' => (string) $v['activity'],
            ];
        }

        $days7 = array_values($byDay);
        return [
            'days' => $days7,
            'nextBefore' => count($days7) >= 7 ? end($days7)['date'] : null,
        ];
    }

    /** POST /admin/users/{id}/lock */
    public function lock(Request $r): array
    {
        $u = $this->find($r->id());
        if ((int) $u['id'] === $r->userId()) {
            throw HttpError::invalid('Không tự khoá tài khoản của mình được.');
        }

        $v = new Validator($r->body);
        $reason = $v->strOrNull('reason', 'Lý do', 255);
        $v->done();

        Db::update('users', ['status' => 'locked', 'locked_at' => date('Y-m-d H:i:s'), 'locked_reason' => $reason], ['id' => (int) $u['id']]);
        // Khoá thì cắt luôn mọi phiên đăng nhập đang mở.
        Db::exec('UPDATE auth_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [(int) $u['id']]);
        Db::exec('UPDATE visits SET ended_at = NOW() WHERE user_id = ? AND ended_at IS NULL', [(int) $u['id']]);

        Audit::log($r, 'user.lock', 'user', (int) $u['id'], ['reason' => $reason]);
        return ['ok' => true];
    }

    /** POST /admin/users/{id}/unlock */
    public function unlock(Request $r): array
    {
        $u = $this->find($r->id());
        Db::update('users', ['status' => 'active', 'locked_at' => null, 'locked_reason' => null], ['id' => (int) $u['id']]);
        Audit::log($r, 'user.unlock', 'user', (int) $u['id']);
        return ['ok' => true];
    }

    /** PATCH /admin/users/{id}/role */
    public function role(Request $r): array
    {
        $u = $this->find($r->id());
        $v = new Validator($r->body);
        $role = (string) $v->in('role', 'Quyền', ['learner', 'editor', 'admin']);
        $v->done();

        if ((int) $u['id'] === $r->userId() && $role !== 'admin') {
            throw HttpError::invalid('Không tự hạ quyền của mình được.');
        }
        if ($u['role'] === 'admin' && $role !== 'admin'
            && (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
            throw HttpError::invalid('Phải còn ít nhất một quản trị viên.');
        }

        Db::update('users', ['role' => $role], ['id' => (int) $u['id']]);
        Audit::log($r, 'user.role', 'user', (int) $u['id'], ['role' => $role]);
        return ['ok' => true];
    }

    /** DELETE /admin/users/{id} */
    public function delete(Request $r): Response
    {
        $u = $this->find($r->id());
        if ((int) $u['id'] === $r->userId()) {
            throw HttpError::invalid('Không tự xoá tài khoản của mình được.');
        }
        Db::exec('DELETE FROM users WHERE id = ?', [(int) $u['id']]);
        Audit::log($r, 'user.delete', 'user', (int) $u['id'], ['email' => $u['email']]);
        return Response::noContent();
    }

    /** POST /admin/users/bulk  { ids, action } */
    public function bulk(Request $r): array
    {
        $v = new Validator($r->body);
        $ids = $v->ids('ids', 'Danh sách người dùng');
        $action = (string) $v->in('action', 'Thao tác', ['lock', 'unlock']);
        $v->done();

        $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $r->userId()));
        if ($ids === []) {
            return ['changed' => 0];
        }

        $in = Db::in($ids);
        if ($action === 'lock') {
            $n = Db::exec("UPDATE users SET status = 'locked', locked_at = NOW() WHERE id IN ($in)", $ids);
            Db::exec("UPDATE auth_sessions SET revoked_at = NOW() WHERE user_id IN ($in) AND revoked_at IS NULL", $ids);
        } else {
            $n = Db::exec("UPDATE users SET status = 'active', locked_at = NULL, locked_reason = NULL WHERE id IN ($in)", $ids);
        }

        Audit::log($r, 'user.bulk', 'user', null, ['action' => $action, 'ids' => $ids]);
        return ['changed' => $n];
    }

    /** POST /admin/users/{id}/notify  { kind, title, body } */
    public function notify(Request $r): Response
    {
        $u = $this->find($r->id());
        $v = new Validator($r->body);
        $kind = (string) $v->in('kind', 'Kiểu', ['modal', 'email'], 'modal');
        $title = $v->str('title', 'Tiêu đề', min: 1, max: 160);
        $body = $v->str('body', 'Nội dung', min: 1, max: 4000);
        $v->done();

        $id = Db::insert('announcements', [
            'title' => $title,
            'body' => $body,
            'kind' => $kind,
            'audience' => 'user',
            'target_user_id' => (int) $u['id'],
            'status' => 'published',
            'starts_at' => date('Y-m-d H:i:s'),
            'created_by' => $r->userId(),
        ]);

        Audit::log($r, 'user.notify', 'user', (int) $u['id'], ['announcementId' => $id]);
        return Response::json(['id' => $id], 201);
    }

    /* ------------------------------------------------------------------ */
    /* Phiên truy cập                                                      */
    /* ------------------------------------------------------------------ */

    /** GET /admin/sessions */
    public function sessions(Request $r): Response
    {
        $days = match ((string) $r->q('range', 'today')) { '7d' => 7, '30d' => 30, default => 0 };
        $where = $days === 0 ? ['DATE(v.started_at) = CURDATE()'] : ['v.started_at > (NOW() - INTERVAL ' . $days . ' DAY)'];
        $params = [];

        if (($q = trim((string) $r->q('q', ''))) !== '') {
            $where[] = '(u.display_name LIKE ? OR u.email LIKE ?)';
            array_push($params, "%$q%", "%$q%");
        }
        if (in_array($d = (string) $r->q('device', 'all'), ['phone', 'desktop', 'tablet'], true)) {
            $where[] = 'v.device_type = ?';
            $params[] = $d;
        }
        if (in_array($p = (string) $r->q('part', 'all'), ['lt', 'bt', 'kt', 'lv', 'ht', 'np'], true)) {
            $where[] = 'FIND_IN_SET(?, v.parts) > 0';
            $params[] = $p;
        }

        $sql = implode(' AND ', $where);
        $per = $r->qInt('per', 20, 1, 100);
        $page = $r->qInt('page', 1, 1);
        $total = (int) Db::val("SELECT COUNT(*) FROM visits v JOIN users u ON u.id = v.user_id WHERE $sql", $params);

        $rows = Db::all(
            "SELECT v.*, u.display_name FROM visits v JOIN users u ON u.id = v.user_id
              WHERE $sql ORDER BY v.started_at DESC LIMIT $per OFFSET " . (($page - 1) * $per),
            $params,
        );

        $topPart = Db::val(
            "SELECT SUBSTRING_INDEX(parts, ',', 1) AS p FROM visits
              WHERE parts <> '' AND started_at > (NOW() - INTERVAL ? DAY)
              GROUP BY p ORDER BY COUNT(*) DESC LIMIT 1",
            [max(1, $days)],
        );

        return Response::json(
            array_map(static function (array $v): array {
                $live = $v['ended_at'] === null && strtotime((string) $v['last_seen_at']) > time() - self::LIVE;
                return [
                    'visitId' => (int) $v['id'],
                    'user' => [
                        'id' => (int) $v['user_id'],
                        'initials' => Str::initials((string) $v['display_name']),
                        'name' => (string) $v['display_name'],
                    ],
                    'date' => date('Y-m-d', strtotime((string) $v['started_at'])),
                    'start' => date('H:i', strtotime((string) $v['started_at'])),
                    'end' => $v['ended_at'] === null ? null : date('H:i', strtotime((string) $v['ended_at'])),
                    'durationMin' => (int) round(((int) $v['duration_sec']) / 60),
                    'device' => (string) $v['device_type'],
                    'os' => (string) $v['os'],
                    'parts' => array_values(array_filter(explode(',', (string) $v['parts']))),
                    'online' => $live,
                ];
            }, $rows),
            200,
            [
                'total' => $total,
                'page' => $page,
                'per' => $per,
                'kpi' => [
                    'visits' => $total,
                    'online' => $this->onlineCount(),
                    'avgMin' => (int) round(((float) Db::val(
                        "SELECT COALESCE(AVG(v.duration_sec), 0) FROM visits v JOIN users u ON u.id = v.user_id WHERE $sql",
                        $params,
                    )) / 60),
                    'topPart' => $topPart,
                ],
                'keepDays' => (int) Settings::value('privacy', 'visit_log_days', 90),
            ],
        );
    }

    /* ------------------------------------------------------------------ */

    private function onlineCount(): int
    {
        return (int) Db::val(
            'SELECT COUNT(DISTINCT user_id) FROM visits WHERE ended_at IS NULL AND last_seen_at > (NOW() - INTERVAL ? SECOND)',
            [self::LIVE],
        );
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($u === null) {
            throw HttpError::notFound('Không có người dùng này.');
        }
        return $u;
    }
}
