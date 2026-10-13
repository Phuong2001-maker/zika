<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\Settings;

/**
 * Việc dọn dẹp chạy mỗi ngày (02 §4.4 · cron/daily.php):
 *  1. chốt số liệu `stats_daily` của hôm qua
 *  2. gộp `affiliate_events` vào `affiliate_daily`
 *  3. xoá dữ liệu quá hạn (phiên, mẫu online, token, hàng đợi thư, nhật ký)
 *
 * Mỗi bước độc lập để một bước lỗi không chặn các bước sau (Cron::run lo việc đó).
 */
final class MaintenanceService
{
    /** Giữ sự kiện Shopee thô bao nhiêu ngày (đã gộp vào affiliate_daily). */
    private const AFFILIATE_RAW_DAYS = 30;
    private const ONLINE_SAMPLE_DAYS = 60;
    private const TOKEN_DAYS = 30;
    private const MAIL_DAYS = 30;
    private const AUDIT_DAYS = 365;
    private const LOG_DAYS = 30;

    /**
     * Chốt số liệu một ngày (mặc định hôm qua) vào `stats_daily`.
     * Chạy lại cùng một ngày cho ra cùng kết quả — ghi đè bằng REPLACE.
     */
    public static function rollupDay(?string $day = null): string
    {
        $d = $day ?? Clock::now()->modify('-1 day')->format('Y-m-d');

        $users = Db::one(
            "SELECT COUNT(*) AS n,
                    SUM(signup_method = 'google') AS g,
                    SUM(signup_method = 'email')  AS e
             FROM users WHERE DATE(created_at) = ?",
            [$d],
        ) ?? [];

        $v = Db::one(
            "SELECT COUNT(*) AS visits,
                    COUNT(DISTINCT user_id) AS active,
                    COALESCE(SUM(duration_sec), 0) AS sec,
                    COALESCE(SUM(IF(device_type = 'phone',   duration_sec, 0)), 0) AS phone,
                    COALESCE(SUM(IF(device_type = 'tablet',  duration_sec, 0)), 0) AS tablet,
                    COALESCE(SUM(IF(device_type = 'desktop', duration_sec, 0)), 0) AS desktop
             FROM visits WHERE DATE(started_at) = ?",
            [$d],
        ) ?? [];

        $lessons = (int) Db::val(
            "SELECT COUNT(*) FROM lesson_progress WHERE status = 'done' AND DATE(last_done_at) = ?",
            [$d],
        );

        $aff = Db::one(
            "SELECT SUM(kind = 'view') AS views, SUM(kind = 'click') AS clicks
             FROM affiliate_events WHERE DATE(created_at) = ?",
            [$d],
        ) ?? [];

        // Lúc đông người nhất trong ngày, lấy từ các mẫu 5 phút.
        $peak = Db::one(
            'SELECT online, TIME(ts) AS at FROM online_samples
             WHERE DATE(ts) = ? ORDER BY online DESC, ts LIMIT 1',
            [$d],
        );

        Db::exec(
            'REPLACE INTO stats_daily
               (d, new_users, new_google, new_email, active_users, visits, study_sec,
                phone_sec, tablet_sec, desktop_sec, lessons_done, aff_views, aff_clicks,
                peak_online, peak_online_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $d,
                (int) ($users['n'] ?? 0), (int) ($users['g'] ?? 0), (int) ($users['e'] ?? 0),
                (int) ($v['active'] ?? 0), (int) ($v['visits'] ?? 0), (int) ($v['sec'] ?? 0),
                (int) ($v['phone'] ?? 0), (int) ($v['tablet'] ?? 0), (int) ($v['desktop'] ?? 0),
                $lessons,
                (int) ($aff['views'] ?? 0), (int) ($aff['clicks'] ?? 0),
                (int) ($peak['online'] ?? 0), $peak['at'] ?? null,
            ],
        );
        return $d;
    }

    /**
     * Gộp sự kiện Shopee của hôm qua vào `affiliate_daily` rồi xoá sự kiện thô đã cũ.
     * @return array{days:int,removed:int}
     */
    public static function rollupAffiliate(?string $day = null): array
    {
        $d = $day ?? Clock::now()->modify('-1 day')->format('Y-m-d');
        $rows = Db::exec(
            "INSERT INTO affiliate_daily (link_id, d, views, clicks)
             SELECT link_id, DATE(created_at),
                    SUM(kind = 'view'), SUM(kind = 'click')
             FROM affiliate_events WHERE DATE(created_at) = ?
             GROUP BY link_id, DATE(created_at)
             ON DUPLICATE KEY UPDATE views = VALUES(views), clicks = VALUES(clicks)",
            [$d],
        );
        $removed = Db::exec(
            'DELETE FROM affiliate_events WHERE created_at < ?',
            [Clock::sql(Clock::now()->modify('-' . self::AFFILIATE_RAW_DAYS . ' days'))],
        );
        return ['days' => $rows, 'removed' => $removed];
    }

    /**
     * Xoá dữ liệu quá hạn. Số ngày giữ nhật ký phiên lấy từ Cài đặt → Quyền riêng tư.
     * @return array<string,int> tên bảng => số dòng đã xoá
     */
    public static function prune(): array
    {
        $visitDays = max(7, (int) Settings::value('privacy', 'visit_log_days', 90));
        $out = [];

        // Mốc cắt tính bằng Clock chứ không dùng NOW() của SQL: NOW() lấy giờ máy chủ
        // database nên bỏ qua Clock::freeze(), khiến kiểm thử phụ thuộc ngày chạy thật.
        $truoc = static fn (int $days): string => Clock::sql(Clock::now()->modify('-' . $days . ' days'));

        $out['visits'] = Db::exec('DELETE FROM visits WHERE started_at < ?', [$truoc($visitDays)]);
        $out['online_samples'] = Db::exec('DELETE FROM online_samples WHERE ts < ?', [$truoc(self::ONLINE_SAMPLE_DAYS)]);

        // Token đã dùng / hết hạn và phiên đăng nhập đã thu hồi: giữ thêm 30 ngày để tra cứu.
        $out['email_tokens'] = Db::exec(
            'DELETE FROM email_tokens WHERE expires_at < ?',
            [$truoc(self::TOKEN_DAYS)],
        );
        $out['auth_sessions'] = Db::exec(
            'DELETE FROM auth_sessions
             WHERE (revoked_at IS NOT NULL AND revoked_at < ?)
                OR expires_at < ?',
            [$truoc(self::TOKEN_DAYS), $truoc(self::TOKEN_DAYS)],
        );
        $out['admin_invites'] = Db::exec(
            'DELETE FROM admin_invites WHERE expires_at < ?',
            [$truoc(self::TOKEN_DAYS)],
        );

        // rate_limits đếm theo cửa sổ window_start; quá 1 ngày là chắc chắn không còn dùng.
        $out['rate_limits'] = Db::exec('DELETE FROM rate_limits WHERE window_start < ?', [$truoc(1)]);
        $out['mail_queue'] = Db::exec(
            "DELETE FROM mail_queue WHERE status = 'sent' AND sent_at < ?",
            [$truoc(self::MAIL_DAYS)],
        );
        $out['audit_log'] = Db::exec('DELETE FROM audit_log WHERE created_at < ?', [$truoc(self::AUDIT_DAYS)]);
        $out['log_files'] = self::pruneLogFiles();

        return $out;
    }

    /** Xoá file nhật ký cũ hơn 30 ngày trong storage/logs. */
    private static function pruneLogFiles(): int
    {
        $dir = Config::storageDir('logs');
        if (!is_dir($dir)) {
            return 0;
        }
        $cutoff = time() - self::LOG_DAYS * 86400;
        $n = 0;
        foreach (glob($dir . '/*.log') ?: [] as $f) {
            if (filemtime($f) < $cutoff && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
