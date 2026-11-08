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
use Zika\Core\Str;
use Zika\Core\Validator;
use Zika\Services\QuanTra;

/** Quản trị Quán trà: tin bị báo cáo, tin gần đây, cấm chat, đóng/mở phòng. Chỉ admin. */
final class ChatController
{
    /** GET /admin/chat?tab=reported|all|muted|rooms */
    public function index(Request $r): array
    {
        $tab = (string) $r->q('tab', 'reported');
        $ra = ['open' => QuanTra::moCua(), 'counts' => [
            'reported' => (int) Db::val('SELECT COUNT(DISTINCT cr.message_id) FROM chat_reports cr JOIN chat_messages m ON m.id = cr.message_id WHERE m.deleted_at IS NULL'),
            'muted' => (int) Db::val('SELECT COUNT(*) FROM chat_mutes WHERE until IS NULL OR until > ?', [Clock::sql()]),
        ]];
        if ($tab === 'rooms') {
            $ra['rooms'] = QuanTra::cacBan(true);
            return $ra;
        }
        if ($tab === 'muted') {
            $ra['mutes'] = array_map(static fn ($x) => [
                'userId' => (int) $x['user_id'], 'name' => (string) $x['display_name'], 'email' => (string) $x['email'],
                'until' => Clock::iso($x['until']), 'reason' => (string) $x['reason'],
            ], Db::all(
                'SELECT c.*, u.display_name, u.email FROM chat_mutes c JOIN users u ON u.id = c.user_id
                  WHERE c.until IS NULL OR c.until > ? ORDER BY c.created_at DESC',
                [Clock::sql()],
            ));
            return $ra;
        }
        $chiBaoCao = $tab === 'reported';
        $rows = Db::all(
            'SELECT m.id, m.user_id, m.body, m.created_at, m.deleted_at, u.display_name, u.email, cr0.name ban,
                    (SELECT COUNT(*) FROM chat_reports cr WHERE cr.message_id = m.id) bao_cao,
                    (SELECT GROUP_CONCAT(NULLIF(cr.reason, \'\') SEPARATOR \' · \') FROM chat_reports cr WHERE cr.message_id = m.id) ly_do
               FROM chat_messages m JOIN users u ON u.id = m.user_id JOIN chat_rooms cr0 ON cr0.id = m.room_id'
            . ($chiBaoCao ? ' WHERE m.deleted_at IS NULL AND EXISTS (SELECT 1 FROM chat_reports cr WHERE cr.message_id = m.id)' : '')
            . ' ORDER BY ' . ($chiBaoCao ? 'bao_cao DESC, m.id DESC' : 'm.id DESC') . ' LIMIT 200',
        );
        $ra['messages'] = array_map(static fn ($m) => [
            'id' => (int) $m['id'], 'userId' => (int) $m['user_id'], 'name' => (string) $m['display_name'], 'email' => (string) $m['email'],
            'body' => (string) $m['body'], 'createdAt' => Clock::iso((string) $m['created_at']), 'deleted' => $m['deleted_at'] !== null,
            'reports' => (int) $m['bao_cao'], 'reasons' => (string) ($m['ly_do'] ?? ''), 'room' => (string) $m['ban'],
        ], $rows);
        return $ra;
    }

    /** PATCH /admin/chat {open} — đóng/mở Quán trà. */
    public function toggle(Request $r): array
    {
        $v = new Validator($r->body);
        $mo = $v->bool('open', true);
        $v->done();
        Settings::set('chat', ['enabled' => $mo]);
        Audit::log($r, 'chat.toggle', 'site_settings', null, ['open' => $mo]);
        return ['open' => $mo];
    }

    /** POST /admin/chat/mute {userId, minutes (bỏ trống = vĩnh viễn), reason} */
    public function mute(Request $r): Response
    {
        $v = new Validator($r->body);
        $uid = (int) $v->int('userId', 'Học viên', 1);
        $phut = $v->int('minutes', 'Thời gian cấm', 1, 525600, false);
        $lyDo = (string) ($v->strOrNull('reason', 'Lý do', 200) ?? '');
        $v->done();
        if (Db::val('SELECT 1 FROM users WHERE id = ?', [$uid]) === null) {
            throw HttpError::notFound('Không có tài khoản này.');
        }
        QuanTra::cam($uid, $phut, $lyDo, $r->userId());
        Audit::log($r, 'chat.mute', 'user', $uid, ['minutes' => $phut, 'reason' => $lyDo]);
        return Response::noContent();
    }

    /**
     * POST /admin/chat/rooms · PUT /admin/chat/rooms/{id} {name, description, icon, sortOrder, open} — thêm/sửa bàn.
     * Không xoá bàn: đóng bàn là chỉ đọc, tin cũ còn nguyên.
     */
    public function saveRoom(Request $r): Response
    {
        $id = $r->id();
        $v = new Validator($r->body);
        $ten = $v->str('name', 'Tên bàn', min: 2, max: 80);
        $moTa = $v->str('description', 'Mô tả', required: false, max: 200);
        $icon = $v->str('icon', 'Biểu tượng', required: false, max: 8) ?: '🍵';
        $thuTu = (int) ($v->int('sortOrder', 'Thứ tự', 0, 999, false) ?? 0);
        $mo = $v->bool('open', true);
        $v->done();
        $row = ['name' => $ten, 'description' => $moTa, 'icon' => $icon, 'sort_order' => $thuTu, 'is_open' => $mo ? 1 : 0];
        if ($id > 0) {
            if (Db::val('SELECT 1 FROM chat_rooms WHERE id = ?', [$id]) === null) {
                throw HttpError::notFound('Không có bàn này.');
            }
            Db::update('chat_rooms', $row, ['id' => $id]);
            Audit::log($r, 'chat.room.update', 'chat_room', $id);
            return Response::json(['id' => $id]);
        }
        // Đường dẫn bàn lấy từ tên: "Luyện thi HSK" → luyen-thi-hsk; trùng thì thêm số.
        $goc = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(Str::stripMarks($ten))), '-') ?: 'ban';
        $goc = substr($goc, 0, 34);
        $slug = $goc;
        for ($i = 2; Db::val('SELECT 1 FROM chat_rooms WHERE slug = ?', [$slug]) !== null; $i++) {
            $slug = "$goc-$i";
        }
        $id = Db::insert('chat_rooms', $row + ['slug' => $slug, 'created_at' => Clock::sql()]);
        Audit::log($r, 'chat.room.create', 'chat_room', $id);
        return Response::json(['id' => $id, 'slug' => $slug], 201);
    }

    /** DELETE /admin/chat/mute/{id} */
    public function unmute(Request $r): Response
    {
        QuanTra::boCam($r->id());
        Audit::log($r, 'chat.unmute', 'user', $r->id());
        return Response::noContent();
    }
}
