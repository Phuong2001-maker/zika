<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Services\ProgressService;
use Zika\Tests\DbTestCase;

/** Khối “Hôm nay” ở Bàn học: chuỗi ngày học tính theo ngày có ghi tiến độ. */
final class TodayStreakTest extends DbTestCase
{
    /** Ghi một dòng tiến độ vào ngày cách “hôm nay” $back ngày. */
    private function hoc(int $uid, int $lessonId, int $back, string $status = 'in_progress'): void
    {
        $t = Clock::sql(Clock::now()->modify("-$back days"));
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
}
