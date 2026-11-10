<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Controllers\ContestController;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Request;
use Zika\Services\TopHoc;
use Zika\Tests\DbTestCase;

/** Top chăm học tháng lấy số thật (thay 10 tên minh hoạ viết cứng ở giao diện trước 08/11/2026). */
final class TopHocTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Clock::freeze(new DateTimeImmutable('2026-11-20 10:00:00', Clock::tz()));
    }

    private function hoc(int $uid, string $luc, int $giay): void
    {
        Db::insert('visits', [
            'user_id' => $uid, 'started_at' => $luc, 'last_seen_at' => $luc, 'ended_at' => $luc,
            'duration_sec' => $giay, 'device_type' => 'desktop',
        ]);
    }

    private function xong(int $uid, int $lesson, string $part, string $luc): void
    {
        Db::insert('lesson_progress', [
            'user_id' => $uid, 'lesson_id' => $lesson, 'part' => $part, 'status' => 'done',
            'started_at' => $luc, 'first_done_at' => $luc, 'updated_at' => $luc,
        ]);
    }

    public function testXepTheoPhutRoiPhanBaiXongChiTinhHocVienTrongThang(): void
    {
        $bai = Db::insert('lessons', ['level_id' => 1, 'lesson_no' => 1, 'title' => 'B1', 'status' => 'published',
            'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00']);
        $nhieu = $this->makeUser(['display_name' => 'Học nhiều']);
        $bangPhutItBai = $this->makeUser(['display_name' => 'Bằng phút ít bài']);
        $bangPhutNhieuBai = $this->makeUser(['display_name' => 'Bằng phút nhiều bài']);
        $thangTruoc = $this->makeUser(['display_name' => 'Tháng trước']);
        $chuaDuPhut = $this->makeUser(['display_name' => 'Mở rồi đóng']);
        $admin = $this->makeUser(['display_name' => 'Quản trị', 'role' => 'admin']);
        $khoa = $this->makeUser(['display_name' => 'Bị khoá', 'status' => 'locked']);

        $this->hoc($nhieu, '2026-11-02 08:00:00', 3000);
        $this->hoc($nhieu, '2026-11-19 08:00:00', 600);
        $this->hoc($bangPhutItBai, '2026-11-05 08:00:00', 1230);
        $this->hoc($bangPhutNhieuBai, '2026-11-06 08:00:00', 1210);
        $this->xong($bangPhutNhieuBai, $bai, 'lt', '2026-11-06 08:10:00');
        $this->xong($bangPhutNhieuBai, $bai, 'ht', '2026-11-06 08:15:00');
        $this->xong($bangPhutItBai, $bai, 'lt', '2026-10-20 08:00:00');    // xong từ tháng trước: không tính
        Db::exec('INSERT INTO study_days (user_id, day) VALUES (?, ?), (?, ?), (?, ?)', [
            $nhieu, '2026-11-02', $nhieu, '2026-11-19', $nhieu, '2026-10-31',
        ]);
        $this->hoc($thangTruoc, '2026-10-31 20:00:00', 9000);
        $this->hoc($chuaDuPhut, '2026-11-10 08:00:00', 59);
        $this->hoc($admin, '2026-11-10 08:00:00', 9000);
        $this->hoc($khoa, '2026-11-10 08:00:00', 9000);

        $bang = TopHoc::thang($bangPhutItBai);

        self::assertSame('11/2026', $bang['month']);
        self::assertSame(['Học nhiều', 'Bằng phút nhiều bài', 'Bằng phút ít bài'], array_column($bang['rows'], 'name'));
        self::assertSame([60, 20, 20], array_column($bang['rows'], 'minutes'));
        self::assertSame([2, 0, 0], array_column($bang['rows'], 'days'));
        self::assertSame([0, 2, 0], array_column($bang['rows'], 'done'));
        self::assertSame(3, $bang['count']);
        self::assertSame(3, $bang['me']['rank']);
        self::assertTrue($bang['rows'][2]['isMe']);
    }

    public function testNgoaiTopVanBietHangCuaMinhVaChuaHocThiKhongCoTen(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->hoc($this->makeUser(), '2026-11-03 08:00:00', 6000 - $i * 60);
        }
        $toi = $this->makeUser();
        $this->hoc($toi, '2026-11-04 08:00:00', 120);
        $chuaHoc = $this->makeUser();

        $bang = TopHoc::thang($toi, 2);
        self::assertCount(2, $bang['rows']);
        self::assertSame(4, $bang['me']['rank']);

        $r = new Request('GET', '/contest/study-top');
        $r->user = ['id' => $chuaHoc, 'role' => 'learner'];
        $cuaNguoiChuaHoc = (new ContestController())->studyTop($r);
        self::assertNull($cuaNguoiChuaHoc['me']);
        self::assertSame(4, $cuaNguoiChuaHoc['count']);
    }

    public function testNguoiMinhHoaHienCungNguoiThatVaTuanTheoCongTac(): void
    {
        $that = $this->makeUser(['display_name' => 'Người học thật']);
        $this->hoc($that, '2026-11-04 08:00:00', 600);
        Db::insert('demo_learners', [
            'name' => 'Người học hiển thị', 'activity_level' => 'vua', 'minutes' => 120,
            'study_days' => 5, 'completed_parts' => 4, 'is_visible' => 1,
            'is_registered' => 0, 'show_contest' => 0, 'auto_enabled' => 0,
            'stats_month' => '2026-11', 'created_at' => Clock::sql(), 'updated_at' => Clock::sql(),
        ]);
        Db::insert('demo_learners', [
            'name' => 'Người học đang ẩn', 'activity_level' => 'vua', 'minutes' => 500,
            'study_days' => 9, 'completed_parts' => 8, 'is_visible' => 0,
            'is_registered' => 0, 'show_contest' => 0, 'auto_enabled' => 0,
            'stats_month' => '2026-11', 'created_at' => Clock::sql(), 'updated_at' => Clock::sql(),
        ]);

        $bang = TopHoc::thang($that);

        self::assertSame(['Người học hiển thị', 'Người học thật'], array_column($bang['rows'], 'name'));
        self::assertTrue($bang['rows'][0]['isDemo']);
        self::assertFalse($bang['rows'][1]['isDemo']);
        self::assertSame(2, $bang['count']);
        self::assertSame(2, $bang['me']['rank']);
    }
}
