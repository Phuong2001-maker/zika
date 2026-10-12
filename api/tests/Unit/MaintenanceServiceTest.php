<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Settings;
use Zika\Services\MaintenanceService;
use Zika\Tests\DbTestCase;

/** Việc chạy mỗi ngày (02 §4.4): chốt stats_daily, gộp sự kiện Shopee, xoá dữ liệu quá hạn. */
final class MaintenanceServiceTest extends DbTestCase
{
    private const DAY = '2026-10-11';

    private function at(string $time): void
    {
        Clock::freeze(new DateTimeImmutable($time, Clock::tz()));
    }

    public function testRollupDayCountsUsersVisitsAndPeak(): void
    {
        $this->at(self::DAY . ' 09:00:00');
        $u1 = $this->makeUser(['signup_method' => 'email', 'created_at' => self::DAY . ' 09:00:00']);
        $u2 = $this->makeUser(['signup_method' => 'google', 'created_at' => self::DAY . ' 10:00:00']);
        // Người đăng ký hôm khác không được tính vào ngày này.
        $this->makeUser(['created_at' => '2026-10-09 10:00:00']);

        foreach ([[$u1, 'phone', 600], [$u1, 'phone', 300], [$u2, 'desktop', 1200]] as [$uid, $dev, $sec]) {
            Db::insert('visits', [
                'user_id' => $uid,
                'started_at' => self::DAY . ' 11:00:00',
                'last_seen_at' => self::DAY . ' 11:10:00',
                'ended_at' => self::DAY . ' 11:10:00',
                'duration_sec' => $sec,
                'device_type' => $dev,
            ]);
        }
        Db::exec('INSERT INTO online_samples (ts, online) VALUES (?, 3), (?, 9), (?, 5)', [
            self::DAY . ' 08:00:00', self::DAY . ' 20:00:00', self::DAY . ' 21:00:00',
        ]);

        self::assertSame(self::DAY, MaintenanceService::rollupDay(self::DAY));
        $row = Db::one('SELECT * FROM stats_daily WHERE d = ?', [self::DAY]);

        self::assertSame(2, (int) $row['new_users']);
        self::assertSame(1, (int) $row['new_email']);
        self::assertSame(1, (int) $row['new_google']);
        self::assertSame(3, (int) $row['visits']);
        self::assertSame(2, (int) $row['active_users']);
        self::assertSame(2100, (int) $row['study_sec']);
        self::assertSame(900, (int) $row['phone_sec']);
        self::assertSame(1200, (int) $row['desktop_sec']);
        self::assertSame(0, (int) $row['tablet_sec']);
        self::assertSame(9, (int) $row['peak_online']);
        self::assertSame('20:00:00', (string) $row['peak_online_at']);
    }

    public function testRollupDayIsRepeatable(): void
    {
        $this->at(self::DAY . ' 09:00:00');
        $this->makeUser(['created_at' => self::DAY . ' 09:00:00']);

        MaintenanceService::rollupDay(self::DAY);
        MaintenanceService::rollupDay(self::DAY);

        // Chạy hai lần vẫn đúng một dòng, số không bị cộng dồn.
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM stats_daily WHERE d = ?', [self::DAY]));
        self::assertSame(1, (int) Db::val('SELECT new_users FROM stats_daily WHERE d = ?', [self::DAY]));
    }

    public function testRollupAffiliateGroupsAndPrunesRawEvents(): void
    {
        $this->at(self::DAY . ' 23:00:00');
        $link = Db::insert('affiliate_links', ['name' => 'Bút luyện chữ', 'url' => 'https://shopee.vn/x']);

        foreach ([['view', self::DAY], ['view', self::DAY], ['click', self::DAY]] as [$kind, $d]) {
            Db::insert('affiliate_events', ['link_id' => $link, 'kind' => $kind, 'place' => 'desk', 'created_at' => $d . ' 12:00:00']);
        }
        // Sự kiện thô quá 30 ngày phải bị xoá.
        Db::insert('affiliate_events', ['link_id' => $link, 'kind' => 'view', 'place' => 'done', 'created_at' => '2026-08-01 12:00:00']);

        $out = MaintenanceService::rollupAffiliate(self::DAY);
        self::assertSame(1, $out['removed']);

        $row = Db::one('SELECT * FROM affiliate_daily WHERE link_id = ? AND d = ?', [$link, self::DAY]);
        self::assertSame(2, (int) $row['views']);
        self::assertSame(1, (int) $row['clicks']);
        self::assertSame(3, (int) Db::val('SELECT COUNT(*) FROM affiliate_events'));
    }

    public function testPruneRemovesOnlyExpiredRows(): void
    {
        $this->at('2026-10-12 03:00:00');
        Settings::set('privacy', ['visit_log_days' => 90]);
        $uid = $this->makeUser();

        Db::insert('visits', [
            'user_id' => $uid, 'started_at' => '2026-01-01 10:00:00', 'last_seen_at' => '2026-01-01 10:05:00',
            'ended_at' => '2026-01-01 10:05:00', 'duration_sec' => 300, 'device_type' => 'phone',
        ]);
        Db::insert('visits', [
            'user_id' => $uid, 'started_at' => '2026-10-10 10:00:00', 'last_seen_at' => '2026-10-10 10:05:00',
            'ended_at' => '2026-10-10 10:05:00', 'duration_sec' => 300, 'device_type' => 'phone',
        ]);
        Db::exec('INSERT INTO online_samples (ts, online) VALUES (?, 1), (?, 2)', ['2026-01-01 10:00:00', '2026-10-10 10:00:00']);
        Db::exec('INSERT INTO rate_limits (k, hits, window_start) VALUES (?, 1, ?), (?, 1, ?)', [
            'cu', '2026-10-01 10:00:00', 'moi', '2026-10-12 02:59:00',
        ]);

        $out = MaintenanceService::prune();

        self::assertSame(1, $out['visits']);
        self::assertSame(1, $out['online_samples']);
        self::assertSame(1, $out['rate_limits']);
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM visits'));
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM online_samples'));
        self::assertSame('moi', (string) Db::val('SELECT k FROM rate_limits'));
    }
}
