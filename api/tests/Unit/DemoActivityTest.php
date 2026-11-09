<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Controllers\Admin\DemoLearnerController;
use Zika\Core\{Clock, Db, Request, Router};
use Zika\Services\DemoActivity;
use Zika\Tests\DbTestCase;

final class DemoActivityTest extends DbTestCase
{
    private function at(string $s): void { Clock::freeze(new DateTimeImmutable($s, Clock::tz())); }

    private function body(): array
    {
        return ['ten' => 'Người tự động', 'muc' => 'cham', 'phut' => 0, 'ngay' => 0, 'bai' => 0, 'hien' => true, 'dangKy' => false, 'thi' => false, 'tuDong' => false];
    }

    private function save(array $body, int $id = 0): int
    {
        $r = new Request('POST', '/admin/demo-learners', body: $body);
        $r->params = $id ? ['id' => (string) $id] : [];
        return (new DemoLearnerController())->saveLearner($r)['id'];
    }

    private function active(): array
    {
        $this->at('2026-11-01 00:00:00');
        $id = $this->save($this->body());
        for ($d = 2; $d <= 8; $d++) {
            $day = '2026-11-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $plan = DemoActivity::plan($id, $day, 'cham');
            if ($plan['target'] > 0) {
                $this->at($day . ' 00:00:00');
                $this->save(array_replace($this->body(), ['tuDong' => true]), $id);
                return [$id, $day, $plan];
            }
        }
        self::fail('Phải có ngày học trong tuần.');
    }

    private function during(string $day, array $plan, int $elapsed): void
    {
        Clock::freeze((new DateTimeImmutable($day, Clock::tz()))->modify('+' . ($plan['sessions'][0]['at'] + $elapsed) . ' minutes'));
    }

    public function testLichDungKhoangCoNgayNghiVaOnDinh(): void
    {
        foreach (['it' => [5, 15, 3, 4], 'vua' => [15, 35, 4, 5], 'cham' => [30, 60, 5, 6]] as $level => [$min, $max, $daysMin, $daysMax]) {
            $active = 0;
            for ($d = 2; $d <= 8; $d++) {
                $day = sprintf('2026-11-%02d', $d);
                $p = DemoActivity::plan(42, $day, $level);
                self::assertSame($p, DemoActivity::plan(42, $day, $level));
                if (!$p['target']) { self::assertSame([], $p['sessions']); continue; }
                $active++;
                self::assertGreaterThanOrEqual($min, $p['target']);
                self::assertLessThanOrEqual($max, $p['target']);
                self::assertSame($p['target'], array_sum(array_column($p['sessions'], 'minutes')));
                self::assertLessThan($p['sessions'][1]['at'], $p['sessions'][0]['at'] + $p['sessions'][0]['minutes']);
            }
            self::assertGreaterThanOrEqual($daysMin, $active);
            self::assertLessThanOrEqual($daysMax, $active);
        }
    }

    public function testCongDanKhongTrungVaPhanHoanThanhTheoPhut(): void
    {
        [$id, $day, $plan] = $this->active();
        $this->during($day, $plan, 3);
        $n = DemoActivity::sync($id);
        self::assertSame(3, $n['minutes']);
        self::assertSame(1, $n['study_days']);
        DemoActivity::tick();
        self::assertSame(3, DemoActivity::sync($id)['minutes']);
        $this->at($day . ' 23:59:00');
        $n = DemoActivity::sync($id);
        self::assertSame($plan['target'], $n['minutes']);
        self::assertSame(intdiv($plan['target'], 25), $n['completed_parts']);
        self::assertSame(1, $n['study_days']);
        self::assertSame($plan['target'], DemoActivity::sync($id)['minutes']);
    }

    public function testTatBatKhongCongBuThoiGianTat(): void
    {
        [$id, $day, $plan] = $this->active();
        $this->during($day, $plan, 3);
        DemoActivity::sync($id);
        $body = array_replace($this->body(), ['phut' => 3, 'ngay' => 1]);
        $this->save($body, $id);
        $this->during($day, $plan, 6);
        self::assertSame(3, DemoActivity::sync($id)['minutes']);
        $this->save(array_replace($body, ['tuDong' => true]), $id);
        $this->during($day, $plan, 8);
        self::assertSame(5, DemoActivity::sync($id)['minutes']);
    }

    public function testThangMoiVeKhongVaGiuSoLieuThangTruoc(): void
    {
        $this->at('2026-11-30 23:59:00');
        $id = $this->save(array_replace($this->body(), ['phut' => 120, 'ngay' => 5, 'bai' => 8, 'tuDong' => true]));
        $this->at('2026-12-01 00:01:00');
        $n = DemoActivity::sync($id);
        self::assertSame([0, 0, 0], [$n['minutes'], $n['study_days'], $n['completed_parts']]);
        $h = DemoActivity::history($id);
        self::assertSame('2026-12', $h['months'][0]['month']);
        self::assertSame(['month' => '2026-11', 'minutes' => 120, 'days' => 5, 'parts' => 8], $h['months'][1]);
    }

    public function testMoFormCuKhongGhiDePhutTuTangVaVanChinhTayDuoc(): void
    {
        [$id, $day, $plan] = $this->active();
        $this->during($day, $plan, 3);
        $this->save(array_replace($this->body(), ['ten' => 'Đổi tên', 'tuDong' => true, 'goc' => ['phut' => 0, 'ngay' => 0, 'bai' => 0]]), $id);
        self::assertSame(3, DemoActivity::sync($id)['minutes']);
        $this->save(array_replace($this->body(), ['phut' => 50, 'ngay' => 1, 'tuDong' => true, 'goc' => ['phut' => 3, 'ngay' => 1, 'bai' => 0]]), $id);
        self::assertSame(50, DemoActivity::sync($id)['minutes']);
        $this->during($day, $plan, 5);
        self::assertSame(52, DemoActivity::sync($id)['minutes']);
    }

    public function testBuLichKhiTacVuChayTreVaNgayNghiKhongCong(): void
    {
        $this->at('2026-11-02 00:00:00');
        $id = $this->save(array_replace($this->body(), ['tuDong' => true]));
        $total = 0;
        $days = 0;
        for ($d = 2; $d <= 8; $d++) {
            $p = DemoActivity::plan($id, sprintf('2026-11-%02d', $d), 'cham');
            $total += $p['target'];
            $days += $p['target'] > 0 ? 1 : 0;
        }
        $this->at('2026-11-08 23:59:00');
        $n = DemoActivity::sync($id);
        self::assertSame($total, $n['minutes']);
        self::assertSame($days, $n['study_days']);
        self::assertSame(intdiv($total, 25), $n['completed_parts']);
        self::assertSame(7, (int) Db::val('SELECT COUNT(*) FROM demo_activity_days WHERE learner_id = ?', [$id]));
        self::assertSame($total, DemoActivity::sync($id)['minutes']);
    }

    public function testDoiNhipKhongDoiLichNgayDaTaoVaXoaSachLichSu(): void
    {
        [$id, $day, $plan] = $this->active();
        DemoActivity::sync($id);
        $this->save(array_replace($this->body(), ['muc' => 'it', 'tuDong' => true]), $id);
        $h = DemoActivity::history($id);
        $today = array_values(array_filter($h['week'], static fn ($d) => $d['day'] === $day))[0];
        self::assertSame($plan['target'], $today['target']);
        self::assertSame('A', Router::match('GET', '/admin/demo-learners/1/activity')['auth']);
        Db::exec('DELETE FROM demo_learners WHERE id = ?', [$id]);
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM demo_activity_days'));
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM demo_activity_months'));
    }
}
