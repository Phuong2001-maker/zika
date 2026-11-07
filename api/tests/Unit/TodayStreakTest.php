<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Controllers\ExerciseController;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Request;
use Zika\Services\ProgressService;
use Zika\Services\TestService;
use Zika\Tests\DbTestCase;

/** Khối “Hôm nay” ở Bàn học: chuỗi ngày học tính theo bảng study_days (chỉ ngày có học thật). */
final class TodayStreakTest extends DbTestCase
{
    /** Ghi một dòng tiến độ và ngày học vào ngày cách “hôm nay” $back ngày. */
    private function hoc(int $uid, int $lessonId, int $back, string $status = 'in_progress'): void
    {
        $when = Clock::now()->modify("-$back days");
        Db::exec('INSERT IGNORE INTO study_days (user_id, day) VALUES (?, ?)', [$uid, $when->format('Y-m-d')]);
        $t = Clock::sql($when);
        Db::insert('lesson_progress', [
            'user_id' => $uid, 'lesson_id' => $lessonId, 'part' => 'lt', 'status' => $status,
            'position' => 1, 'total' => 10, 'started_at' => $t, 'updated_at' => $t,
        ]);
    }

    public function testChuoiNgayVaTuan(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-10-10 09:00:00', Clock::tz()));
        $ids = [];
        for ($no = 1; $no <= 5; $no++) {
            $ids[] = Db::insert('lessons', [
                'level_id' => 1, 'lesson_no' => $no, 'title' => "Bài $no", 'status' => 'published', 'cnt_words' => 10 + $no,
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00',
            ]);
        }
        $uid = $this->makeUser();

        self::assertSame(['streak' => 0, 'week' => array_fill(0, 7, false), 'words' => 0], ProgressService::today($uid));

        // Học hôm qua, hôm kia, 3 hôm trước; nghỉ 4 hôm trước; học 5 hôm trước.
        foreach ([1, 2, 3, 5] as $k => $back) {
            $this->hoc($uid, $ids[$k], $back);
        }
        $t = ProgressService::today($uid);
        self::assertSame(3, $t['streak'], 'Hôm nay chưa học vẫn giữ chuỗi đến hôm qua');
        self::assertSame([false, true, false, true, true, true, false], $t['week']);

        $this->hoc($uid, $ids[4], 0, 'done');
        $t = ProgressService::today($uid);
        self::assertSame(4, $t['streak']);
        self::assertTrue($t['week'][6]);
        self::assertSame(15, $t['words'], 'Chỉ cộng từ của bài đã lật xong');

        // Nghỉ hai ngày liền thì chuỗi về 0.
        Clock::freeze(new DateTimeImmutable('2026-10-12 09:00:00', Clock::tz()));
        self::assertSame(0, ProgressService::today($uid)['streak']);
    }

    private function baiHienRa(): int
    {
        Db::exec('UPDATE levels SET is_visible = 1 WHERE id = 1');
        static $no = 100;
        return Db::insert('lessons', [
            'level_id' => 1, 'lesson_no' => ++$no, 'title' => 'Bài thử', 'status' => 'published', 'cnt_words' => 10, 'cnt_lv' => 3,
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00',
        ]);
    }

    public function testHocCungMotBaiHaiNgayThiChuoiLaHai(): void
    {
        $uid = $this->makeUser();
        $bai = $this->baiHienRa();

        Clock::freeze(new DateTimeImmutable('2026-10-09 09:00:00', Clock::tz()));
        ProgressService::save($uid, $bai, 'lt', 3, 10, null);
        Clock::freeze(new DateTimeImmutable('2026-10-10 09:00:00', Clock::tz()));
        ProgressService::save($uid, $bai, 'lt', 5, 10, null);

        $t = ProgressService::today($uid);
        self::assertSame(2, $t['streak']);
        self::assertSame([false, false, false, false, false, true, true], $t['week']);
    }

    public function testChuoiDaiHonSauMuoiNgayVanDemDuoc(): void
    {
        $uid = $this->makeUser();
        Clock::freeze(new DateTimeImmutable('2026-10-10 09:00:00', Clock::tz()));
        for ($b = 0; $b < 100; $b++) {
            Db::exec('INSERT INTO study_days (user_id, day) VALUES (?, ?)', [$uid, Clock::now()->modify("-$b days")->format('Y-m-d')]);
        }
        self::assertSame(100, ProgressService::today($uid)['streak']);
    }

    public function testMoTrangVietHoacBatDauBaiTapChuaTraLoiThiKhongTinhNgayHoc(): void
    {
        $uid = $this->makeUser();
        $bai = $this->baiHienRa();
        Db::insert('questions', ['part' => 'bt', 'lesson_id' => $bai, 'type' => 'pick', 'sort_order' => 1,
            'payload' => json_encode(['prompt' => 'Q', 'options' => ['a', 'b'], 'answer' => 0])]);
        Clock::freeze(new DateTimeImmutable('2026-10-10 09:00:00', Clock::tz()));

        ProgressService::save($uid, $bai, 'lv', 0, 3, null);     // mở màn Luyện viết, chưa viết nét nào
        $r = new Request('POST', '/exercises/attempts', [], ['lessonId' => $bai, 'mode' => 'full']);
        $r->user = ['id' => $uid, 'role' => 'learner'];
        $att = json_decode((new ExerciseController())->start($r)->body, true)['data'];   // mở bài tập, chưa trả lời

        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM study_days WHERE user_id = ?', [$uid]));
        self::assertSame(0, ProgressService::today($uid)['streak']);

        // Trả lời một câu mới tính là học.
        $r = new Request('PATCH', '/exercises/attempts/' . $att['attemptId'], [], ['result' => ['qid' => $att['questionIds'][0], 'answer' => 0]]);
        $r->user = ['id' => $uid, 'role' => 'learner'];
        $r->params = ['id' => (string) $att['attemptId']];
        (new ExerciseController())->answer($r);
        self::assertSame(1, ProgressService::today($uid)['streak']);
    }

    public function testChiNopHoacLuuCauTraLoiBaiKiemTraMoiTinhNgayHoc(): void
    {
        $uid = $this->makeUser();
        $bai = $this->baiHienRa();
        Db::insert('questions', ['part' => 'kt', 'lesson_id' => $bai, 'type' => 'pick', 'sort_order' => 1,
            'payload' => json_encode(['prompt' => 'Q', 'options' => ['a', 'b'], 'answer' => 0])]);
        Db::exec('UPDATE lessons SET cnt_kt = 1 WHERE id = ?', [$bai]);
        Clock::freeze(new DateTimeImmutable('2026-10-10 09:00:00', Clock::tz()));

        $p = TestService::start($uid, $bai, null);
        $a = Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]);
        TestService::patch($a, ['flags' => []]);
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM study_days WHERE user_id = ?', [$uid]), 'Mở đề chưa trả lời không tính');

        TestService::patch($a, ['answers' => [(int) $p['questions'][0]['id'] => 0]]);
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM study_days WHERE user_id = ?', [$uid]));
    }

    public function testNopBaiBangTayTinhNgayHocCoCronChamHoThiKhong(): void
    {
        $nguoi = $this->makeUser();
        $bai = $this->baiHienRa();
        Db::insert('questions', ['part' => 'kt', 'lesson_id' => $bai, 'type' => 'pick', 'sort_order' => 1,
            'payload' => json_encode(['prompt' => 'Q', 'options' => ['a', 'b'], 'answer' => 0])]);
        Clock::freeze(new DateTimeImmutable('2026-10-10 09:00:00', Clock::tz()));
        $p = TestService::start($nguoi, $bai, null);

        Clock::freeze(new DateTimeImmutable('2026-10-12 09:00:00', Clock::tz()));
        TestService::grade($p['attemptId'], true);   // quá hạn, cron chấm hộ
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM study_days WHERE user_id = ?', [$nguoi]));

        $khac = $this->makeUser();
        $q = TestService::start($khac, $bai, null);
        TestService::grade($q['attemptId'], false);
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM study_days WHERE user_id = ?', [$khac]));
    }
}
