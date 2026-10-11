<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Db;

/** Số liệu thống kê gom định kỳ (03 §3.4, §5.3). */
final class StatsService
{
    /** “Đang online” = có heartbeat trong 180 giây gần nhất (01 §6). */
    public const ONLINE_SEC = 180;

    public static function onlineNow(): int
    {
        return (int) Db::val(
            "SELECT COUNT(*) FROM users WHERE last_seen_at >= NOW() - INTERVAL ? SECOND AND status = 'active'",
            [self::ONLINE_SEC],
        );
    }

    /** Cron 5 phút: ghi số người online, mốc thời gian làm tròn xuống 5 phút. Chạy lại trong cùng mốc thì ghi đè. */
    public static function sampleOnline(): int
    {
        $n = self::onlineNow();
        Db::exec(
            'INSERT INTO online_samples (ts, online) VALUES (FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP() / 300) * 300), ?)
             ON DUPLICATE KEY UPDATE online = GREATEST(online, VALUES(online))',
            [$n],
        );
        return $n;
    }
}
