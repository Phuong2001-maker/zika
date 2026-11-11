<?php
declare(strict_types=1);

namespace Zika\Core;

/**
 * Khách học thử (chưa đăng nhập): một mã ngẫu nhiên trong cookie HttpOnly `zk_khach` gắn các lượt Kiểm tra / Thi thử
 * HSK 3.0 của khách (test_attempts.guest_key, migration 028), để tải lại trang vẫn làm tiếp và xem được kết quả.
 * DB chỉ giữ sha256 của mã: lộ bảng cũng không giả được cookie của người khác.
 */
final class Khach
{
    public const COOKIE = 'zk_khach';

    /** Cookie sống bằng thời gian MaintenanceService giữ lượt của khách. */
    public const GIU_NGAY = 30;

    /** @var array<int,string> mã vừa tạo trong request này, để gọi hai lần không sinh hai cookie */
    private static array $moi = [];

    /** Mã khách đã băm; null nếu trình duyệt chưa có mã và không được tạo mới. */
    public static function ma(Request $r, bool $tao = false): ?string
    {
        $c = $r->cookie(self::COOKIE);
        if ($c !== null && preg_match('/^[A-Za-z0-9_-]{43}$/', $c)) {
            return hash('sha256', $c);
        }
        $id = spl_object_id($r);
        if (isset(self::$moi[$id])) {
            return self::$moi[$id];
        }
        if (!$tao) {
            return null;
        }
        $c = Str::token();
        Cookie::queue(self::COOKIE, $c, time() + self::GIU_NGAY * 86400);
        return self::$moi[$id] = hash('sha256', $c);
    }
}
