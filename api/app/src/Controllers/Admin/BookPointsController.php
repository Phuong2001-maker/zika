<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Settings;
use Zika\Core\Validator;
use Zika\Services\BookAccess;

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
