<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;

/**
 * Top chăm học của tháng: xếp theo phút học, bằng phút thì ai học xong nhiều phần bài hơn đứng trên.
 * Phút học là thời gian heartbeat đã cộng (visits.duration_sec) — cùng nguồn với tổng giờ học ở hồ sơ, nên hai nơi
 * không nói hai số khác nhau. Chỉ học viên đang hoạt động mới lên bảng; quản trị và biên tập mở web để làm việc.
 */
final class TopHoc
{
    /** Dưới một phút là mở web rồi đóng, không tính là học. */
    private const GIAY_TOI_THIEU = 60;

    /** @return array{month:string,rows:list<array<string,mixed>>,me:?array<string,mixed>,count:int} */
    public static function thang(int $userId, int $limit = 10): array
    {
        $dau = Clock::now()->modify('first day of this month')->setTime(0, 0);
        $cuoi = $dau->modify('first day of next month');
        [$tu, $den] = [Clock::sql($dau), Clock::sql($cuoi)];
        [$ngayTu, $ngayDen] = [$dau->format('Y-m-d'), $cuoi->format('Y-m-d')];

        $all = [];
        foreach (Db::all(
            "SELECT u.id, u.display_name, t.giay,
                    (SELECT COUNT(*) FROM study_days d WHERE d.user_id = u.id AND d.day >= ? AND d.day < ?) AS ngay,
                    (SELECT COUNT(*) FROM lesson_progress p
                      WHERE p.user_id = u.id AND p.first_done_at >= ? AND p.first_done_at < ?) AS xong
               FROM (SELECT user_id, SUM(duration_sec) AS giay FROM visits
                      WHERE started_at >= ? AND started_at < ? GROUP BY user_id) t
               JOIN users u ON u.id = t.user_id
              WHERE u.role = 'learner' AND u.status = 'active' AND t.giay >= ?
              ORDER BY FLOOR(t.giay / 60) DESC, xong DESC, ngay DESC, u.id",
            [$ngayTu, $ngayDen, $tu, $den, $tu, $den, self::GIAY_TOI_THIEU],
        ) as $u) {
            $all[] = [
                'name' => (string) $u['display_name'],
                'minutes' => intdiv((int) $u['giay'], 60),
                'days' => (int) $u['ngay'],
                'done' => (int) $u['xong'],
                'isMe' => (int) $u['id'] === $userId,
                'isDemo' => false,
                'avatarUrl' => null,
                '_order' => (int) $u['id'],
            ];
        }
        foreach (DemoActivity::visible() as $n) {
            $all[] = [
                'name' => (string) $n['name'],
                'minutes' => (int) $n['minutes'],
                'days' => (int) $n['study_days'],
                'done' => (int) $n['completed_parts'],
                'isMe' => false,
                'isDemo' => true,
                'avatarUrl' => $n['avatar_path'],
                '_order' => 1_000_000_000 + (int) $n['id'],
            ];
        }
        usort($all, static fn (array $a, array $b): int =>
            $b['minutes'] <=> $a['minutes'] ?: $b['done'] <=> $a['done'] ?: $b['days'] <=> $a['days'] ?: $a['_order'] <=> $b['_order']);

        $rows = [];
        $me = null;
        foreach ($all as $i => $raw) {
            unset($raw['_order']);
            $row = ['rank' => $i + 1] + $raw;
            if ($row['isMe']) {
                $me = $row;
            }
            if ($i < $limit) {
                $rows[] = $row;
            }
        }
        return ['month' => $dau->format('m/Y'), 'rows' => $rows, 'me' => $me, 'count' => count($all)];
    }
}
