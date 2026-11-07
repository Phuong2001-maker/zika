<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Json;
use Zika\Core\Log;

/**
 * Bài kiểm tra của bài học và đề thi thử (04 §7).
 *
 * Nguyên tắc: đề gửi ra trình duyệt KHÔNG kèm đáp án, và đáp án người học gửi lên dùng
 * chỉ số theo thứ tự đang hiển thị. Server lưu bảng quy đổi (option_maps) lúc tạo lượt,
 * nên lúc chấm luôn đổi được về chỉ số gốc — dù trình duyệt có gửi gì đi nữa.
 */
final class TestService
{
    /** Cho phép trễ 10 giây so với hạn cứng, bù độ lệch đồng hồ và độ trễ mạng. */
    public const GRACE_SEC = 10;

    /* ------------------------------------------------------------------ */
    /* Tạo / lấy lượt làm bài                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string,mixed> khối dữ liệu cho màn làm bài, hoặc ['resultAttemptId' => int]
     */
    public static function start(int $userId, ?int $lessonId, ?int $mockTestId): array
    {
        // Sau khi cuộc thi đóng, đề thường lui về Nháp nên source() sẽ 404. Người đã thi vẫn phải mở được kết quả của mình.
        if ($mockTestId !== null && $lessonId === null) {
            $done = self::submittedContestAttempt($userId, $mockTestId);
            if ($done !== null) {
                return ['resultAttemptId' => $done];
            }
        }
        // Chỉ chặn lúc vào làm: lượt đã làm vẫn phải chấm và xem lại được dù học viên đã đổi giáo trình.
        if ($lessonId !== null) {
            ProgressService::visibleLesson($lessonId, $userId);
        }
        [$cfg, $questions] = self::source($lessonId, $mockTestId);

        $contest = $mockTestId === null ? null : ContestService::openContest($mockTestId);
        if ($contest !== null) {
            return self::startContest($userId, (int) $mockTestId, $contest, $cfg, $questions);
        }

        // 1. Còn lượt dở trong hạn → trả lại đúng lượt đó.
        $open = Db::one(
            'SELECT * FROM test_attempts
              WHERE user_id = ? AND ' . self::scopeSql($lessonId) . ' AND submitted_at IS NULL
              ORDER BY id DESC LIMIT 1',
            [$userId, $lessonId ?? $mockTestId],
        );
        if ($open !== null) {
            if (!self::isOverdue($open)) {
                return self::payload($open, $cfg);
            }
            // Quá hạn mà chưa nộp → chấm luôn rồi báo có kết quả chờ xem.
            self::grade((int) $open['id'], true);
            return ['resultAttemptId' => (int) $open['id']];
        }

        // 2. Lượt gần nhất bị tự chấm mà người học chưa xem kết quả.
        $unseen = Db::one(
            'SELECT id FROM test_attempts
              WHERE user_id = ? AND ' . self::scopeSql($lessonId) . '
                AND submitted_at IS NOT NULL AND auto_submitted = 1 AND result_seen_at IS NULL
              ORDER BY id DESC LIMIT 1',
            [$userId, $lessonId ?? $mockTestId],
        );
        if ($unseen !== null) {
            return ['resultAttemptId' => (int) $unseen['id']];
        }

        // 3. Hết lượt làm.
        $limit = $cfg['attemptsLimit'] ?? null;
        if ($limit !== null) {
            $used = (int) Db::val(
                'SELECT COUNT(*) FROM test_attempts WHERE user_id = ? AND ' . self::scopeSql($lessonId) . ' AND submitted_at IS NOT NULL',
                [$userId, $lessonId ?? $mockTestId],
            );
            if ($used >= $limit) {
                throw new HttpError(409, 'ATTEMPTS_EXCEEDED');
            }
        }

        if ($questions === []) {
            throw HttpError::invalid('Bài này chưa có câu kiểm tra nào.');
        }

        // 4. Tạo lượt mới.
        return self::payload(self::create($userId, $lessonId, $mockTestId, $cfg, $questions), $cfg);
    }

    /**
     * Lượt đã nộp của người này ở cuộc thi gần nhất dùng đề, nhưng chỉ khi cuộc thi đó không còn mở:
     * đang mở thì startContest tự lo (mở lại kết quả hoặc làm tiếp).
     */
    private static function submittedContestAttempt(int $userId, int $mockTestId): ?int
    {
        $c = Db::one('SELECT id, ends_at FROM contests WHERE mock_test_id = ? ORDER BY starts_at DESC, id DESC LIMIT 1', [$mockTestId]);
        if ($c === null || (string) $c['ends_at'] > Clock::sql()) {
            return null;
        }
        $id = Db::val(
            'SELECT id FROM test_attempts WHERE user_id = ? AND contest_id = ? AND submitted_at IS NOT NULL',
            [$userId, (int) $c['id']],
        );
        return $id === null ? null : (int) $id;
    }

    /**
     * Cuộc thi: mỗi người một lượt để bảng xếp hạng rõ ràng. Đã nộp thì mở lại kết quả, không cho làm lại.
     * @param array<string,mixed> $contest
     * @param array<string,mixed> $cfg
     * @param list<array<string,mixed>> $questions
     * @return array<string,mixed>
     */
    private static function startContest(int $userId, int $mockTestId, array $contest, array $cfg, array $questions): array
    {
        $contestId = (int) $contest['id'];
        $cfg['durationSec'] = (int) $contest['duration_min'] * 60;
        $find = static fn (): ?array => Db::one('SELECT * FROM test_attempts WHERE user_id = ? AND contest_id = ?', [$userId, $contestId]);

        $mine = $find();
        if ($mine === null) {
            if (!ContestService::isRegistered($contestId, $userId)) {
                throw HttpError::forbidden('Bạn chưa đăng ký bài thi này trước giờ bắt đầu nên không vào thi được.');
            }
            if ($questions === []) {
                throw HttpError::invalid('Bài này chưa có câu kiểm tra nào.');
            }
            try {
                return self::payload(self::create($userId, null, $mockTestId, $cfg, $questions, $contest), $cfg);
            } catch (\PDOException) {
                // Mở hai tab cùng lúc: tab sau vấp UNIQUE (contest_id, user_id), lấy lại lượt tab trước vừa tạo.
                $mine = $find();
                if ($mine === null) {
                    throw HttpError::invalid('Không vào thi được, thử lại nhé.');
                }
            }
        }

        if ($mine['submitted_at'] !== null) {
            return ['resultAttemptId' => (int) $mine['id']];
        }
        if (self::isOverdue($mine)) {
            self::grade((int) $mine['id'], true);
            return ['resultAttemptId' => (int) $mine['id']];
        }
        return self::payload($mine, $cfg);
    }

    /** @param array<string,mixed> $a */
    private static function isOverdue(array $a): bool
    {
        return strtotime((string) $a['deadline_at']) + self::GRACE_SEC <= Clock::now()->getTimestamp();
    }

    /**
     * @param array<string,mixed> $cfg
     * @param list<array<string,mixed>> $questions
     * @param array<string,mixed>|null $contest
     * @return array<string,mixed> dòng test_attempts vừa tạo
     */
    private static function create(int $userId, ?int $lessonId, ?int $mockTestId, array $cfg, array $questions, ?array $contest = null): array
    {
        if ($cfg['shuffleQuestions']) {
            shuffle($questions);
        }

        $ids = [];
        $maps = [];
        foreach ($questions as $q) {
            $id = (int) $q['id'];
            $ids[] = $id;
            $maps[$id] = self::buildMap($q, (bool) $cfg['shuffleOptions']);
        }

        $now = Clock::now();
        // Tự nộp: hạn cứng = đúng thời lượng. Không tự nộp: nới gấp 3 (hết giờ vẫn làm tiếp được).
        $hard = (int) $cfg['durationSec'] * ($cfg['autoSubmit'] ? 1 : 3);
        // Cuộc thi: mọi người cùng dừng lúc hết giờ thi, vào muộn thì còn ít thời gian hơn như thi thật.
        $deadline = $contest !== null ? Clock::parse((string) $contest['ends_at']) : $now->modify("+$hard seconds");

        $id = Db::insert('test_attempts', [
            'user_id' => $userId,
            'lesson_id' => $lessonId,
            'mock_test_id' => $mockTestId,
            'contest_id' => $contest === null ? null : (int) $contest['id'],
            'question_ids' => Json::encode($ids),
            'option_maps' => Json::encode($maps),
            'answers' => Json::encode([]),
            'flags' => Json::encode([]),
            'listen_counts' => Json::encode([]),
            'started_at' => $now->format('Y-m-d H:i:s.v'),
            'deadline_at' => $deadline->format('Y-m-d H:i:s.v'),
        ]);

        return Db::one('SELECT * FROM test_attempts WHERE id = ?', [$id]) ?? [];
    }

    /**
     * Bảng “vị trí hiển thị → chỉ số gốc” cho options (trắc nghiệm), tiles (sắp xếp)
     * hoặc cột nghĩa (nối từ). Cột trái của nối từ không bao giờ xáo.
     * @param array<string,mixed> $q
     * @return list<int>
     */
    private static function buildMap(array $q, bool $shuffle): array
    {
        $payload = ContentBuilder::json($q['payload'], []);
        $n = match ((string) $q['type']) {
            'order' => count((array) ($payload['tiles'] ?? [])),
            'match' => count((array) ($payload['pairs'] ?? [])),
            'trans', 'tf' => 0,
            default => count((array) ($payload['options'] ?? [])),
        };
        if ($n === 0) {
            return [];
        }

        $map = range(0, $n - 1);
        // Câu sắp xếp và nối từ luôn phải xáo: cột phải của nối từ mà giữ nguyên thì dòng nào cũng thẳng hàng với đáp án.
        if ($shuffle || $q['type'] === 'order' || $q['type'] === 'match') {
            shuffle($map);
            // Tránh xáo ra đúng thứ tự gốc.
            if (($q['type'] === 'order' || $q['type'] === 'match') && $n > 1 && $map === range(0, $n - 1)) {
                [$map[0], $map[1]] = [$map[1], $map[0]];
            }
        }
        return $map;
    }

    /* ------------------------------------------------------------------ */
    /* Dữ liệu gửi ra trình duyệt                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed> $a dòng test_attempts
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    public static function payload(array $a, array $cfg): array
    {
        $ids = array_map('intval', ContentBuilder::json($a['question_ids'], []));
        $maps = ContentBuilder::json($a['option_maps'], []);
        $rows = self::questionsByIds($ids);

        $out = [];
        foreach ($ids as $i => $id) {
            $q = $rows[$id] ?? null;
            if ($q === null) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'no' => $i + 1,
                'type' => (string) $q['type'],
                'score' => (float) $q['score'],
                'section' => $q['section'],
                'payload' => self::viewPayload($q, (array) ($maps[$id] ?? $maps[(string) $id] ?? [])),
            ];
        }

        $started = strtotime((string) $a['started_at']);
        $soft = $started + (int) $cfg['durationSec'];

        return [
            'attemptId' => (int) $a['id'],
            'serverNow' => Clock::now()->format('c'),
            'deadlineAt' => date('c', strtotime((string) $a['deadline_at'])),
            'softDeadlineAt' => date('c', $soft),
            'durationSec' => (int) $cfg['durationSec'],
            'listenLimit' => (int) $cfg['listenLimit'],
            'maxScore' => array_sum(array_map(static fn (array $q): float => (float) $q['score'], $rows)),
            'passScore' => (float) $cfg['passScore'],
            'autoSubmit' => (bool) $cfg['autoSubmit'],
            'contestTitle' => self::contestTitle($a),
            'questions' => $out,
            'answers' => ContentBuilder::json($a['answers'], []),
            'flags' => ContentBuilder::json($a['flags'], []),
            'listenCounts' => ContentBuilder::json($a['listen_counts'], []),
        ];
    }

    /** @param array<string,mixed> $a */
    private static function contestStillOpen(array $a): bool
    {
        if (($a['contest_id'] ?? null) === null) {
            return false;
        }
        return Db::val('SELECT id FROM contests WHERE id = ? AND ends_at > ?', [(int) $a['contest_id'], Clock::sql()]) !== null;
    }

    /** @param array<string,mixed> $a */
    private static function contestTitle(array $a): ?string
    {
        if (($a['contest_id'] ?? null) === null) {
            return null;
        }
        $title = Db::val('SELECT title FROM contests WHERE id = ?', [(int) $a['contest_id']]);
        return $title === null ? null : (string) $title;
    }

    /**
     * Payload đã bỏ đáp án và đã xáo theo map (04 §7.1).
     * @param array<string,mixed> $q
     * @param list<int> $map
     * @return array<string,mixed>
     */
    private static function viewPayload(array $q, array $map): array
    {
        $p = QuestionGrader::hideAnswer(ContentBuilder::json($q['payload'], []));
        $type = (string) $q['type'];

        if ($type === 'match') {
            $pairs = (array) ($p['pairs'] ?? []);
            $p['left'] = array_map(static fn (array $x): string => (string) ($x[0] ?? ''), $pairs);
            $p['right'] = array_map(static fn (int $k): string => (string) ($pairs[$k][1] ?? ''), $map);
            unset($p['pairs']);
            return $p;
        }

        $key = $type === 'order' ? 'tiles' : 'options';
        if (isset($p[$key]) && is_array($p[$key]) && $map !== []) {
            $src = array_values($p[$key]);
            $p[$key] = array_map(static fn (int $k): mixed => $src[$k] ?? '', $map);
        }
        // Ở bài kiểm tra, câu điền chỗ trống không hiện nghĩa tiếng Việt.
        if ($type === 'blank') {
            $p['showMeaning'] = (bool) ($p['showMeaning'] ?? false);
        }
        return $p;
    }

    /* ------------------------------------------------------------------ */
    /* Lưu tạm và nộp bài                                                  */
    /* ------------------------------------------------------------------ */

    /** PATCH: gộp câu trả lời đang làm vào lượt. @param array<string,mixed> $in */
    public static function patch(array $a, array $in): void
    {
        self::assertOpen($a);

        $set = [];
        if (isset($in['answers']) && is_array($in['answers'])) {
            $set['answers'] = Json::encode($in['answers'] + ContentBuilder::json($a['answers'], []));
        }
        if (isset($in['flags']) && is_array($in['flags'])) {
            $set['flags'] = Json::encode(array_values(array_map('intval', $in['flags'])));
        }
        if (isset($in['listenCounts']) && is_array($in['listenCounts'])) {
            $set['listen_counts'] = Json::encode($in['listenCounts']);
        }
        if ($set !== []) {
            Db::update('test_attempts', $set, ['id' => (int) $a['id']]);
        }
        // Mở đề rồi để đó không phải là học; có câu trả lời mới tính.
        if (!empty($in['answers']) && is_array($in['answers'])) {
            ProgressService::markStudied((int) $a['user_id']);
        }
    }

    /**
     * Chấm bài và ghi tiến độ. Gọi khi người học bấm Nộp, khi quá hạn, hoặc từ cron.
     * @return array<string,mixed> khối kết quả (04 §7.2)
     */
    public static function grade(int $attemptId, bool $auto = false): array
    {
        $a = Db::one('SELECT * FROM test_attempts WHERE id = ?', [$attemptId]);
        if ($a === null) {
            throw HttpError::notFound('Không tìm thấy lượt làm bài.');
        }
        if ($a['submitted_at'] !== null) {
            return self::result($a);
        }

        [$cfg] = self::source(
            $a['lesson_id'] === null ? null : (int) $a['lesson_id'],
            $a['mock_test_id'] === null ? null : (int) $a['mock_test_id'],
            true,
        );

        $ids = array_map('intval', ContentBuilder::json($a['question_ids'], []));
        $maps = ContentBuilder::json($a['option_maps'], []);
        $answers = ContentBuilder::json($a['answers'], []);
        $rows = self::questionsByIds($ids);

        $score = 0.0;
        $max = 0.0;
        $correct = 0;
        $results = [];

        foreach ($ids as $i => $id) {
            $q = $rows[$id] ?? null;
            if ($q === null) {
                continue;
            }
            $type = (string) $q['type'];
            $payload = ContentBuilder::json($q['payload'], []);
            $pt = (float) $q['score'];
            $max += $pt;

            $given = $answers[$id] ?? $answers[(string) $id] ?? null;
            $orig = self::toOriginal($type, $given, (array) ($maps[$id] ?? $maps[(string) $id] ?? []));
            $ok = $orig !== null && QuestionGrader::check($type, $payload, $orig);
            if ($ok) {
                $score += $pt;
                $correct++;
            }

            $results[] = [
                'qid' => $id,
                'no' => $i + 1,
                'correct' => $ok,
                'type' => $type,
                'promptText' => QuestionGrader::promptText($type, $payload),
                'yourAnswerText' => QuestionGrader::yourAnswerText($type, $payload, $orig),
                'rightAnswerText' => QuestionGrader::rightAnswerText($type, $payload),
                'explain' => $q['explain_vi'],
            ];
        }

        // Tính tới hạn nộp chứ không tới lúc chấm: cron chấm muộn vài phút cũng không làm người ta tụt hạng.
        $started = strtotime((string) $a['started_at']);
        $finished = min(Clock::now()->getTimestamp(), strtotime((string) $a['deadline_at']));
        $used = max(0, $finished - $started);
        // Cuộc thi chỉ xếp hạng, không có điểm đạt.
        $passed = $a['contest_id'] !== null || $score >= (float) $cfg['passScore'];

        Db::update('test_attempts', [
            'submitted_at' => Clock::now()->format('Y-m-d H:i:s.v'),
            'auto_submitted' => $auto ? 1 : 0,
            'score' => $score,
            'max_score' => $max,
            'correct_count' => $correct,
            'passed' => $passed ? 1 : 0,
            'used_sec' => $used,
            'results' => Json::encode($results),
        ], ['id' => $attemptId]);

        // Ghi tiến độ cho bài kiểm tra của bài học (đề thi thử không gắn vào bài nào).
        // Chấm luôn thắng: bài bị ẩn hoặc học viên đã đổi giáo trình giữa chừng thì bỏ qua ghi tiến độ, không làm hỏng việc chấm.
        if (!$auto) {
            ProgressService::markStudied((int) $a['user_id']);
        }
        if ($a['lesson_id'] !== null && ProgressService::canSee((int) $a['lesson_id'], (int) $a['user_id'])) {
            $userId = (int) $a['user_id'];
            $lessonId = (int) $a['lesson_id'];
            ProgressService::complete($userId, $lessonId, 'kt', !$auto);
            Db::exec(
                "UPDATE lesson_progress
                    SET last_score = ?, max_score = ?, best_score = GREATEST(COALESCE(best_score, 0), ?)
                  WHERE user_id = ? AND lesson_id = ? AND part = 'kt'",
                [$score, $max, $score, $userId, $lessonId],
            );
        }

        return self::result(Db::one('SELECT * FROM test_attempts WHERE id = ?', [$attemptId]) ?? []);
    }

    /**
     * Đổi câu trả lời theo chỉ số hiển thị về chỉ số gốc (04 §7.1).
     * @param list<int> $map
     */
    private static function toOriginal(string $type, mixed $given, array $map): mixed
    {
        if ($given === null) {
            return null;
        }
        if ($map === []) {
            return $given;   // trans, tf: không có gì để quy đổi
        }

        if (QuestionGrader::isChoice($type)) {
            return is_numeric($given) && isset($map[(int) $given]) ? $map[(int) $given] : null;
        }
        if ($type === 'order') {
            if (!is_array($given)) {
                return null;
            }
            $out = [];
            foreach ($given as $pos) {
                if (!is_numeric($pos) || !isset($map[(int) $pos])) {
                    return null;
                }
                $out[] = $map[(int) $pos];
            }
            return $out;
        }
        if ($type === 'match') {
            $pairs = (array) ($given['pairs'] ?? $given);
            if (!is_array($pairs)) {
                return null;
            }
            $out = [];
            foreach ($pairs as $left => $rightPos) {
                if (!is_numeric($rightPos) || !isset($map[(int) $rightPos])) {
                    return null;
                }
                $out[(int) $left] = $map[(int) $rightPos];
            }
            return $out;
        }
        return $given;
    }

    /** @param array<string,mixed> $a @return array<string,mixed> */
    public static function result(array $a): array
    {
        [$cfg] = self::source(
            $a['lesson_id'] === null ? null : (int) $a['lesson_id'],
            $a['mock_test_id'] === null ? null : (int) $a['mock_test_id'],
            true,
        );

        $all =ContentBuilder::json($a['results'], []);
        // Chỉ trả câu sai, và chỉ khi admin cho xem đáp án sau khi nộp.
        // Cuộc thi còn mở thì giấu, kẻo người nộp sớm truyền đáp án cho người chưa thi.
        $show = (bool) $cfg['showAnswersAfter'] && !self::contestStillOpen($a);
        $wrong = array_values(array_filter($all, static fn (array $x): bool => empty($x['correct'])));

        $used = (int) ($a['used_sec'] ?? 0);
        $over = max(0, $used - (int) $cfg['durationSec']);
        $max = (float) ($a['max_score'] ?? 0);

        $out = [
            'attemptId' => (int) $a['id'],
            'score' => (float) ($a['score'] ?? 0),
            'maxScore' => $max,
            'percent' => $max > 0 ? (int) round(((float) $a['score']) / $max * 100) : 0,
            'passed' => $a['contest_id'] !== null ? true : (bool) $a['passed'],
            'passScore' => (float) $cfg['passScore'],
            'usedSec' => $used,
            'durationSec' => (int) $cfg['durationSec'],
            'autoSubmitted' => (bool) $a['auto_submitted'],
            'overtimeSec' => $cfg['autoSubmit'] ? 0 : $over,
            'results' => $show ? $wrong : [],
            'answeredCount' => count(array_filter($all, static fn (array $x): bool => ($x['yourAnswerText'] ?? '') !== 'Chưa trả lời')),
            'questionCount' => count($all),
            'contestTitle' => self::contestTitle($a),
            'correctCount' => (int) ($a['correct_count'] ?? 0),
            'answersHidden' => (bool) $cfg['showAnswersAfter'] && !$show,
        ];

        if ($a['lesson_id'] !== null) {
            $l = Db::one('SELECT * FROM lessons WHERE id = ?', [(int) $a['lesson_id']]);
            if ($l !== null) {
                $out['next'] = ContentBuilder::nextLesson($l, false, 'cnt_kt');
            }
            $out['attemptsUsed'] = (int) Db::val(
                "SELECT COUNT(*) FROM test_attempts WHERE user_id = ? AND lesson_id = ? AND submitted_at IS NOT NULL",
                [(int) $a['user_id'], (int) $a['lesson_id']],
            );
            $out['attemptsLimit'] = $cfg['attemptsLimit'];
        }

        return $out;
    }

    /** Tóm tắt cho màn Chọn bài (04 §7). @return array<string,mixed> */
    public static function summary(int $userId, ?int $lessonId, ?int $mockTestId): array
    {
        [$cfg] = self::source($lessonId, $mockTestId);
        $scope = self::scopeSql($lessonId);
        $key = $lessonId ?? $mockTestId;

        $best = Db::one(
            "SELECT MAX(score) AS best, MAX(max_score) AS max_score, MAX(passed) AS passed, COUNT(*) AS used
               FROM test_attempts WHERE user_id = ? AND $scope AND submitted_at IS NOT NULL",
            [$userId, $key],
        ) ?? [];

        $open = Db::one(
            "SELECT id, deadline_at FROM test_attempts
              WHERE user_id = ? AND $scope AND submitted_at IS NULL ORDER BY id DESC LIMIT 1",
            [$userId, $key],
        );
        $unseen = Db::val(
            "SELECT id FROM test_attempts
              WHERE user_id = ? AND $scope AND submitted_at IS NOT NULL AND result_seen_at IS NULL
              ORDER BY id DESC LIMIT 1",
            [$userId, $key],
        );

        return [
            'bestScore' => $best['best'] === null ? null : (float) $best['best'],
            'maxScore' => $best['max_score'] === null ? null : (float) $best['max_score'],
            'passed' => (bool) ($best['passed'] ?? false),
            'attemptsUsed' => (int) ($best['used'] ?? 0),
            'attemptsLimit' => $cfg['attemptsLimit'],
            'durationSec' => (int) $cfg['durationSec'],
            'passScore' => (float) $cfg['passScore'],
            'openAttemptId' => $open === null ? null : (int) $open['id'],
            'openDeadlineAt' => $open === null ? null : date('c', strtotime((string) $open['deadline_at'])),
            'unseenResultAttemptId' => $unseen === null ? null : (int) $unseen,
        ];
    }

    /** Cron 5 phút: chấm các lượt đã quá hạn cứng. Trả về số lượt chấm được. */
    public static function gradeOverdue(): int
    {
        // Mốc cắt tính bằng Clock, không dùng NOW() của DB (bẫy số 4).
        $ids = Db::col(
            'SELECT id FROM test_attempts WHERE submitted_at IS NULL AND deadline_at < ? LIMIT 200',
            [Clock::sql(Clock::now()->modify('-' . self::GRACE_SEC . ' seconds'))],
        );
        $ok = 0;
        foreach ($ids as $id) {
            // Một lượt hỏng không được chặn các lượt còn lại, nếu không cron kẹt mãi ở dòng đó.
            try {
                self::grade((int) $id, true);
                $ok++;
            } catch (\Throwable $e) {
                Log::error('Không chấm được lượt quá hạn', ['attemptId' => (int) $id, 'error' => $e->getMessage()]);
            }
        }
        return $ok;
    }

    /* ------------------------------------------------------------------ */
    /* Helper                                                              */
    /* ------------------------------------------------------------------ */

    /** Chấm tự động một lượt quá hạn mà không ném lỗi ra ngoài (dùng ở bảng xếp hạng). */
    public static function gradeSafely(int $attemptId): void
    {
        try {
            self::grade($attemptId, true);
        } catch (\Throwable $e) {
            Log::error('Không chấm được lượt quá hạn', ['attemptId' => $attemptId, 'error' => $e->getMessage()]);
        }
    }

    /** @param array<string,mixed> $a */
    public static function assertOpen(array $a): void
    {
        if ($a['submitted_at'] !== null) {
            throw new HttpError(409, 'TEST_EXPIRED', 'Bài này đã nộp rồi.', [], ['resultReady' => true, 'attemptId' => (int) $a['id']]);
        }
        if (self::isOverdue($a)) {
            self::grade((int) $a['id'], true);
            throw new HttpError(409, 'TEST_EXPIRED', '', [], ['resultReady' => true, 'attemptId' => (int) $a['id']]);
        }
    }

    private static function scopeSql(?int $lessonId): string
    {
        return $lessonId === null ? 'mock_test_id = ?' : 'lesson_id = ?';
    }

    /**
     * Cấu hình + danh sách câu của bài kiểm tra hoặc đề thi thử.
     * $hasAttempt: người học đã vào làm rồi, nên hết giờ thi hoặc đề bị ẩn vẫn phải chấm và xem lại được.
     * @return array{0:array<string,mixed>,1:list<array<string,mixed>>}
     */
    private static function source(?int $lessonId, ?int $mockTestId, bool $hasAttempt = false): array
    {
        if ($lessonId !== null) {
            if (!$hasAttempt) {
                ProgressService::publishedLesson($lessonId);
            }
            return [
                PartContent::testConfig($lessonId),
                Db::all("SELECT * FROM questions WHERE part = 'kt' AND lesson_id = ? ORDER BY sort_order, id", [$lessonId]),
            ];
        }

        $m = Db::one('SELECT * FROM mock_tests WHERE id = ?', [$mockTestId]);
        $canEnter = $m !== null && ($hasAttempt || $m['status'] === 'published' || ContestService::isOpen((int) $mockTestId));
        if (!$canEnter) {
            throw HttpError::notFound('Đề thi thử này chưa mở.');
        }
        return [[
            'durationSec' => (int) $m['duration_sec'],
            'passScore' => (float) $m['pass_points'],
            'listenLimit' => (int) $m['listen_limit'],
            'attemptsLimit' => null,
            'shuffleQuestions' => false,   // đề thi thử giữ nguyên thứ tự như đề thật
            'shuffleOptions' => true,
            'autoSubmit' => true,
            'showAnswersAfter' => true,
        ], Db::all('SELECT * FROM questions WHERE mock_test_id = ? ORDER BY sort_order, id', [$mockTestId])];
    }

    /**
     * @param list<int> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function questionsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (Db::all('SELECT * FROM questions WHERE id IN (' . Db::in($ids) . ')', $ids) as $q) {
            $out[(int) $q['id']] = $q;
        }
        return $out;
    }
}
