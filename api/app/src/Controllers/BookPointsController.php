<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Request;
use Zika\Services\BookAccess;

/** Điểm mở bài giáo trình phía người học: xem số điểm, nhận điểm hôm nay. */
final class BookPointsController
{
    /** GET /learn/points — số điểm và hôm nay đã nhận chưa. Việc nhận điểm độc lập với link Shopee. */
    public function points(Request $r): array
    {
        $uid = $r->userId();
        return [
            'diem' => BookAccess::soDiem($uid),
            'homNay' => BookAccess::daNhanHomNay($uid),
            'lanDau' => !BookAccess::daNhanLanDau($uid),
        ];
    }

    /** POST /learn/points/daily — nút nhận điểm riêng; không mở hay phụ thuộc link Shopee. */
    public function daily(Request $r): array
    {
        BookAccess::nhanDiemNgay($r->userId());
        return $this->points($r);
    }
}
