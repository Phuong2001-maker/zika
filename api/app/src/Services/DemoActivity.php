<?php
declare(strict_types=1);

namespace Zika\Services;

use DateTimeImmutable;
use Zika\Core\{Clock, Db, HttpError, Json};

/** Chỉ cập nhật các bảng demo_*. Khóa từng người để cron và lượt tải trang không cộng trùng. */
final class DemoActivity
{
    /**
     * Dữ liệu dùng để làm đầy các bảng cộng đồng. Vẫn tách khỏi users, lượt thi và phần thưởng thật.
     * @return list<array<string,mixed>>
     */
    public static function visible(?string $flag = null): array
    {
        self::tick();
        $where = ['is_visible = 1'];
        if ($flag !== null) {
            $where[] = match ($flag) {
                'registered' => 'is_registered = 1',
                'contest' => 'show_contest = 1',
                default => throw new \InvalidArgumentException('Cờ người học minh họa không hợp lệ.'),
            };
        }
        return Db::all('SELECT * FROM demo_learners WHERE ' . implode(' AND ', $where) . ' ORDER BY id');
    }

    public static function tick(): int
    {
        $count = 0;
        foreach (Db::col('SELECT id FROM demo_learners ORDER BY id') as $id) {
            self::sync((int) $id);
            $count++;
        }
        return $count;
    }

    public static function sync(int $id): ?array
    {
        return Db::tx(static function () use ($id) {
            $n = Db::one('SELECT * FROM demo_learners WHERE id = ? FOR UPDATE', [$id]);
            return $n === null ? null : self::syncLocked($n);
        });
    }

    /** Ghi cấu hình và chỉnh tay trong cùng khóa với tác vụ tự động. */
    public static function save(int $id, array $row, ?array $original): int
    {
        return Db::tx(static function () use ($id, $row, $original) {
            $now = Clock::sql();
            if ($id > 0) {
                $n = self::sync($id);
                if ($n === null) throw HttpError::notFound('Người học minh họa không còn tồn tại.');
                // Form chỉ đổi tên/ẩn/nhịp học: giữ phần tự cộng từ lúc mở form tới lúc lưu.
                foreach (['minutes', 'study_days', 'completed_parts'] as $key) {
                    if ($original !== null && $row[$key] === $original[$key]) $row[$key] = (int) $n[$key];
                }
                Db::update('demo_learners', $row + ['updated_at' => $now, 'auto_cursor' => $now], ['id' => $id]);
            } else {
                $id = Db::insert('demo_learners', $row + ['created_at' => $now, 'updated_at' => $now, 'auto_cursor' => $now, 'stats_month' => Clock::now()->format('Y-m')]);
            }
            self::snapshot($id, Clock::now()->format('Y-m'), $row);
            return $id;
        });
    }

    private static function snapshot(int $id, string $month, array $n): void
    {
        Db::exec('INSERT INTO demo_activity_months (learner_id, month, minutes, study_days, completed_parts) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE minutes = VALUES(minutes), study_days = VALUES(study_days), completed_parts = VALUES(completed_parts)',
            [$id, $month, $n['minutes'], $n['study_days'], $n['completed_parts']]);
    }

    private static function month(int $id, string $month): array
    {
        Db::exec('INSERT IGNORE INTO demo_activity_months (learner_id, month) VALUES (?,?)', [$id, $month]);
        return Db::one('SELECT * FROM demo_activity_months WHERE learner_id = ? AND month = ?', [$id, $month]);
    }

    private static function syncLocked(array $n): array
    {
        $now = Clock::now();
        $id = (int) $n['id'];
        $cursor = $n['auto_cursor'] ? Clock::parse($n['auto_cursor']) : $now;
        if ($cursor > $now) return $n;
        self::snapshot($id, $n['stats_month'] ?? $now->format('Y-m'), $n);
        if ($n['auto_enabled']) {
            for ($day = $cursor->setTime(0, 0); $day <= $now; $day = $day->modify('+1 day')) {
                $d = $day->format('Y-m-d');
                $saved = Db::one('SELECT * FROM demo_activity_days WHERE learner_id = ? AND day = ?', [$id, $d]);
                $plan = $saved ? Json::decode($saved['plan_json']) : self::plan($id, $d, $n['activity_level']);
                if (!$saved) Db::insert('demo_activity_days', ['learner_id' => $id, 'day' => $d, 'plan_json' => Json::encode($plan)]);
                $delta = self::earned($plan, $day, $now) - self::earned($plan, $day, $cursor);
                if ($delta <= 0) continue;
                $month = $day->format('Y-m');
                $m = self::month($id, $month);
                $parts = intdiv((int) $m['auto_minutes'] + $delta, 25) - intdiv((int) $m['auto_minutes'], 25);
                Db::update('demo_activity_months', [
                    'minutes' => min(50000, (int) $m['minutes'] + $delta),
                    'study_days' => min((int) $day->format('t'), (int) $m['study_days'] + ((int) ($saved['credited_minutes'] ?? 0) === 0 ? 1 : 0)),
                    'completed_parts' => min(1000, (int) $m['completed_parts'] + $parts),
                    'auto_minutes' => (int) $m['auto_minutes'] + $delta,
                ], ['learner_id' => $id, 'month' => $month]);
                Db::exec('UPDATE demo_activity_days SET credited_minutes = credited_minutes + ? WHERE learner_id = ? AND day = ?', [$delta, $id, $d]);
            }
        }
        $current = self::month($id, $now->format('Y-m'));
        $changes = ['minutes' => (int) $current['minutes'], 'study_days' => (int) $current['study_days'],
            'completed_parts' => (int) $current['completed_parts'], 'stats_month' => $now->format('Y-m'), 'auto_cursor' => Clock::sql($now)];
        Db::update('demo_learners', $changes, ['id' => $id]);
        return array_replace($n, $changes);
    }

    /** Lịch giả lập ổn định theo người/tuần: tải lại không bốc lịch mới. */
    public static function plan(int $id, string $day, string $level): array
    {
        $date = new DateTimeImmutable($day, Clock::tz());
        $week = $date->modify('monday this week')->format('Y-m-d');
        [$min, $max, $days] = match ($level) { 'it' => [5, 15, 3], 'cham' => [30, 60, 5], default => [15, 35, 4] };
        $seed = "$id:$week:$level";
        $activeDays = $days + self::number($seed . ':count', 0, 1);
        $order = range(1, 7);
        usort($order, static fn ($a, $b) => strcmp(hash('sha256', "$seed:$a"), hash('sha256', "$seed:$b")));
        if (!in_array((int) $date->format('N'), array_slice($order, 0, $activeDays), true)) return ['level' => $level, 'target' => 0, 'sessions' => []];
        $seed = "$id:$day:$level";
        $target = self::number($seed . ':minutes', $min, $max);
        $first = self::number($seed . ':split', max(1, intdiv($target, 3)), max(1, intdiv($target * 2, 3)));
        $times = [self::number($seed . ':morning', 7 * 60, 10 * 60), self::number($seed . ':afternoon', 13 * 60, 15 * 60), self::number($seed . ':evening', 18 * 60, 21 * 60)];
        $skip = self::number($seed . ':skip', 0, 2);
        unset($times[$skip]);
        $times = array_values($times);
        return ['level' => $level, 'target' => $target, 'sessions' => [['at' => $times[0], 'minutes' => $first], ['at' => $times[1], 'minutes' => $target - $first]]];
    }

    private static function number(string $seed, int $min, int $max): int
    {
        return $min + (int) (hexdec(substr(hash('sha256', $seed), 0, 8)) % ($max - $min + 1));
    }

    private static function earned(array $plan, DateTimeImmutable $day, DateTimeImmutable $at): int
    {
        $minutes = 0;
        foreach ($plan['sessions'] as $s) {
            $start = $day->modify('+' . $s['at'] . ' minutes');
            $minutes += max(0, min($s['minutes'], (int) floor(($at->getTimestamp() - $start->getTimestamp()) / 60)));
        }
        return $minutes;
    }

    public static function history(int $id): array
    {
        return Db::tx(static function () use ($id) {
            $n = self::sync($id);
            if ($n === null) throw HttpError::notFound('Người học minh họa không còn tồn tại.');
            $week = [];
            $monday = Clock::now()->modify('monday this week')->setTime(0, 0);
            for ($i = 0; $i < 7; $i++) {
                $d = $monday->modify("+$i days")->format('Y-m-d');
                $saved = Db::one('SELECT * FROM demo_activity_days WHERE learner_id = ? AND day = ?', [$id, $d]);
                $plan = $saved ? Json::decode($saved['plan_json']) : self::plan($id, $d, $n['activity_level']);
                $week[] = ['day' => $d, 'target' => $plan['target'], 'credited' => (int) ($saved['credited_minutes'] ?? 0), 'sessions' => $plan['sessions']];
            }
            $months = array_map(static fn ($m) => ['month' => $m['month'], 'minutes' => (int) $m['minutes'], 'days' => (int) $m['study_days'], 'parts' => (int) $m['completed_parts']],
                Db::all('SELECT * FROM demo_activity_months WHERE learner_id = ? ORDER BY month DESC LIMIT 24', [$id]));
            return ['week' => $week, 'months' => $months, 'enabled' => (bool) $n['auto_enabled'], 'updatedAt' => $n['auto_cursor']];
        });
    }
}
