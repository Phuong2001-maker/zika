<?php
declare(strict_types=1);

namespace Zika\Ctrl;

use Zika\Auth;
use Zika\Db;
use Zika\Grader;
use Zika\Http\ApiError;
use Zika\Http\Req;
use Zika\Http\Res;
use Zika\Util;

/** Ghi tiến độ học, chấm bài kiểm tra, đếm thời gian online. */
final class ProgressCtrl
{
    /**
     * POST /api/progress/study
     * body: { lessonId, position, known, notYet, total, words: [{id, state}] }
     *
     * Gọi mỗi khi người học lật xong một thẻ, nên phải nhẹ: một UPSERT cho
     * bảng tiến độ + một UPSERT gộp cho các từ vừa đánh dấu.
     */
    public static function study(Req $req): never
    {
        $u        = Auth::need($req);
        $lessonId = $req->int('lessonId');
        $total    = max(0, $req->int('total'));
        $position = max(0, $req->int('position'));
        $known    = max(0, $req->int('known'));
        $done     = $total > 0 && $position >= $total;

        // lessonId = 0 nghĩa là bộ thẻ Tổng hợp của cả cấp: không có bài nào để
        // ghi tiến độ, chỉ cần cập nhật trạng thái từng từ.
        if ($lessonId > 0) {
            ContentCtrl::lesson($lessonId);
            self::upsertPart((int) $u['id'], $lessonId, 'study', [
                'position' => $position,
                'known'    => $known,
                'total'    => $total,
                'done_at'  => $done ? date('Y-m-d H:i:s') : null,
            ]);
        }

        $words = $req->arr('words');
        if ($words !== []) {
            self::saveWordStates((int) $u['id'], $words);
        }

        Res::ok(['done' => $done]);
    }

    /**
     * POST /api/progress/exercise
     * body: { lessonId, answers: { questionId: given } }
     *
     * Bài tập chấm ngay tại máy để phản hồi tức thì, nhưng điểm lưu lại vẫn do
     * server chấm lại — tránh gửi điểm giả.
     */
    public static function exercise(Req $req): never
    {
        $u      = Auth::need($req);
        $lesson = ContentCtrl::lesson($req->int('lessonId'));
        $given  = $req->arr('answers');

        $rows = Db::all(
            "SELECT * FROM questions WHERE lesson_id = ? AND bank = 'bt' ORDER BY sort, id",
            [$lesson['id']]
        );
        if ($rows === []) {
            throw ApiError::notFound('Bài này chưa có bài tập.');
        }

        [$score, $total, $detail] = self::grade($rows, $given);

        self::upsertPart((int) $u['id'], (int) $lesson['id'], 'bt', [
            'position'   => $total,
            'known'      => $score,
            'total'      => $total,
            'last_score' => $score,
            'done_at'    => date('Y-m-d H:i:s'),
        ], bestScore: $score);

        Db::insert('test_attempts', [
            'user_id'      => $u['id'],
            'lesson_id'    => $lesson['id'],
            'bank'         => 'bt',
            'score'        => $score,
            'total'        => $total,
            'duration_sec' => max(0, $req->int('durationSec')),
            'detail'       => Util::jsonEncode($detail),
        ]);

        Res::ok(['score' => $score, 'total' => $total, 'detail' => $detail]);
    }

    /**
     * POST /api/attempts/test
     * body: { lessonId, answers: { questionId: given }, durationSec }
     *
     * Đây là chỗ duy nhất tính điểm bài Kiểm tra. Trả về cả đáp án đúng và lời
     * giải để màn Tổng kết xem lại được.
     */
    public static function submitTest(Req $req): never
    {
        $u      = Auth::need($req);
        $lesson = ContentCtrl::lesson($req->int('lessonId'));
        $given  = $req->arr('answers');

        $rows = Db::all(
            "SELECT * FROM questions WHERE lesson_id = ? AND bank = 'kt' ORDER BY sort, id",
            [$lesson['id']]
        );
        if ($rows === []) {
            throw ApiError::notFound('Bài này chưa có bài kiểm tra.');
        }

        [$score, $total, $detail] = self::grade($rows, $given);
        $duration = max(0, $req->int('durationSec'));

        $attemptId = Db::insert('test_attempts', [
            'user_id'      => $u['id'],
            'lesson_id'    => $lesson['id'],
            'bank'         => 'kt',
            'score'        => $score,
            'total'        => $total,
            'duration_sec' => $duration,
            'detail'       => Util::jsonEncode($detail),
        ]);

        self::upsertPart((int) $u['id'], (int) $lesson['id'], 'kt', [
            'position'   => $total,
            'known'      => $score,
            'total'      => $total,
            'last_score' => $score,
            'done_at'    => date('Y-m-d H:i:s'),
        ], bestScore: $score);

        // Gửi kèm đáp án + lời giải của từng câu để xem lại sau khi đã nộp.
        $review = array_map(static fn($r) => [
            'id'      => (int) $r['id'],
            'kind'    => $r['kind'],
            'answer'  => Util::jsonDecode($r['answer'], []),
            'explain' => $r['explain'],
            'key'     => $r['kind'] === 'match' ? Grader::matchKey($r) : null,
        ], $rows);

        Res::ok([
            'attemptId'   => $attemptId,
            'score'       => $score,
            'total'       => $total,
            'durationSec' => $duration,
            'detail'      => $detail,
            'review'      => $review,
        ]);
    }

    /**
     * POST /api/progress/part
     * body: { lessonId, part: lv|ht|np, position, total, done }
     *
     * Ba phần Luyện viết / Hội thoại / Ngữ pháp không có điểm, chỉ cần biết
     * đang ở đâu và đã xong chưa.
     */
    public static function part(Req $req): never
    {
        $u    = Auth::need($req);
        $part = ContentCtrl::part($req->str('part'));
        if (in_array($part, ['study', 'bt', 'kt'], true)) {
            throw ApiError::badRequest('Phần này có API riêng để ghi tiến độ.');
        }

        $lesson   = ContentCtrl::lesson($req->int('lessonId'));
        $total    = max(0, $req->int('total'));
        $position = max(0, $req->int('position'));
        $done     = $req->bool('done') || ($total > 0 && $position >= $total);

        self::upsertPart((int) $u['id'], (int) $lesson['id'], $part, [
            'position' => $position,
            'known'    => $position,
            'total'    => $total,
            'done_at'  => $done ? date('Y-m-d H:i:s') : null,
        ]);

        Res::ok(['done' => $done]);
    }

    /** POST /api/progress/word — đánh dấu một từ là đã biết / chưa thuộc. */
    public static function word(Req $req): never
    {
        $u = Auth::need($req);
        self::saveWordStates((int) $u['id'], [[
            'id'    => $req->int('wordId'),
            'state' => $req->str('state', 'known'),
        ]]);
        Res::ok();
    }

    /**
     * POST /api/session/ping
     * body: { part, lessonId }
     *
     * Giao diện gọi mỗi 60 giây khi tab đang mở. Phiên nào không ping trong
     * 3 phút thì coi như đã thoát (trang Quản trị dựa vào đó để đếm online).
     */
    public static function ping(Req $req): never
    {
        $u       = Auth::need($req);
        $uid     = (int) $u['id'];
        $part    = $req->str('part');
        $lessonId = $req->int('lessonId');
        $part    = in_array($part, ContentCtrl::PARTS, true) ? $part : '';

        [, $platform, $isPhone] = Util::device($req->ua());

        $live = Db::one(
            'SELECT * FROM study_sessions
              WHERE user_id = ? AND ended_at IS NULL
                AND last_ping_at > (NOW() - INTERVAL 180 SECOND)
              ORDER BY id DESC LIMIT 1',
            [$uid]
        );

        if ($live === null) {
            // Đóng những phiên treo (tab bị tắt đột ngột) rồi mở phiên mới.
            Db::run(
                'UPDATE study_sessions
                    SET ended_at = last_ping_at
                  WHERE user_id = ? AND ended_at IS NULL',
                [$uid]
            );
            $id = Db::insert('study_sessions', [
                'user_id'        => $uid,
                'is_phone'       => $isPhone ? 1 : 0,
                'platform'       => $platform,
                'parts'          => $part,
                'last_part'      => $part,
                'last_lesson_id' => $lessonId > 0 ? $lessonId : null,
            ]);
            $seconds = 0;
        } else {
            $id    = (int) $live['id'];
            $parts = array_values(array_unique(array_filter(
                array_merge(explode(',', (string) $live['parts']), [$part])
            )));
            $seconds = (int) $live['seconds'] + max(0, min(120, time() - strtotime((string) $live['last_ping_at'])));

            Db::run(
                'UPDATE study_sessions
                    SET last_ping_at = NOW(), seconds = ?, parts = ?, last_part = ?, last_lesson_id = ?
                  WHERE id = ?',
                [$seconds, implode(',', $parts), $part, $lessonId > 0 ? $lessonId : null, $id]
            );
        }

        // Tổng thời gian học của user được tính lại từ các phiên, tránh lệch.
        Db::run(
            'UPDATE users u
                SET u.total_seconds = (SELECT COALESCE(SUM(s.seconds), 0) FROM study_sessions s WHERE s.user_id = u.id),
                    u.last_seen_at  = NOW()
              WHERE u.id = ?',
            [$uid]
        );

        Res::ok(['sessionId' => $id, 'seconds' => $seconds]);
    }

    /** POST /api/session/end — gọi khi đóng tab (navigator.sendBeacon). */
    public static function endSession(Req $req): never
    {
        $u = Auth::need($req);
        Db::run(
            'UPDATE study_sessions SET ended_at = NOW() WHERE user_id = ? AND ended_at IS NULL',
            [$u['id']]
        );
        Res::ok();
    }

    /** GET /api/progress/continue — thẻ "Học tiếp" ở trang Bàn học. */
    public static function continueCard(Req $req): never
    {
        $u = Auth::need($req);

        $row = Db::one(
            "SELECT p.*, l.hsk, l.no, l.title, l.sample
               FROM user_part_progress p
               JOIN lessons l ON l.id = p.lesson_id
              WHERE p.user_id = ? AND p.part = 'study' AND p.position > 0
              ORDER BY p.updated_at DESC LIMIT 1",
            [$u['id']]
        );

        if ($row === null) {
            Res::ok(null);
        }

        $words = Db::all(
            'SELECT hz FROM words WHERE lesson_id = ? ORDER BY sort, id LIMIT 3',
            [$row['lesson_id']]
        );

        Res::ok([
            'lessonId' => (int) $row['lesson_id'],
            'hsk'      => (int) $row['hsk'],
            'no'       => (int) $row['no'],
            'title'    => $row['title'],
            'sample'   => implode(' · ', array_column($words, 'hz')),
            'position' => (int) $row['position'],
            'total'    => (int) $row['total'],
            'pct'      => ((int) $row['total']) > 0
                ? (int) round((int) $row['position'] / (int) $row['total'] * 100)
                : 0,
            'updatedAt' => $row['updated_at'],
        ]);
    }

    // ─────────────────────────── helper ───────────────────────────

    /**
     * Chấm cả bộ câu hỏi.
     * @return array{0:int,1:int,2:array}
     */
    private static function grade(array $rows, array $given): array
    {
        $score  = 0;
        $total  = 0;
        $detail = [];

        foreach ($rows as $r) {
            $id  = (int) $r['id'];
            $pt  = max(1, (int) $r['score']);
            $total += $pt;

            $ansGiven = $given[$id] ?? $given[(string) $id] ?? null;
            $ok = $ansGiven !== null && Grader::check($r, $ansGiven);
            if ($ok) {
                $score += $pt;
            }

            $detail[] = ['qid' => $id, 'ok' => $ok, 'given' => $ansGiven];
        }

        return [$score, $total, $detail];
    }

    /** UPSERT một dòng user_part_progress; best_score chỉ tăng chứ không giảm. */
    private static function upsertPart(int $userId, int $lessonId, string $part, array $data, ?int $bestScore = null): void
    {
        $cols = ['position', 'known', 'total', 'last_score', 'done_at'];
        $row  = [
            'user_id'   => $userId,
            'lesson_id' => $lessonId,
            'part'      => $part,
        ];
        foreach ($cols as $c) {
            if (array_key_exists($c, $data)) {
                $row[$c] = $data[$c];
            }
        }
        if ($bestScore !== null) {
            $row['best_score'] = $bestScore;
        }

        $names  = array_keys($row);
        $place  = implode(', ', array_map(static fn($c) => ":$c", $names));
        $update = [];
        foreach ($names as $c) {
            if (in_array($c, ['user_id', 'lesson_id', 'part'], true)) {
                continue;
            }
            // done_at đã có thì giữ, tránh một lần lật lại làm mất mốc hoàn thành.
            $update[] = match ($c) {
                'best_score' => '`best_score` = GREATEST(COALESCE(`best_score`, 0), VALUES(`best_score`))',
                'done_at'    => '`done_at` = COALESCE(`done_at`, VALUES(`done_at`))',
                default      => "`$c` = VALUES(`$c`)",
            };
        }

        $sql = sprintf(
            'INSERT INTO user_part_progress (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            implode(', ', array_map(static fn($c) => "`$c`", $names)),
            $place,
            implode(', ', $update)
        );
        Db::run($sql, $row);
    }

    /** Ghi trạng thái nhiều từ trong một câu lệnh. */
    private static function saveWordStates(int $userId, array $items): void
    {
        $valid  = ['new', 'known', 'not_yet'];
        $rows   = [];
        $params = [];

        foreach ($items as $it) {
            $wid   = (int) ($it['id'] ?? 0);
            $state = (string) ($it['state'] ?? 'known');
            if ($wid <= 0 || !in_array($state, $valid, true)) {
                continue;
            }
            $rows[]   = '(?, ?, ?, 1)';
            $params[] = $userId;
            $params[] = $wid;
            $params[] = $state;
        }

        if ($rows === []) {
            return;
        }

        Db::run(
            'INSERT INTO user_word_progress (user_id, word_id, state, reps) VALUES '
            . implode(', ', $rows)
            . ' ON DUPLICATE KEY UPDATE state = VALUES(state), reps = reps + 1',
            $params
        );
    }
}
