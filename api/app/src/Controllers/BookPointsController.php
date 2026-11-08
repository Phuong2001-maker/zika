<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Services\BookAccess;

/** Điểm mở bài giáo trình phía người học: xem số điểm, nhận điểm hôm nay. */
final class BookPointsController
{
    /** GET /learn/points — số điểm, hôm nay đã nhận chưa, và link để nhận (link Icon trước, rồi link Quỹ tháng). */
    public function points(Request $r): array
    {
        $uid = $r->userId();
        $links = SupportController::visibleLinks();
        $link = null;
        foreach ($links as $l) {
            if ((int) $l['place_icon'] === 1) {
                $link = '/go/' . (int) $l['id'] . '?place=icon';
                break;
            }
        }
        if ($link === null && $links !== []) {
            $link = '/go/' . (int) $links[0]['id'] . '?place=fund';
        }
        return [
            'diem' => BookAccess::soDiem($uid),
            'homNay' => BookAccess::daNhanHomNay($uid),
            'lanDau' => !BookAccess::daNhanLanDau($uid),
            'link' => $link,
        ];
    }

    /**
     * POST /learn/points/daily — chỉ dùng khi quản trị chưa gắn link Shopee nào (công tắc tắt): khi đó không có gì để
     * bấm, nút "Nhận điểm hôm nay" cộng thẳng. Có link thì điểm chỉ cộng qua /go, không lách được bằng gọi API.
     */
    public function daily(Request $r): array
    {
        if (SupportController::visibleLinks() !== []) {
            throw HttpError::forbidden('Bấm bé túi hoặc link Shopee để nhận điểm hôm nay.');
        }
        BookAccess::nhanDiemNgay($r->userId());
        return $this->points($r);
    }
}
