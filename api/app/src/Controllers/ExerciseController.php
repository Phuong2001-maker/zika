<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Json;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\ContentBuilder;
use Zika\Services\ProgressService;
use Zika\Services\QuestionGrader;

/**
 * Lượt làm Bài tập (04 §6).
 *
 * Bài tập chấm ngay ở trình duyệt cho mượt, nhưng điểm lưu lại luôn do server chấm lại
 * bằng QuestionGrader — gửi `correct` giả lên cũng không ăn thua.
 * Mỗi người chỉ giữ một lượt dở cho mỗi bài (và một cho Tổng hợp mỗi cấp).
 */
final class ExerciseController
{
    /** POST /exercises/attempts  { lessonId, mode } | { levelId, mode: "review" } */
    public function start(Request $r): Response
    {
        $v = new Validator($r->body);
        $mode = (string) $v->in('mode', 'Kiểu làm bài', ['full', 'retry_wrong', 'review'], 'full');
        $lessonId = $v->int('lessonId', 'Bài', 1, null, false);
        $levelId = $v->int('levelId', 'Cấp', 1, 6, false);
        $v->done();

        $userId = $r->userId();

        if ($mode === 'review') {
            if ($levelId === null) {
                throw HttpError::validation(['levelId' => 'Thiếu cấp.']);
            }
            $lv = ProgressService::visibleLevel($levelId);
            $ids = $this->reviewQuestionIds($lv);
            if ($ids === []) {
                throw HttpError::invalid('Cấp này chưa có câu bài tập nào.');
            }
            $attemptId = $this->createAttempt($userId, $levelId, null, $mode, $ids);
            ProgressService::saveReview($userId, $levelId, 'bt', 0, count($ids), ['attemptId' => $attemptId]);
            return Response::json(['attemptId' => $attemptId, 'questionIds' => $ids], 201);
        }

        if ($lessonId === null) {
            throw HttpError::validation(['lessonId' => 'Thiếu bài.']);
        }
        $lesson = ProgressService::visibleLesson($lessonId);

        $ids = $mode === 'retry_wrong'
            ? $this->wrongQuestionIds($userId, $lessonId)
            : array_map('intval', Db::col(
                "SELECT id FROM questions WHERE part = 'bt' AND lesson_id = ? ORDER BY sort_order, id",
                [$lessonId],
            ));

        if ($ids === []) {
            throw HttpError::invalid($mode === 'retry_wrong' ? 'Bạn không có câu sai nào.' : 'Bài này chưa có câu bài tập.');
        }

        $attemptId = $this->createAttempt($userId, (int) $lesson['level_id'], $lessonId, $mode, $ids);
        ProgressService::save($userId, $lessonId, 'bt', 0, count($ids), ['attemptId' => $attemptId, 'mode' => $mode]);

        return Response::json(['attemptId' => $attemptId, 'questionIds' => $ids], 201);
    }

    /** GET /exercises/attempts/{id} — tiếp tục lượt còn dở. */
    public function show(Request $r): array
    {
        $a = $this->attempt($r);
        return [
            'attemptId' => (int) $a['id'],
            'mode' => (string) $a['mode'],
            'lessonId' => $a['lesson_id'] === null ? null : (int) $a['lesson_id'],
            'levelId' => (int) $a['level_id'],
            'questionIds' => ContentBuilder::json($a['question_ids'], []),
            'results' => ContentBuilder::json($a['results'], []),
            'finishedAt' => $a['finished_at'],
        ];
    }

    /**
     * PATCH /exercises/attempts/{id}  { result: { qid, answer, correct } }
     * Server chấm lại và lưu kết quả của chính mình.
     */
    public function answer(Request $r): array
    {
        $a = $this->attempt($r);
        if ($a['finished_at'] !== null) {
            throw HttpError::invalid('Lượt làm bài này đã xong.');
        }

        $v = new Validator($r->body);
        $in = $v->arr('result', 'Câu trả lời');
        $v->done();

        $qid = (int) ($in['qid'] ?? 0);
        $ids = array_map('intval', ContentBuilder::json($a['question_ids'], []));
        if (!in_array($qid, $ids, true)) {
            throw HttpError::validation(['qid' => 'Câu này không thuộc lượt làm bài.']);
        }

        $q = Db::one('SELECT * FROM questions WHERE id = ?', [$qid]);
        if ($q === null) {
            throw HttpError::notFound('Câu hỏi không còn nữa.');
        }

        $given = $in['answer'] ?? null;
        $correct = QuestionGrader::check((string) $q['type'], ContentBuilder::json($q['payload'], []), $given);

        // Ghi đè nếu câu này đã trả lời (người học quay lại sửa).
        $results = ContentBuilder::json($a['results'], []);
        $results = array_values(array_filter($results, static fn (array $x): bool => (int) ($x['qid'] ?? 0) !== $qid));
        $results[] = ['qid' => $qid, 'answer' => $given, 'correct' => $correct];

        Db::update('exercise_attempts', ['results' => Json::encode($results)], ['id' => (int) $a['id']]);

        // Vị trí đang dở = số câu đã trả lời.
        $state = ['attemptId' => (int) $a['id'], 'mode' => (string) $a['mode']];
        if ($a['lesson_id'] !== null) {
            ProgressService::save($r->userId(), (int) $a['lesson_id'], 'bt', count($results), count($ids), $state);
        } else {
            ProgressService::saveReview($r->userId(), (int) $a['level_id'], 'bt', count($results), count($ids), $state);
        }

        return ['correct' => $correct, 'answered' => count($results), 'total' => count($ids)];
    }

    /** POST /exercises/attempts/{id}/finish */
    public function finish(Request $r): array
    {
        $a = $this->attempt($r);
        $ids = array_map('intval', ContentBuilder::json($a['question_ids'], []));
        $results = ContentBuilder::json($a['results'], []);

        $score = count(array_filter($results, static fn (array $x): bool => !empty($x['correct'])));
        $total = count($ids);

        if ($a['finished_at'] === null) {
            Db::update('exercise_attempts', [
                'score' => $score,
                'total' => $total,
                'finished_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $a['id']]);

            if ($a['lesson_id'] !== null) {
                ProgressService::complete($r->userId(), (int) $a['lesson_id'], 'bt');
                Db::exec(
                    "UPDATE lesson_progress SET last_score = ?, max_score = ?, best_score = GREATEST(COALESCE(best_score, 0), ?)
                     WHERE user_id = ? AND lesson_id = ? AND part = 'bt'",
                    [$score, $total, $score, $r->userId(), (int) $a['lesson_id']],
                );
            } else {
                ProgressService::completeReview($r->userId(), (int) $a['level_id'], 'bt');
            }
        }

        $wrong = array_values(array_filter($results, static fn (array $x): bool => empty($x['correct'])));

        return [
            'score' => $score,
            'total' => $total,
            'wrongCount' => count($wrong),
            'wrongQuestionIds' => array_map(static fn (array $x): int => (int) $x['qid'], $wrong),
        ];
    }

    /* ------------------------------------------------------------------ */

    /** @param list<int> $ids */
    private function createAttempt(int $userId, int $levelId, ?int $lessonId, string $mode, array $ids): int
    {
        // Bỏ lượt dở cũ của đúng phạm vi này (mỗi bài / mỗi cấp chỉ giữ 1 lượt).
        Db::exec(
            'UPDATE exercise_attempts SET finished_at = NOW() WHERE user_id = ? AND finished_at IS NULL AND '
            . ($lessonId === null ? 'lesson_id IS NULL AND level_id = ?' : 'lesson_id = ?'),
            [$userId, $lessonId ?? $levelId],
        );

        return Db::insert('exercise_attempts', [
            'user_id' => $userId,
            'level_id' => $levelId,
            'lesson_id' => $lessonId,
            'mode' => $mode,
            'question_ids' => Json::encode($ids),
            'results' => Json::encode([]),
            'total' => count($ids),
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Các câu sai của lượt `full` đã xong gần nhất. @return list<int> */
    private function wrongQuestionIds(int $userId, int $lessonId): array
    {
        $a = Db::one(
            "SELECT results FROM exercise_attempts
              WHERE user_id = ? AND lesson_id = ? AND mode = 'full' AND finished_at IS NOT NULL
              ORDER BY finished_at DESC LIMIT 1",
            [$userId, $lessonId],
        );
        if ($a === null) {
            return [];
        }
        $out = [];
        foreach (ContentBuilder::json($a['results'], []) as $x) {
            if (empty($x['correct'])) {
                $out[] = (int) $x['qid'];
            }
        }
        return $out;
    }

    /**
     * Tổng hợp: chọn ngẫu nhiên review_bt_count câu từ các bài đang hiện của cấp.
     * @param array<string,mixed> $lv dòng levels
     * @return list<int>
     */
    private function reviewQuestionIds(array $lv): array
    {
        $ids = ContentBuilder::reviewLessonIds($lv, false);
        if ($ids === []) {
            return [];
        }
        return array_map('intval', Db::col(
            "SELECT id FROM questions WHERE part = 'bt' AND lesson_id IN (" . Db::in($ids) . ') ORDER BY RAND() LIMIT '
            . max(1, (int) $lv['review_bt_count']),
            $ids,
        ));
    }

    /** @return array<string,mixed> lượt làm bài của chính người đang đăng nhập */
    private function attempt(Request $r): array
    {
        $a = Db::one('SELECT * FROM exercise_attempts WHERE id = ? AND user_id = ?', [$r->id(), $r->userId()]);
        if ($a === null) {
            throw HttpError::notFound('Không tìm thấy lượt làm bài.');
        }
        return $a;
    }
}
