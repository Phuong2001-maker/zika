<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Db;
use Zika\Core\Request;
use Zika\Services\TestService;

/**
 * Thi thử HSK 3.0 phía người học (migration 027). Đề không gắn giáo trình: học viên học bộ nào cũng thấy cùng kho đề,
 * nên không lọc theo curriculum hay cây Ẩn/hiện nội dung, chỉ theo quyền `learn.hsk30` và trạng thái Công khai của đề.
 */
final class Hsk30Controller
{
    /** GET /learn/hsk30 — đề đã công khai, gom theo cấp, kèm điểm thi thật tốt nhất và lượt đang dở của người này. */
    public function index(Request $r): array
    {
        $uid = $r->userId();
        $exams = Db::all(
            "SELECT m.id, m.level_id, m.title, m.duration_sec,
                    SUM(q.section = 'listening') AS nghe, SUM(q.section = 'reading') AS doc,
                    SUM(q.section = 'writing') AS viet, SUM(q.section = 'speaking') AS noi, COUNT(q.id) AS so_cau
               FROM mock_tests m LEFT JOIN questions q ON q.mock_test_id = m.id
              WHERE m.kind = 'hsk30' AND m.status = 'published'
              GROUP BY m.id ORDER BY m.level_id, m.sort_order, m.id",
        );
        $ids = array_map(static fn (array $m): int => (int) $m['id'], $exams);
        $best = [];
        $open = [];
        if ($ids !== []) {
            $in = Db::in($ids);
            foreach (Db::all(
                "SELECT mock_test_id, MAX(CASE WHEN max_score > 0 THEN score / max_score END) AS ti_le, COUNT(*) AS lan
                   FROM test_attempts WHERE user_id = ? AND mode = 'exam' AND submitted_at IS NOT NULL AND mock_test_id IN ($in)
                  GROUP BY mock_test_id",
                [$uid, ...$ids],
            ) as $b) {
                $best[(int) $b['mock_test_id']] = ['percent' => $b['ti_le'] === null ? null : (int) round((float) $b['ti_le'] * 100), 'attempts' => (int) $b['lan']];
            }
            foreach (Db::all(
                "SELECT mock_test_id, mode, deadline_at FROM test_attempts
                  WHERE user_id = ? AND submitted_at IS NULL AND mock_test_id IN ($in)",
                [$uid, ...$ids],
            ) as $o) {
                // Lượt đã quá hạn sẽ được chấm khi mở lại, không còn là "làm tiếp".
                if (strtotime((string) $o['deadline_at']) + TestService::GRACE_SEC > \Zika\Core\Clock::now()->getTimestamp()) {
                    $open[(int) $o['mock_test_id']][(string) $o['mode']] = true;
                }
            }
        }

        $levels = [];
        foreach ($exams as $m) {
            $id = (int) $m['id'];
            $levels[(int) $m['level_id']][] = [
                'id' => $id,
                'title' => (string) $m['title'],
                'durationSec' => (int) $m['duration_sec'],
                'questionCount' => (int) $m['so_cau'],
                'sections' => [
                    'listening' => (int) $m['nghe'], 'reading' => (int) $m['doc'],
                    'writing' => (int) $m['viet'], 'speaking' => (int) $m['noi'],
                ],
                'best' => $best[$id] ?? null,
                'open' => ['exam' => isset($open[$id]['exam']), 'practice' => isset($open[$id]['practice'])],
            ];
        }
        $out = [];
        foreach ($levels as $level => $list) {
            $out[] = ['level' => $level, 'exams' => $list];
        }
        return ['levels' => $out];
    }
}
