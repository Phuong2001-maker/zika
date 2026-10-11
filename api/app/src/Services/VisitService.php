<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Json;
use Zika\Core\Request;

/**
 * Phiên truy cập (03 §5.1): mở/kéo dài/đóng theo heartbeat 120 giây, đóng khi quá 300 giây im lặng.
 */
final class VisitService
{
    public const IDLE_SEC = 300;
    public const PARTS = ['lt', 'bt', 'kt', 'lv', 'ht', 'np'];

    /**
     * @param array<string,mixed>|null $activity { part, screen, level?, lessonId?, lessonNo?, mockId? }
     * @return int id phiên đang mở
     */
    public static function ping(Request $r, ?array $activity): int
    {
        $user = $r->requireUser();
        $session = $r->session;
        $now = Clock::now();
        $nowSql = Clock::sql($now);

        return Db::tx(function () use ($user, $session, $activity, $now, $nowSql): int {
            $visit = Db::one(
                'SELECT * FROM visits WHERE user_id = ? AND auth_session_id <=> ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [(int) $user['id'], $session['id'] ?? null],
            );
            $gap = $visit === null ? PHP_INT_MAX : $now->getTimestamp() - Clock::ts((string) $visit['last_seen_at']);

            if ($visit === null || $gap > self::IDLE_SEC) {
                if ($visit !== null) {
                    Db::exec('UPDATE visits SET ended_at = last_seen_at WHERE id = ?', [$visit['id']]);
                }
                $visitId = Db::insert('visits', [
                    'user_id' => (int) $user['id'],
                    'auth_session_id' => $session['id'] ?? null,
                    'started_at' => $nowSql,
                    'last_seen_at' => $nowSql,
                    'device_type' => $session['device_type'] ?? 'desktop',
                    'os' => $session['os'] ?? '',
                    'browser' => $session['browser'] ?? '',
                    'stats' => Json::encode(new \stdClass()),
                ]);
                Db::exec('UPDATE users SET visits_count = visits_count + 1 WHERE id = ?', [(int) $user['id']]);
                $delta = 0;
            } else {
                $visitId = (int) $visit['id'];
                $delta = max(0, min($gap, self::IDLE_SEC));
                Db::exec(
                    'UPDATE visits SET duration_sec = duration_sec + ?, last_seen_at = ? WHERE id = ?',
                    [$delta, $nowSql, $visitId],
                );
            }

            if ($activity !== null) {
                $part = in_array($activity['part'] ?? null, self::PARTS, true) ? $activity['part'] : null;
                Db::exec(
                    "UPDATE visits SET activity = ?, parts = IF(? IS NULL OR FIND_IN_SET(?, parts), parts, CONCAT_WS(',', NULLIF(parts, ''), ?)) WHERE id = ?",
                    [mb_substr(self::activityText($activity), 0, 160), $part, $part, $part, $visitId],
                );
            }

            Db::exec(
                'UPDATE users SET total_study_sec = total_study_sec + ?, last_seen_at = ?, last_device = ? WHERE id = ?',
                [$delta, $nowSql, $session['device_type'] ?? 'desktop', (int) $user['id']],
            );
            return $visitId;
        });
    }

    /** Rời trang (sendBeacon): cộng nốt thời gian rồi đóng phiên. */
    public static function leave(Request $r): void
    {
        $visitId = self::ping($r, null);
        Db::exec('UPDATE visits SET ended_at = last_seen_at WHERE id = ?', [$visitId]);
    }

    /** Cron 5 phút: đóng các phiên quá 300 giây không có heartbeat. */
    public static function closeStale(): int
    {
        return Db::exec('UPDATE visits SET ended_at = last_seen_at WHERE ended_at IS NULL AND last_seen_at < NOW() - INTERVAL ? SECOND', [self::IDLE_SEC]);
    }

    /**
     * Ghi số liệu tóm tắt vào phiên đang mở của người dùng (03 §4.3).
     * @param callable(array<string,mixed>):array<string,mixed> $mutate nhận stats hiện tại, trả stats mới
     */
    public static function addStat(int $userId, callable $mutate): void
    {
        $visit = Db::one('SELECT id, stats FROM visits WHERE user_id = ? AND ended_at IS NULL ORDER BY last_seen_at DESC LIMIT 1', [$userId]);
        if ($visit === null) {
            return;
        }
        $stats = Json::decode(is_string($visit['stats']) ? $visit['stats'] : null, []);
        $stats = $mutate(is_array($stats) ? $stats : []);
        Db::exec('UPDATE visits SET stats = ? WHERE id = ?', [Json::encode($stats === [] ? new \stdClass() : $stats), $visit['id']]);
    }

    /** Câu hoạt động hiển thị cho admin (03 §5.1). @param array<string,mixed> $a */
    public static function activityText(array $a): string
    {
        $part = (string) ($a['part'] ?? '');
        $screen = (string) ($a['screen'] ?? '');
        $level = isset($a['level']) ? (int) $a['level'] : null;
        $no = isset($a['lessonNo']) ? (int) $a['lessonNo'] : null;
        $where = $level !== null ? 'HSK ' . $level . ($no !== null ? ' · Bài ' . $no : '') : '';

        if ($screen === 'account') {
            return 'Ở trang Tài khoản';
        }
        if ($screen === 'admin') {
            return 'Ở trang quản trị';
        }
        if ($screen === 'home') {
            return match ($part) {
                'bt' => 'Ở Bài tập', 'kt' => 'Ở Kiểm tra', 'lv' => 'Ở Luyện viết',
                'ht' => 'Ở Hội thoại', 'np' => 'Ở Ngữ pháp', default => 'Ở Bàn học',
            };
        }
        if ($screen === 'level') {
            return 'Đang chọn bài' . ($level !== null ? ' HSK ' . $level : '');
        }
        $review = $screen === 'review';
        $lv = $level !== null ? 'HSK ' . $level : '';
        return match ($part) {
            'lt' => $review ? "Đang lật Tổng hợp $lv" : "Đang lật $where",
            'bt' => $review ? "Đang làm bài tập tổng hợp $lv" : "Đang làm bài tập $where",
            'kt' => isset($a['mockId']) || $review ? "Đang thi thử $lv" : "Đang kiểm tra $where",
            'lv' => $review ? "Đang luyện viết cả cấp $lv" : "Đang luyện viết $where",
            'ht' => $review ? "Đang nghe hội thoại cả cấp $lv" : "Đang đọc hội thoại $where",
            'np' => $review ? "Đang xem tổng hợp ngữ pháp $lv" : "Đang xem ngữ pháp $where",
            default => 'Đang học',
        };
    }
}
