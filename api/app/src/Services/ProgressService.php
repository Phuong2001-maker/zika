<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Json;

/**
 * Tiến độ học (04 §5, 03 §5.2).
 * - lesson_progress: 1 dòng / (người, bài, phần). status='done' từ lần đầu hoàn thành và GIỮ NGUYÊN khi học lại.
 * - review_progress: 1 dòng / (người, cấp, phần) cho phần “Tổng hợp”.
 * Chỉ ghi khi bài/cấp đang hiện; chế độ xem trước của biên tập/quản trị thì không ghi gì.
 */
final class ProgressService
{
    public const PARTS = ['lt', 'bt', 'kt', 'lv', 'ht', 'np'];
    /** Phần ghi vị trí / hoàn thành qua /progress (bt, kt có API riêng). */
    public const SIMPLE_PARTS = ['lt', 'lv', 'ht', 'np'];
    private const CNT = ['lt' => 'cnt_words', 'bt' => 'cnt_bt', 'kt' => 'cnt_kt', 'lv' => 'cnt_lv', 'ht' => 'cnt_ht', 'np' => 'cnt_np'];

    public static function cntCol(string $part): string
    {
        return self::CNT[$part] ?? throw HttpError::notFound();
    }

    /* ------------------------------------------------------------------ */
    /* Đọc                                                                 */
    /* ------------------------------------------------------------------ */

    /** GET /progress/home?part= (04 §5.1). @return array<string,mixed> */
    public static function home(int $userId, string $part): array
    {
        $cnt = self::cntCol($part);
        $user = Db::one('SELECT current_level_id FROM users WHERE id = ?', [$userId]);
        $currentLevel = $user['current_level_id'] === null ? null : (int) $user['current_level_id'];
        $curriculum = Curricula::ofUser($userId);

        $totals = [];
        foreach (Db::all(
            "SELECT l.level_id, COUNT(*) AS n FROM lessons l JOIN levels v ON v.id = l.level_id
             WHERE l.curriculum_id = ? AND " . ContentVisibility::lessonSql() . " AND l.$cnt > 0 GROUP BY l.level_id",
            [$curriculum],
        ) as $row) {
            $totals[(int) $row['level_id']] = (int) $row['n'];
        }
        $done = [];
        foreach (Db::all(
            "SELECT l.level_id, COUNT(*) AS n FROM lesson_progress p
             JOIN lessons l ON l.id = p.lesson_id JOIN levels v ON v.id = l.level_id
             WHERE p.user_id = ? AND p.part = ? AND p.status = 'done' AND l.curriculum_id = ? AND " . ContentVisibility::lessonSql() . " AND l.$cnt > 0
             GROUP BY l.level_id",
            [$userId, $part, $curriculum],
        ) as $row) {
            $done[(int) $row['level_id']] = (int) $row['n'];
        }
        $levels = [];
        foreach ($totals as $lid => $t) {
            $levels[] = ['levelId' => $lid, 'done' => $done[$lid] ?? 0, 'total' => $t];
        }

        // Thẻ “Học tiếp”: lượt đang dở mới nhất; không có thì bài đầu tiên chưa xong.
        $cont = null;
        if ($part !== 'kt') {
            $cont = Db::one(
                "SELECT l.*, p.position AS p_position, p.total AS p_total, p.updated_at AS p_updated_at FROM lesson_progress p
                 JOIN lessons l ON l.id = p.lesson_id JOIN levels v ON v.id = l.level_id
                 WHERE p.user_id = ? AND p.part = ? AND p.position > 0 AND l.curriculum_id = ? AND " . ContentVisibility::lessonSql() . " AND l.$cnt > 0
                 ORDER BY p.updated_at DESC LIMIT 1",
                [$userId, $part, $curriculum],
            );
        }
        if ($cont === null) {
            $order = $currentLevel !== null ? 'ORDER BY (l.level_id = ' . $currentLevel . ') DESC, l.level_id, l.lesson_no' : 'ORDER BY l.level_id, l.lesson_no';
            $cont = Db::one(
                "SELECT l.*, 0 AS p_position, l.$cnt AS p_total, NULL AS p_updated_at FROM lessons l JOIN levels v ON v.id = l.level_id
                 LEFT JOIN lesson_progress p ON p.lesson_id = l.id AND p.user_id = ? AND p.part = ?
                 WHERE l.curriculum_id = ? AND " . ContentVisibility::lessonSql() . " AND l.$cnt > 0 AND (p.status IS NULL OR p.status <> 'done')
                 $order LIMIT 1",
                [$userId, $part, $curriculum],
            );
        }

        $suggest = null;
        foreach (array_reverse($levels) as $lv) {
            if ($lv['total'] > 0 && $lv['done'] / $lv['total'] >= 0.8) {
                $times = (int) Db::val('SELECT times_done FROM review_progress WHERE user_id = ? AND level_id = ? AND part = ?', [$userId, $lv['levelId'], $part]);
                if ($times === 0) {
                    $suggest = $lv;
                    break;
                }
            }
        }

        return [
            'currentLevelId' => $currentLevel,
            'levels' => $levels,
            'continue' => $cont === null ? null : [
                'levelId' => (int) $cont['level_id'],
                'lessonId' => (int) $cont['id'],
                'lessonNo' => (int) $cont['lesson_no'],
                'title' => (string) $cont['title'],
                'sampleText' => $part === 'lt' ? self::sampleWords((int) $cont['id'], 3, (string) $cont['sample_text']) : (string) $cont['sample_text'],
                'position' => (int) $cont['p_position'],
                'total' => (int) $cont['p_total'] > 0 ? (int) $cont['p_total'] : (int) $cont[$cnt],
                'updatedAt' => Clock::iso($cont['p_updated_at']),
                'extra' => self::lessonExtra((int) $cont['id'], $part, $cont),
            ],
            'reviewSuggest' => $suggest,
            'today' => self::today($userId),
        ];
    }

    /**
     * Khối “Hôm nay” ở Bàn học: chuỗi ngày học liên tiếp, 7 ngày gần nhất, số từ đã lật xong.
     * “Ngày có học” = ngày có dòng trong study_days (xem markStudied), không tính mở web rồi đóng (visits)
     * và không tính chỉ mở trang bài.
     * Hôm nay chưa học thì chuỗi vẫn giữ tính đến hôm qua — sáng ra mở web không thấy về 0.
     * @return array{streak:int, week:list<bool>, words:int}
     */
    public static function today(int $userId): array
    {
        $now = Clock::now();
        // Đọc riêng study_days (đã có khoá chính theo người + ngày) và không giới hạn 60 ngày như trước:
        // chuỗi dài hơn mức đó mới đếm đúng. 400 ngày là đủ cho mọi chuỗi thực tế mà truy vấn vẫn rẻ.
        $from = $now->setTime(0, 0)->modify('-400 days')->format('Y-m-d');
        $days = array_flip(array_map('strval', Db::col(
            'SELECT day FROM study_days WHERE user_id = ? AND day >= ?',
            [$userId, $from],
        )));
        $co = static fn (int $back): bool => isset($days[$now->modify("-$back days")->format('Y-m-d')]);

        $streak = 0;
        for ($b = $co(0) ? 0 : 1; $co($b); $b++) {
            $streak++;
        }
        $week = [];
        for ($b = 6; $b >= 0; $b--) {
            $week[] = $co($b);
        }
        $words = (int) Db::val(
            "SELECT COALESCE(SUM(l.cnt_words), 0) FROM lesson_progress p JOIN lessons l ON l.id = p.lesson_id
             WHERE p.user_id = ? AND p.part = 'lt' AND p.status = 'done' AND l.status = 'published'",
            [$userId],
        );
        return ['streak' => $streak, 'week' => $week, 'words' => $words];
    }

    /** “鸡蛋 · 牛奶 · 羊肉” — n từ đầu của bài. */
    public static function sampleWords(int $lessonId, int $n, string $fallback = ''): string
    {
        $w = Db::col('SELECT hanzi FROM words WHERE lesson_id = ? ORDER BY sort_order, id LIMIT ' . max(1, $n), [$lessonId]);
        return $w === [] ? $fallback : implode(' · ', $w);
    }

    /**
     * Thông tin thêm cho thẻ “Học tiếp” của từng phần (07 §5.3: cont1).
     * @param array<string,mixed> $l dòng lessons
     * @return array<string,mixed>
     */
    private static function lessonExtra(int $lessonId, string $part, array $l): array
    {
        return match ($part) {
            'kt' => [
                'questionCount' => (int) $l['cnt_kt'],
                'durationMin' => (int) round(((int) (Db::val('SELECT duration_sec FROM test_configs WHERE lesson_id = ?', [$lessonId]) ?? 300)) / 60),
            ],
            'ht' => [
                'dialogueTitle' => (string) (Db::val('SELECT title FROM dialogues WHERE lesson_id = ?', [$lessonId]) ?? ''),
                'lineCount' => (int) $l['cnt_ht'],
            ],
            'np' => ['titles' => implode(' · ', Db::col('SELECT title FROM grammar_points WHERE lesson_id = ? ORDER BY sort_order, id', [$lessonId]))],
            default => ['count' => (int) $l[self::CNT[$part]]],
        };
    }

    /** GET /progress/levels/{id}?part= (04 §5.2). @return array<string,mixed> */
    public static function level(int $userId, int $levelId, string $part): array
    {
        $cnt = self::cntCol($part);
        $curriculum = Curricula::ofUser($userId);
        $rows = Db::all(
            'SELECT p.* FROM lesson_progress p JOIN lessons l ON l.id = p.lesson_id WHERE p.user_id = ? AND p.part = ? AND l.level_id = ? AND l.curriculum_id = ? AND ' . ContentVisibility::lessonSql() . ' ORDER BY l.lesson_no',
            [$userId, $part, $levelId, $curriculum],
        );
        $out = [];
        foreach ($rows as $p) {
            $item = self::rowToApi($p);
            if ($part === 'kt') {
                $pass = (float) (Db::val('SELECT pass_score FROM test_configs WHERE lesson_id = ?', [$p['lesson_id']]) ?? 6);
                $item['passed'] = $p['best_score'] !== null && (float) $p['best_score'] >= $pass;
                $open = Db::one(
                    'SELECT id, deadline_at FROM test_attempts WHERE user_id = ? AND lesson_id = ? AND submitted_at IS NULL AND deadline_at > NOW(3) ORDER BY id DESC LIMIT 1',
                    [$userId, $p['lesson_id']],
                );
                $item['openAttempt'] = $open === null ? null : ['id' => (int) $open['id'], 'deadlineAt' => Clock::isoMs((string) $open['deadline_at'])];
            }
            $out[] = $item;
        }
        $rv = Db::one('SELECT * FROM review_progress WHERE user_id = ? AND level_id = ? AND part = ?', [$userId, $levelId, $part]);
        $lessonCount = (int) Db::val("SELECT COUNT(*) FROM lessons WHERE level_id = ? AND curriculum_id = ? AND status = 'published' AND $cnt > 0", [$levelId, $curriculum]);
        $doneCount = (int) Db::val(
            "SELECT COUNT(*) FROM lesson_progress p JOIN lessons l ON l.id = p.lesson_id
             WHERE p.user_id = ? AND p.part = ? AND p.status = 'done' AND l.level_id = ? AND l.curriculum_id = ? AND l.status = 'published' AND l.$cnt > 0",
            [$userId, $part, $levelId, $curriculum],
        );
        return [
            'lessons' => $out,
            'review' => [
                'position' => (int) ($rv['position'] ?? 0),
                'total' => (int) ($rv['total'] ?? 0),
                'timesDone' => (int) ($rv['times_done'] ?? 0),
                'state' => $rv === null ? null : Json::decode($rv['state'] ?? null, null),
                'updatedAt' => Clock::iso($rv['updated_at'] ?? null),
            ],
            'doneCount' => $doneCount,
            'lessonCount' => $lessonCount,
        ];
    }

    /** @param array<string,mixed> $p @return array<string,mixed> */
    public static function rowToApi(array $p): array
    {
        $num = static fn ($v) => $v === null ? null : (float) $v + 0;
        return [
            'lessonId' => (int) $p['lesson_id'],
            'status' => (string) $p['status'],
            'position' => (int) $p['position'],
            'total' => (int) $p['total'],
            'bestScore' => $num($p['best_score']),
            'lastScore' => $num($p['last_score']),
            'maxScore' => $num($p['max_score']),
            'timesDone' => (int) $p['times_done'],
            'state' => Json::decode($p['state'] ?? null, null),
            'updatedAt' => Clock::iso((string) $p['updated_at']),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Ghi                                                                 */
    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed> dòng lessons đang hiện (404 nếu ẩn hoặc không thuộc giáo trình của học viên) */
    public static function visibleLesson(int $lessonId, int $userId): array
    {
        $l = self::publishedLesson($lessonId);
        // Biên tập và quản trị được làm thử bài của mọi giáo trình.
        $user = UserService::withEffectiveRole(UserService::find($userId) ?? []);
        if (($user['role'] ?? 'learner') === 'learner') {
            Curricula::assertLesson($lessonId, Curricula::ofUser($userId));
        }
        return $l;
    }

    /** @return array<string,mixed> dòng lessons đang hiện, không xét giáo trình (404 nếu ẩn) */
    public static function publishedLesson(int $lessonId): array
    {
        $l = Db::one(
            "SELECT l.* FROM lessons l JOIN levels v ON v.id = l.level_id WHERE l.id = ? AND " . ContentVisibility::lessonSql() . "",
            [$lessonId],
        );
        return $l ?? throw HttpError::notFound('Bài này chưa có hoặc đã bị ẩn.');
    }

    /**
     * Ghi nhận hôm nay là một ngày học. Chỉ gọi khi có hành động học thật (đã lật/trả lời/nộp),
     * không gọi khi mới mở trang, kẻo mở rồi thoát cũng giữ được chuỗi.
     */
    public static function markStudied(int $userId): void
    {
        Db::exec('INSERT IGNORE INTO study_days (user_id, day) VALUES (?, ?)', [$userId, Clock::now()->format('Y-m-d')]);
    }

    /** Bài còn hiện và nằm trong giáo trình của người này không (không ném lỗi). */
    public static function canSee(int $lessonId, int $userId): bool
    {
        try {
            self::visibleLesson($lessonId, $userId);
            return true;
        } catch (HttpError) {
            return false;
        }
    }

    /** @return array<string,mixed>|null */
    public static function row(int $userId, int $lessonId, string $part): ?array
    {
        return Db::one('SELECT * FROM lesson_progress WHERE user_id = ? AND lesson_id = ? AND part = ? FOR UPDATE', [$userId, $lessonId, $part]);
    }

    /**
     * PUT /progress/lessons/{id}/{part}: lưu vị trí lượt đang dở. Không hạ “done” về “in_progress”.
     * @param array<string,mixed>|null $state
     */
    public static function save(int $userId, int $lessonId, string $part, int $position, int $total, ?array $state): void
    {
        $l = self::visibleLesson($lessonId, $userId);
        Db::tx(static function () use ($userId, $lessonId, $part, $position, $total, $state, $l): void {
            $old = self::row($userId, $lessonId, $part);
            $now = Clock::sql();
            Db::exec(
                "INSERT INTO lesson_progress (user_id, lesson_id, part, status, position, total, state, started_at, updated_at)
                 VALUES (?, ?, ?, 'in_progress', ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE position = VALUES(position), total = VALUES(total), state = VALUES(state), updated_at = VALUES(updated_at)",
                [$userId, $lessonId, $part, $position, $total, $state === null ? null : Json::encode($state), $now, $now],
            );
            self::touchLevel($userId, (int) $l['level_id']);
            if ($position > 0) {
                self::markStudied($userId);
            }
            if ($part === 'lt') {
                $moved = max(0, $position - (int) ($old['position'] ?? 0));
                if ($moved > 0) {
                    VisitService::addStat($userId, static function (array $s) use ($moved): array {
                        $s['lt']['cards'] = (int) ($s['lt']['cards'] ?? 0) + $moved;
                        return $s;
                    });
                }
            }
        });
    }

    /**
     * POST …/complete (lt, lv, ht, np). Lần đầu xong phần Lật thẻ → +1 “bài đã học xong”.
     * @return array{firstTime:bool,levelDone:array{done:int,total:int},lessonsDone:int}
     */
    public static function complete(int $userId, int $lessonId, string $part, bool $markDay = true): array
    {
        $l = self::visibleLesson($lessonId, $userId);
        $levelId = (int) $l['level_id'];
        return Db::tx(static function () use ($userId, $lessonId, $part, $l, $levelId, $markDay): array {
            $old = self::row($userId, $lessonId, $part);
            $first = $old === null || $old['status'] !== 'done';
            $now = Clock::sql();
            $total = (int) $l[self::cntCol($part)];
            Db::exec(
                "INSERT INTO lesson_progress (user_id, lesson_id, part, status, position, total, times_done, state, started_at, first_done_at, last_done_at, updated_at)
                 VALUES (?, ?, ?, 'done', 0, ?, 1, NULL, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = 'done', position = 0, state = NULL, times_done = times_done + 1,
                   first_done_at = COALESCE(first_done_at, VALUES(first_done_at)), last_done_at = VALUES(last_done_at), updated_at = VALUES(updated_at)",
                [$userId, $lessonId, $part, $total, $now, $now, $now, $now],
            );
            self::touchLevel($userId, $levelId);
            // Bài kiểm tra bị cron chấm hộ (quá hạn) không phải lúc người học đang học nên không tính ngày.
            if ($markDay) {
                self::markStudied($userId);
            }
            if ($part === 'lt' && $first) {
                Db::exec('UPDATE users SET lessons_done = lessons_done + 1 WHERE id = ?', [$userId]);
            }
            $no = (int) $l['lesson_no'];
            $oldPos = (int) ($old['position'] ?? 0);
            VisitService::addStat($userId, static function (array $s) use ($part, $levelId, $no, $total, $oldPos, $first): array {
                switch ($part) {
                    case 'lt':
                        $s['lt']['cards'] = (int) ($s['lt']['cards'] ?? 0) + max(0, $total - $oldPos);
                        if ($first) {
                            $s['lt']['done'][] = ['level' => $levelId, 'lessonNo' => $no];
                        }
                        break;
                    case 'lv':
                        $s['lv']['chars'] = (int) ($s['lv']['chars'] ?? 0) + $total;
                        break;
                    default: // ht, np
                        $s[$part][] = ['level' => $levelId, 'lessonNo' => $no];
                }
                return $s;
            });

            $cnt = self::cntCol($part);
            return [
                'firstTime' => $first,
                'levelDone' => [
                    'done' => (int) Db::val(
                        "SELECT COUNT(*) FROM lesson_progress p JOIN lessons x ON x.id = p.lesson_id
                         WHERE p.user_id = ? AND p.part = ? AND p.status = 'done' AND x.level_id = ? AND x.curriculum_id = ? AND x.status = 'published' AND x.$cnt > 0",
                        [$userId, $part, $levelId, (int) $l['curriculum_id']],
                    ),
                    'total' => (int) Db::val("SELECT COUNT(*) FROM lessons WHERE level_id = ? AND curriculum_id = ? AND status = 'published' AND $cnt > 0", [$levelId, (int) $l['curriculum_id']]),
                ],
                'lessonsDone' => (int) Db::val('SELECT lessons_done FROM users WHERE id = ?', [$userId]),
            ];
        });
    }

    /** POST …/restart: bỏ lượt dở. */
    public static function restart(int $userId, int $lessonId, string $part): void
    {
        self::visibleLesson($lessonId, $userId);
        Db::exec(
            'UPDATE lesson_progress SET position = 0, state = NULL, updated_at = ? WHERE user_id = ? AND lesson_id = ? AND part = ?',
            [Clock::sql(), $userId, $lessonId, $part],
        );
    }

    /** @return array<string,mixed> dòng levels đang hiện */
    public static function visibleLevel(int $levelId, int $curriculumId = Curricula::HSK_ID): array
    {
        return ContentBuilder::level($levelId, false, $curriculumId);
    }

    /** @param array<string,mixed>|null $state */
    public static function saveReview(int $userId, int $levelId, string $part, int $position, int $total, ?array $state): void
    {
        self::visibleLevel($levelId, Curricula::ofUser($userId));
        $now = Clock::sql();
        Db::exec(
            'INSERT INTO review_progress (user_id, level_id, part, position, total, state, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE position = VALUES(position), total = VALUES(total), state = VALUES(state), updated_at = VALUES(updated_at)',
            [$userId, $levelId, $part, $position, $total, $state === null ? null : Json::encode($state), $now],
        );
        self::touchLevel($userId, $levelId);
        if ($position > 0) {
            self::markStudied($userId);
        }
    }

    /** @return array{timesDone:int} */
    public static function completeReview(int $userId, int $levelId, string $part): array
    {
        self::visibleLevel($levelId, Curricula::ofUser($userId));
        $now = Clock::sql();
        Db::exec(
            'INSERT INTO review_progress (user_id, level_id, part, position, total, times_done, state, updated_at) VALUES (?, ?, ?, 0, 0, 1, NULL, ?)
             ON DUPLICATE KEY UPDATE position = 0, state = NULL, times_done = times_done + 1, updated_at = VALUES(updated_at)',
            [$userId, $levelId, $part, $now],
        );
        self::touchLevel($userId, $levelId);
        self::markStudied($userId);
        return ['timesDone' => (int) Db::val('SELECT times_done FROM review_progress WHERE user_id = ? AND level_id = ? AND part = ?', [$userId, $levelId, $part])];
    }

    /** “Cấp đang học” = cấp của bài thao tác gần nhất (01 §4). */
    public static function touchLevel(int $userId, int $levelId): void
    {
        Db::exec('UPDATE users SET current_level_id = ? WHERE id = ? AND (current_level_id IS NULL OR current_level_id <> ?)', [$levelId, $userId, $levelId]);
    }
}
