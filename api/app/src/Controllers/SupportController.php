<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Settings;
use Zika\Core\Validator;

/** Link Shopee phía người học: bé túi (một link) và hộp Quỹ tháng (nhiều link). */
final class SupportController
{
    private const PLACES = ['icon', 'fund'];

    /**
     * GET /support/links — công tắc tổng tắt thì không có link nào.
     * Tiền quỹ vẫn trả về vì nó thuộc phần cuộc thi, không phụ thuộc công tắc link.
     */
    public function links(Request $r): array
    {
        $icon = null;
        $fund = [];
        foreach (self::visibleLinks() as $l) {
            $id = (int) $l['id'];
            if ($icon === null && (int) $l['place_icon'] === 1) {
                $icon = ['id' => $id, 'name' => (string) $l['name'], 'url' => "/go/$id?place=icon"];
            }
            if ((int) $l['place_fund'] === 1) {
                $fund[] = ['id' => $id, 'name' => (string) $l['name'], 'url' => "/go/$id?place=fund"];
            }
        }
        return [
            'icon' => $icon,
            'fund' => $fund,
            'monthlyFund' => self::monthlyFund(),
            'clickedToday' => $r->user !== null && self::daBamHomNay($r->userId()),
        ];
    }

    /**
     * Hôm nay (theo giờ Việt Nam) tài khoản đã bấm link Shopee nào chưa, ở bé túi hay ở Quỹ tháng đều tính: bấm một lần
     * là máy đã mang mã tiếp thị, bé hiện lại trong ngày chỉ làm phiền người học. Ghi theo tài khoản ở server để đổi máy
     * hay đổi trình duyệt cũng không hiện lại.
     */
    public static function daBamHomNay(int $userId): bool
    {
        return Db::val(
            "SELECT 1 FROM affiliate_events WHERE kind = 'click' AND user_id = ? AND created_at >= ? LIMIT 1",
            [$userId, Clock::today() . ' 00:00:00'],
        ) !== null;
    }

    /**
     * Tháng ghi lúc admin lưu số tiền, nên sang tháng mới mà chưa nhập lại thì vẫn hiện đúng tháng của số đó.
     * @return array{amount:?int,month:string,updatedOn:?string}
     */
    public static function monthlyFund(): array
    {
        $f = Settings::get('fund');
        return [
            'amount' => $f['amount'] === null ? null : (int) $f['amount'],
            'month' => (string) ($f['month'] ?? Clock::now()->format('m/Y')),
            'updatedOn' => $f['updated_on'],
        ];
    }

    /** POST /support/view — ghi lượt hiển thị của các link đang hiện ở một vị trí. */
    public function view(Request $r): Response
    {
        $v = new Validator($r->body);
        $place = (string) $v->in('place', 'Vị trí', self::PLACES);
        $v->done();

        foreach (self::visibleLinks() as $l) {
            if ((int) $l["place_$place"] === 1) {
                self::logEvent((int) $l['id'], 'view', $place, $r->userId());
            }
        }
        return Response::noContent();
    }

    /**
     * GET /go/{id}?place=icon|fund — đi qua đây để đếm lượt bấm và không lộ link thật ra giao diện.
     * Link đã tắt hoặc đã xoá thì đưa về Bàn học, không để người học thấy lỗi JSON ở tab mới.
     */
    public function go(Request $r): Response
    {
        $id = $r->id();
        $link = null;
        foreach (self::visibleLinks() as $l) {
            if ((int) $l['id'] === $id) {
                $link = $l;
                break;
            }
        }
        if ($link === null) {
            return Response::redirect('/learn/flashcards');
        }

        // Route công khai không có giới hạn tần suất: chỉ tính lượt của người đã đăng nhập để khỏi bị bơm số.
        if ($r->method === 'GET' && $r->user !== null) {
            $place = in_array($r->q('place'), self::PLACES, true) ? (string) $r->q('place') : 'fund';
            self::logEvent($id, 'click', $place, $r->userId());
        }
        return Response::redirect((string) $link['url']);
    }

    /** @return list<array<string,mixed>> */
    public static function visibleLinks(): array
    {
        if (!(bool) Settings::value('support', 'enabled', false)) {
            return [];
        }
        return Db::all(
            'SELECT id, name, url, place_icon, place_fund FROM affiliate_links
              WHERE is_active = 1
              ORDER BY sort_order, id',
        );
    }

    private static function logEvent(int $linkId, string $kind, string $place, int $userId): void
    {
        Db::insert('affiliate_events', [
            'link_id' => $linkId,
            'user_id' => $userId,
            'kind' => $kind,
            'place' => $place,
            'created_at' => Clock::sql(),
        ]);
    }
}
