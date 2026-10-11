<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Request;
use Zika\Services\StatsService;
use Zika\Services\VisitService;
use Zika\Tests\DbTestCase;

/** Phiên truy cập theo heartbeat (03 §5.1): mở, cộng dồn, mở phiên mới sau 300 giây im lặng, đóng. */
final class VisitServiceTest extends DbTestCase
{
    private function req(int $userId, int $sessionId): Request
    {
        $r = new Request('POST', '/visit/ping');
        $r->user = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        $r->session = ['id' => $sessionId, 'remember' => 1, 'device_type' => 'phone', 'os' => 'Android', 'browser' => 'Chrome'];
        return $r;
    }

    private function at(string $time): void
    {
        Clock::freeze(new DateTimeImmutable('2026-10-04 ' . $time, Clock::tz()));
    }

    public function testPingOpensExtendsAndRotatesVisits(): void
    {
        $uid = $this->makeUser();
        $r = $this->req($uid, 77);

        $this->at('20:00:00');
        $v1 = VisitService::ping($r, ['part' => 'lt', 'screen' => 'do', 'level' => 2, 'lessonNo' => 6]);
        $row = Db::one('SELECT * FROM visits WHERE id = ?', [$v1]);
        self::assertSame('Đang lật HSK 2 · Bài 6', $row['activity']);
        self::assertSame('lt', $row['parts']);
        self::assertSame('phone', $row['device_type']);

        $this->at('20:02:00'); // heartbeat 120 giây
        self::assertSame($v1, VisitService::ping($r, ['part' => 'bt', 'screen' => 'do', 'level' => 2, 'lessonNo' => 6]));
        $row = Db::one('SELECT * FROM visits WHERE id = ?', [$v1]);
        self::assertSame(120, (int) $row['duration_sec']);
        self::assertSame('lt,bt', $row['parts']);

        $this->at('20:10:00'); // im lặng 480 giây > 300 → phiên mới
        $v2 = VisitService::ping($r, null);
        self::assertNotSame($v1, $v2);
        self::assertSame('2026-10-04 20:02:00', Db::val('SELECT ended_at FROM visits WHERE id = ?', [$v1]));

        $u = Db::one('SELECT total_study_sec, visits_count, last_device FROM users WHERE id = ?', [$uid]);
        self::assertSame(120, (int) $u['total_study_sec']);
        self::assertSame(2, (int) $u['visits_count']);
        self::assertSame('phone', $u['last_device']);
    }

    public function testLeaveAddsDeltaThenCloses(): void
    {
        $uid = $this->makeUser();
        $r = $this->req($uid, 5);
        $this->at('07:10:00');
        $v = VisitService::ping($r, null);
        $this->at('07:11:30');
        VisitService::leave($r);
        $row = Db::one('SELECT duration_sec, ended_at FROM visits WHERE id = ?', [$v]);
        self::assertSame(90, (int) $row['duration_sec']);
        self::assertSame('2026-10-04 07:11:30', $row['ended_at']);
    }

    public function testCloseStaleAndOnlineCount(): void
    {
        $fresh = $this->makeUser(['last_seen_at' => Clock::sql()]);
        $this->makeUser(['last_seen_at' => Clock::sql(Clock::addSeconds(-600))]);
        $this->makeUser(['last_seen_at' => Clock::sql(), 'status' => 'locked']);
        self::assertSame(1, StatsService::onlineNow());

        Db::insert('visits', [
            'user_id' => $fresh, 'started_at' => Clock::sql(Clock::addSeconds(-900)), 'last_seen_at' => Clock::sql(Clock::addSeconds(-400)),
            'device_type' => 'desktop',
        ]);
        self::assertSame(1, VisitService::closeStale());
        self::assertSame(1, StatsService::sampleOnline());
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM online_samples'));
    }

    public function testActivityTexts(): void
    {
        self::assertSame('Ở Bàn học', VisitService::activityText(['part' => 'lt', 'screen' => 'home']));
        self::assertSame('Đang chọn bài HSK 2', VisitService::activityText(['part' => 'lt', 'screen' => 'level', 'level' => 2]));
        self::assertSame('Đang lật Tổng hợp HSK 2', VisitService::activityText(['part' => 'lt', 'screen' => 'review', 'level' => 2]));
        self::assertSame('Đang thi thử HSK 2', VisitService::activityText(['part' => 'kt', 'screen' => 'do', 'level' => 2, 'mockId' => 3]));
        self::assertSame('Ở trang Tài khoản', VisitService::activityText(['part' => null, 'screen' => 'account']));
    }
}
