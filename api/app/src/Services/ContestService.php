<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Settings;

/** Trạng thái cuộc thi theo giờ, đăng ký, xếp hạng và giải thưởng. */
final class ContestService
{
    /** @param array<string,mixed> $c */
    public static function status(array $c): string
    {
        $now = Clock::sql();
        if ($now < (string) $c['starts_at']) {
            return 'upcoming';
        }
        return $now < (string) $c['ends_at'] ? 'open' : 'ended';
    }

    /** Đề nháp vẫn làm được nếu đang trong giờ của một cuộc thi. */
    public static function isOpen(int $mockTestId): bool
    {
        return self::openContest($mockTestId) !== null;
    }

    /** @return array<string,mixed>|null cuộc thi đang mở dùng đề này */
    public static function openContest(int $mockTestId): ?array
    {
        $now = Clock::sql();
        return Db::one(
            'SELECT * FROM contests WHERE mock_test_id = ? AND starts_at <= ? AND ends_at > ? ORDER BY starts_at DESC, id DESC LIMIT 1',
            [$mockTestId, $now, $now],
        );
    }

    /** @return array<string,mixed>|null cuộc gần nhất chưa kết thúc: cả hộp quỹ, đăng ký và xếp hạng đều nói về bài này */
    private static function currentRow(): ?array
    {
        return Db::one('SELECT * FROM contests WHERE ends_at > ? ORDER BY starts_at, id LIMIT 1', [Clock::sql()]);
    }

    public static function isRegistered(int $contestId, int $userId): bool
    {
        return Db::val('SELECT 1 FROM contest_registrations WHERE contest_id = ? AND user_id = ?', [$contestId, $userId]) !== null;
    }

    /** Đăng ký hoặc huỷ chỉ được làm trước giờ bắt đầu. */
    public static function setRegistered(int $contestId, int $userId, bool $on): void
    {
        $c = Db::one('SELECT * FROM contests WHERE id = ?', [$contestId]);
        if ($c === null) {
            throw HttpError::notFound('Không có bài thi này.');
        }
        if (self::status($c) !== 'upcoming') {
            throw HttpError::conflict('Bài thi đã bắt đầu nên không đăng ký hoặc huỷ được nữa.');
        }
        if ($on) {
            Db::exec(
                'INSERT IGNORE INTO contest_registrations (contest_id, user_id, created_at) VALUES (?, ?, ?)',
                [$contestId, $userId, Clock::sql()],
            );
        } else {
            Db::exec('DELETE FROM contest_registrations WHERE contest_id = ? AND user_id = ?', [$contestId, $userId]);
        }
    }

    /** @return array{contest:?array<string,mixed>,rows:list<array<string,mixed>>,count:int} */
    public static function registrants(int $userId, int $limit = 200): array
    {
        $c = self::currentRow();
        if ($c === null) {
            return ['contest' => null, 'rows' => [], 'count' => 0];
        }
        $rows = Db::all(
            'SELECT r.user_id, r.created_at, u.display_name
               FROM contest_registrations r JOIN users u ON u.id = r.user_id
              WHERE r.contest_id = ? ORDER BY r.created_at, r.user_id',
            [(int) $c['id']],
        );
        return [
            'contest' => ['id' => (int) $c['id'], 'title' => (string) $c['title'], 'status' => self::status($c)],
            'rows' => array_map(static fn (array $r): array => [
                'name' => (string) $r['display_name'],
                'isMe' => (int) $r['user_id'] === $userId,
                'at' => Clock::parse((string) $r['created_at'])->format('H:i, d/m'),
            ], array_slice($rows, 0, $limit)),
            'count' => count($rows),
        ];
    }

    /**
     * Cơ cấu giải kèm số tiền tính từ quỹ đang nhập, làm tròn xuống nghìn đồng cho dễ trao.
     * Quỹ còn nhỏ, giải chưa tới một nghìn thì giữ số thật, kẻo giải hiện 0 đồng.
     * @return list<array{label:string,percent:float,amount:?int}>
     */
    public static function prizes(): array
    {
        $fund = Settings::value('fund', 'amount', null);
        return array_map(static function (array $p) use ($fund): array {
            $amount = null;
            if ($fund !== null) {
                $exact = (int) floor(((int) $fund) * ((float) $p['percent']) / 100);
                $amount = $exact >= 1000 ? (int) (floor($exact / 1000) * 1000) : $exact;
            }
            return ['label' => (string) $p['label'], 'percent' => (float) $p['percent'], 'amount' => $amount];
        }, array_values((array) Settings::value('prizes', 'rows', [])));
    }

    /**
     * Bảng xếp hạng của cuộc thi đang mở hoặc vừa kết thúc gần nhất.
     * Thứ tự: đúng nhiều hơn, rồi làm nhanh hơn, rồi nộp sớm hơn để không bao giờ có hai người cùng hạng.
     * @return array{contest:?array<string,mixed>,rows:list<array<string,mixed>>,me:?array<string,mixed>,participants:int}
     */
    public static function ranking(int $userId, int $limit = 50): array
    {
        // Hai bài thi trùng giờ thì chọn đúng bài hộp quỹ đang hiện (xem current), kẻo hai nơi nói hai bài khác nhau.
        $now = Clock::sql();
        $c = Db::one(
            'SELECT * FROM contests WHERE starts_at <= ? AND ends_at > ? ORDER BY starts_at, id LIMIT 1',
            [$now, $now],
        ) ?? Db::one('SELECT * FROM contests WHERE ends_at <= ? ORDER BY ends_at DESC, id DESC LIMIT 1', [$now]);
        if ($c === null) {
            return ['contest' => null, 'rows' => [], 'me' => null, 'participants' => 0];
        }

        // Người đóng tab trước khi hết giờ chưa được chấm: chấm luôn cho bảng đủ người, không chờ cron.
        $overdue = Db::col(
            'SELECT id FROM test_attempts WHERE contest_id = ? AND submitted_at IS NULL AND deadline_at < ? LIMIT 200',
            [(int) $c['id'], Clock::sql(Clock::now()->modify('-' . TestService::GRACE_SEC . ' seconds'))],
        );
        foreach ($overdue as $attemptId) {
            TestService::grade((int) $attemptId, true);
        }

        $rows = [];
        $me = null;
        foreach (Db::all(
            'SELECT a.user_id, a.correct_count, a.used_sec, JSON_LENGTH(a.question_ids) AS total, u.display_name
               FROM test_attempts a JOIN users u ON u.id = a.user_id
              WHERE a.contest_id = ? AND a.submitted_at IS NOT NULL
              ORDER BY a.correct_count DESC, a.used_sec ASC, a.submitted_at ASC, a.id ASC',
            [(int) $c['id']],
        ) as $i => $a) {
            $row = [
                'rank' => $i + 1,
                'name' => (string) $a['display_name'],
                'correct' => (int) $a['correct_count'],
                'total' => (int) $a['total'],
                'usedSec' => (int) $a['used_sec'],
                'isMe' => (int) $a['user_id'] === $userId,
            ];
            if ($row['isMe']) {
                $me = $row;
            }
            if ($i < $limit) {
                $rows[] = $row;
            }
            $count = $i + 1;
        }

        return [
            'contest' => ['id' => (int) $c['id'], 'title' => (string) $c['title'], 'status' => self::status($c)],
            'rows' => $rows,
            'me' => $me,
            'participants' => $count ?? 0,
        ];
    }

    /** Người này đã vào thi chưa, để nút ở hộp quỹ nói đúng việc tiếp theo. */
    private static function myAttempt(int $contestId, int $userId): string
    {
        $a = Db::one('SELECT submitted_at, deadline_at FROM test_attempts WHERE contest_id = ? AND user_id = ?', [$contestId, $userId]);
        if ($a === null) {
            return 'none';
        }
        return $a['submitted_at'] !== null || (string) $a['deadline_at'] < Clock::sql() ? 'submitted' : 'doing';
    }

    /**
     * Cuộc thi người học đang thấy. Trả số giây còn lại thay vì giờ tuyệt đối để đồng hồ máy người học chạy sai
     * cũng không đếm lệch. Mã đề chỉ lộ ra khi đã mở thi và người này được vào.
     * @return array<string,mixed>|null
     */
    public static function current(int $userId): ?array
    {
        $c = self::currentRow();
        if ($c === null) {
            return null;
        }
        $m = Db::one(
            'SELECT m.level_id, (SELECT COUNT(*) FROM questions q WHERE q.mock_test_id = m.id) AS qn FROM mock_tests m WHERE m.id = ?',
            [(int) $c['mock_test_id']],
        ) ?? ['level_id' => 0, 'qn' => 0];

        $now = Clock::now()->getTimestamp();
        $status = self::status($c);
        $registered = self::isRegistered((int) $c['id'], $userId);
        $attempt = self::myAttempt((int) $c['id'], $userId);
        return [
            'id' => (int) $c['id'],
            'title' => (string) $c['title'],
            'status' => $status,
            'startsInSec' => max(0, Clock::ts((string) $c['starts_at']) - $now),
            'endsInSec' => max(0, Clock::ts((string) $c['ends_at']) - $now),
            'startsLabel' => Clock::parse((string) $c['starts_at'])->format('H:i, d/m/Y'),
            'endsLabel' => Clock::parse((string) $c['ends_at'])->format('H:i, d/m/Y'),
            'durationMin' => (int) $c['duration_min'],
            'questionCount' => (int) $m['qn'],
            'levelId' => (int) $m['level_id'],
            'mockTestId' => $status === 'open' && ($registered || $attempt !== 'none') ? (int) $c['mock_test_id'] : null,
            'myAttempt' => $attempt,
            'registered' => $registered,
            'registrantCount' => (int) Db::val('SELECT COUNT(*) FROM contest_registrations WHERE contest_id = ?', [(int) $c['id']]),
        ];
    }
}
