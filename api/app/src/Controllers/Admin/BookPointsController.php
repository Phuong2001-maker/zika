<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Settings;
use Zika\Core\Validator;
use Zika\Services\BookAccess;
use Zika\Services\Curricula;

/** Quản trị điểm mở bài giáo trình: chế độ test, tra sổ điểm từng học viên, cộng/trừ tay. Chỉ admin. */
final class BookPointsController
{
    /** GET /admin/book-points — cài đặt chung và tên từng quyển (để đọc sổ điểm "Mở Bài 5 · Hán ngữ 1"). */
    public function settings(Request $r): array
    {
        return [
            'testMode' => BookAccess::cheDoThu(),
            'books' => array_map(static fn ($s) => ['ma' => $s['ma'], 'ten' => $s['ten']], BookAccess::sachCuaBo(null)),
        ];
    }

    /** PUT /admin/book-points/test-mode {enabled} — bật/tắt chế độ test điểm (mỗi lần bấm bé túi đều +1). */
    public function testMode(Request $r): array
    {
        $v = new Validator($r->body);
        $on = $v->bool('enabled');
        $v->done();
        Settings::set('points', ['test_mode' => $on]);
        Audit::log($r, 'book_points.test_mode', 'site_settings', null, ['enabled' => $on]);
        return ['testMode' => $on];
    }

    /**
     * GET /admin/book-points/users?q=&sort=diem|moi&page= — mọi học viên kèm số điểm, giáo trình, bài đã mở, lần nhận điểm
     * gần nhất: quản trị nhìn một bảng là thấy ai đang có bao nhiêu điểm, sai thì cộng/trừ tay ngay (người dùng yêu cầu 08/11).
     */
    public function users(Request $r): Response
    {
        $q = trim((string) $r->q('q', ''));
        $per = 50;
        $page = $r->qInt('page', 1, 1);
        $where = "u.role = 'learner'";
        $args = [];
        if ($q !== '') {
            $where .= ' AND (u.email LIKE ? OR u.display_name LIKE ? OR u.full_name LIKE ?)';
            array_push($args, "%$q%", "%$q%", "%$q%");
        }
        $tong = (int) Db::val("SELECT COUNT(*) FROM users u WHERE $where", $args);
        $thuTu = (string) $r->q('sort', 'diem') === 'moi' ? 'lan_cuoi IS NULL, lan_cuoi DESC, u.id DESC' : 'diem DESC, u.id DESC';
        $rows = Db::all(
            "SELECT u.id, u.email, u.display_name, c.name giao_trinh,
                    COALESCE(p.diem, 0) diem, p.lan_cuoi,
                    (SELECT COUNT(*) FROM book_unlocks b WHERE b.user_id = u.id) bai_mo
               FROM users u
               LEFT JOIN curricula c ON c.id = COALESCE(u.curriculum_id, ?)
               LEFT JOIN (SELECT user_id, SUM(delta) diem,
                                 MAX(CASE WHEN kind IN ('first', 'daily') THEN created_at END) lan_cuoi
                            FROM book_points GROUP BY user_id) p ON p.user_id = u.id
              WHERE $where
              ORDER BY $thuTu
              LIMIT $per OFFSET " . (($page - 1) * $per),
            [Curricula::defaultId(), ...$args],
        );
        return Response::json(array_map(static fn ($u) => [
            'id' => (int) $u['id'], 'email' => (string) $u['email'], 'name' => (string) $u['display_name'],
            'curriculum' => (string) ($u['giao_trinh'] ?? ''), 'diem' => (int) $u['diem'], 'baiMo' => (int) $u['bai_mo'],
            'lastClaim' => Clock::iso($u['lan_cuoi']),
        ], $rows), 200, ['total' => $tong, 'page' => $page, 'per' => $per]);
    }

    /** GET /admin/book-points/user?q=email — tìm học viên, xem số điểm, bài đã mở và sổ điểm. */
    public function user(Request $r): array
    {
        $q = trim((string) $r->q('q', ''));
        if ($q === '') {
            return ['user' => null];
        }
        $u = Db::one('SELECT id, email, display_name, role FROM users WHERE email = ? OR id = ? LIMIT 1', [$q, ctype_digit($q) ? (int) $q : 0])
            ?? Db::one('SELECT id, email, display_name, role FROM users WHERE email LIKE ? ORDER BY id LIMIT 1', ["%$q%"]);
        if ($u === null) {
            return ['user' => null];
        }
        $uid = (int) $u['id'];
        return [
            'user' => ['id' => $uid, 'email' => (string) $u['email'], 'name' => (string) $u['display_name'], 'role' => (string) $u['role']],
            'diem' => BookAccess::soDiem($uid),
            'unlocks' => array_map(static fn ($x) => ['ma' => (string) $x['ma'], 'bai' => (int) $x['bai'], 'at' => Clock::iso((string) $x['created_at'])],
                Db::all('SELECT ma, bai, created_at FROM book_unlocks WHERE user_id = ? ORDER BY created_at DESC, ma, bai', [$uid])),
            'ledger' => array_map(static fn ($x) => [
                'delta' => (int) $x['delta'], 'kind' => (string) $x['kind'], 'khoa' => (string) $x['khoa'],
                'note' => $x['note'], 'at' => Clock::iso((string) $x['created_at']),
            ], Db::all('SELECT delta, kind, khoa, note, created_at FROM book_points WHERE user_id = ? ORDER BY id DESC LIMIT 60', [$uid])),
        ];
    }

    /** POST /admin/book-points/adjust {userId, delta, note} */
    public function adjust(Request $r): array
    {
        $v = new Validator($r->body);
        $uid = (int) $v->int('userId', 'Học viên', 1);
        $delta = (int) $v->int('delta', 'Số điểm', -100, 100);
        $note = (string) ($v->strOrNull('note', 'Ghi chú', 200) ?? '');
        if ($delta === 0) {
            $v->error('delta', 'Nhập số điểm khác 0.');
        }
        $v->done();
        if (Db::val('SELECT 1 FROM users WHERE id = ?', [$uid]) === null) {
            throw HttpError::notFound('Không có tài khoản này.');
        }
        BookAccess::congTay($uid, $delta, $note, $r->userId());
        Audit::log($r, 'book_points.adjust', 'user', $uid, ['delta' => $delta, 'note' => $note]);
        return ['diem' => BookAccess::soDiem($uid)];
    }
}
