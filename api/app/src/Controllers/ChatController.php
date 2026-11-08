<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Clock;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\QuanTra;

/** Quán trà phía người học: các bàn, đọc tin, gửi, trả lời, xoá tin mình, báo cáo tin xấu. Biên tập/quản trị xoá mọi tin. */
final class ChatController
{
    /** GET /chat — danh sách bàn, kèm tin mới nhất để web tính số tin chưa đọc. */
    public function rooms(Request $r): array
    {
        return [
            'rooms' => QuanTra::cacBan($r->isStaff()),
            'open' => QuanTra::moCua(),
            'mutedUntil' => QuanTra::dangBiCam($r->userId()),
        ];
    }

    /**
     * GET /chat/ban/{ban}?after=&before=&since= — tin của một bàn.
     * after: hỏi tin mới định kỳ · before: xem tin cũ hơn · since: mốc `now` của lần hỏi trước, để trả các tin vừa bị xoá.
     */
    public function index(Request $r): array
    {
        $uid = $r->userId();
        $ban = QuanTra::ban($r->param('ban'));
        $after = $r->qInt('after', 0, 0);
        $before = $r->qInt('before', 0, 0);
        $since = (string) $r->q('since', '');
        $daXoa = [];
        if ($since !== '' && ($t = date_create_immutable($since)) !== false) {
            $daXoa = QuanTra::daXoaTu((int) $ban['id'], Clock::sql($t->setTimezone(Clock::tz())));
        }
        return [
            'room' => [
                'slug' => (string) $ban['slug'], 'name' => (string) $ban['name'], 'description' => (string) $ban['description'],
                'icon' => (string) $ban['icon'], 'open' => (bool) $ban['is_open'],
            ],
            'messages' => QuanTra::tin((int) $ban['id'], $uid, $r->isStaff(), $after > 0 ? $after : null, $before > 0 ? $before : null),
            'deleted' => $daXoa,
            'now' => Clock::now()->format('Y-m-d\TH:i:sP'),
            'open' => QuanTra::moCua(),
            'mutedUntil' => QuanTra::dangBiCam($uid),
            'staff' => $r->isStaff(),
        ];
    }

    /** POST /chat/ban/{ban} {body, replyTo} */
    public function create(Request $r): Response
    {
        $ban = QuanTra::ban($r->param('ban'));
        $v = new Validator($r->body);
        $body = $v->str('body', 'Tin nhắn', max: QuanTra::DAI_TOI_DA);
        $traLoi = $v->int('replyTo', 'Tin trả lời', 1, null, false);
        $v->done();
        $id = QuanTra::gui($ban, $r->userId(), $body, $traLoi);
        return Response::json(['id' => $id], 201);
    }

    /** DELETE /chat/tin/{id} */
    public function delete(Request $r): Response
    {
        QuanTra::xoa($r->id(), $r->userId(), $r->isStaff());
        return Response::noContent();
    }

    /** POST /chat/tin/{id}/report {reason} */
    public function report(Request $r): Response
    {
        $v = new Validator($r->body);
        $lyDo = (string) ($v->strOrNull('reason', 'Lý do', 200) ?? '');
        $v->done();
        QuanTra::baoCao($r->id(), $r->userId(), $lyDo);
        return Response::noContent();
    }
}
