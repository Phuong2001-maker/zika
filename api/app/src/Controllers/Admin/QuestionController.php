<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Json;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\ContentBuilder;
use Zika\Services\ContentWriter;
use Zika\Services\PartContent;
use Zika\Services\QuestionGrader;
use Zika\Services\QuestionPayload;

/** Quản trị · Bài tập, Kiểm tra, Thi thử (04 §10.4). Quyền E. */
final class QuestionController
{
    /** Trần cho một lượt nhập từ Excel — đủ rộng cho cả cấp, vẫn chặn file hỏng gửi hàng vạn dòng. */
    private const BULK_MAX = 500;

    /* ------------------------------------------------------------------ */
    /* Câu hỏi của bài (bt · kt)                                           */
    /* ------------------------------------------------------------------ */

    /** GET /admin/lessons/{id}/questions?part=bt|kt */
    public function index(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $part = self::part((string) $r->q('part', 'bt'));

        $out = [
            'questions' => self::rows('part = ? AND lesson_id = ?', [$part, (int) $lesson['id']]),
        ];
        if ($part === 'kt') {
            $out['config'] = PartContent::testConfig((int) $lesson['id']);
        }
        return $out;
    }

    /** POST /admin/lessons/{id}/questions */
    public function create(Request $r): Response
    {
        $lesson = ContentWriter::lesson($r->id());
        $part = self::part((string) ($r->input('part') ?? 'bt'));
        [$type, $payload, $explain, $score] = $this->read($r, $part);

        $id = Db::insert('questions', [
            'part' => $part,
            'lesson_id' => (int) $lesson['id'],
            'type' => $type,
            'payload' => Json::encode($payload),
            'explain_vi' => $explain,
            'score' => $score,
            'sort_order' => 1 + (int) Db::val(
                'SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE part = ? AND lesson_id = ?',
                [$part, (int) $lesson['id']],
            ),
        ]);

        ContentWriter::touch((int) $lesson['id'], $r);
        Audit::log($r, 'question.create', 'question', $id, ['lessonId' => (int) $lesson['id'], 'part' => $part, 'type' => $type]);
        return Response::json(self::row($id), 201);
    }

    /**
     * POST /admin/lessons/{id}/questions/bulk — nhập nhiều câu một lượt (từ Excel).
     * Câu nào sai thì bỏ qua và báo số dòng, các câu còn lại vẫn vào — nhập 50 câu không nên
     * hỏng cả mẻ vì một dòng thiếu đáp án.
     */
    public function bulk(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $lessonId = (int) $lesson['id'];

        $v = new Validator($r->body);
        $part = (string) $v->in('part', 'Phần', ['bt', 'kt'], 'bt');
        $mode = (string) $v->in('mode', 'Cách nhập', ['append', 'replace'], 'append');
        $items = $v->arr('items', 'Danh sách câu');
        $v->done();

        if (count($items) > self::BULK_MAX) {
            throw HttpError::validation(['items' => 'Mỗi lần nhập tối đa ' . self::BULK_MAX . ' câu.']);
        }

        $allowed = $part === 'bt' ? QuestionGrader::BT_TYPES : QuestionGrader::KT_TYPES;
        $created = 0;
        $skipped = [];

        Db::tx(static function () use ($items, $allowed, $part, $lessonId, $mode, &$created, &$skipped): void {
            if ($mode === 'replace') {
                Db::exec('DELETE FROM questions WHERE part = ? AND lesson_id = ?', [$part, $lessonId]);
            }
            $sort = (int) Db::val(
                'SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE part = ? AND lesson_id = ?',
                [$part, $lessonId],
            );

            foreach (array_values($items) as $i => $raw) {
                $row = is_array($raw) ? $raw : [];
                $type = (string) ($row['type'] ?? '');
                if (!in_array($type, $allowed, true)) {
                    $skipped[] = ['row' => $i + 1, 'reason' => 'Dạng câu không dùng được ở phần này.'];
                    continue;
                }

                try {
                    $payload = QuestionPayload::clean($type, (array) ($row['payload'] ?? []));
                } catch (HttpError $e) {
                    // Lấy đúng câu lỗi của trường đầu tiên để người soạn biết sửa ô nào.
                    $fields = $e->fields;
                    $skipped[] = ['row' => $i + 1, 'reason' => reset($fields) ?: $e->getMessage()];
                    continue;
                }

                $score = isset($row['score']) && is_numeric($row['score']) ? (float) $row['score'] : 1.0;
                Db::insert('questions', [
                    'part' => $part,
                    'lesson_id' => $lessonId,
                    'type' => $type,
                    'payload' => Json::encode($payload),
                    'explain_vi' => mb_substr(trim((string) ($row['explain'] ?? '')), 0, 2000) ?: null,
                    'score' => $score > 0 ? min($score, 100) : 1.0,
                    'sort_order' => ++$sort,
                ]);
                $created++;
            }
        });

        ContentWriter::touch($lessonId, $r);
        Audit::log($r, 'question.bulk', 'lesson', $lessonId, [
            'part' => $part, 'mode' => $mode, 'created' => $created, 'skipped' => count($skipped),
        ]);
        return ['created' => $created, 'skipped' => $skipped];
    }

    /** PUT /admin/questions/{id} */
    public function update(Request $r): array
    {
        $q = self::find($r->id());
        $part = (string) $q['part'];
        [$type, $payload, $explain, $score] = $this->read($r, $part === 'mock' ? 'kt' : $part);

        Db::update('questions', [
            'type' => $type,
            'payload' => Json::encode($payload),
            'explain_vi' => $explain,
            'score' => $score,
        ], ['id' => (int) $q['id']]);

        self::touch($q, $r);
        Audit::log($r, 'question.update', 'question', (int) $q['id'], ['type' => $type]);
        return self::row((int) $q['id']);
    }

    /** DELETE /admin/questions/{id} */
    public function delete(Request $r): Response
    {
        $q = self::find($r->id());
        Db::exec('DELETE FROM questions WHERE id = ?', [(int) $q['id']]);
        self::touch($q, $r);
        Audit::log($r, 'question.delete', 'question', (int) $q['id'], ['part' => $q['part']]);
        return Response::noContent();
    }

    /** PUT /admin/lessons/{id}/questions/order */
    public function reorder(Request $r): Response
    {
        $lesson = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);
        $part = $v->in('part', 'Phần', ['bt', 'kt'], 'bt');
        $ids = $v->ids('ids', 'Danh sách câu');
        $v->done();

        Db::tx(static function () use ($ids, $lesson, $part): void {
            foreach ($ids as $i => $id) {
                Db::exec(
                    'UPDATE questions SET sort_order = ? WHERE id = ? AND lesson_id = ? AND part = ?',
                    [$i + 1, $id, (int) $lesson['id'], $part],
                );
            }
        });

        ContentBuilder::forgetLesson((int) $lesson['id'], (int) $lesson['level_id']);
        return Response::noContent();
    }

    /**
     * POST /admin/lessons/{id}/questions/copy-from-bt
     * Chép câu từ phần Bài tập sang Kiểm tra. Dạng `audio` và `pinyin` không có ở Kiểm tra nên bị bỏ qua.
     */
    public function copyFromBt(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);
        $ids = $v->ids('questionIds', 'Danh sách câu');
        $v->done();

        $rows = Db::all(
            "SELECT * FROM questions WHERE part = 'bt' AND lesson_id = ? AND id IN (" . Db::in($ids) . ') ORDER BY sort_order',
            [(int) $lesson['id'], ...$ids],
        );

        $sort = (int) Db::val("SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE part = 'kt' AND lesson_id = ?", [(int) $lesson['id']]);
        $copied = 0;
        $skipped = [];

        foreach ($rows as $q) {
            if (!in_array((string) $q['type'], QuestionGrader::KT_TYPES, true)) {
                $skipped[] = ['id' => (int) $q['id'], 'type' => (string) $q['type']];
                continue;
            }
            $payload = ContentBuilder::json($q['payload'], []);
            // Ở Kiểm tra, câu điền chỗ trống không hiện nghĩa tiếng Việt (06 §2.6).
            if ($q['type'] === 'blank') {
                $payload['showMeaning'] = false;
            }
            Db::insert('questions', [
                'part' => 'kt',
                'lesson_id' => (int) $lesson['id'],
                'type' => (string) $q['type'],
                'payload' => Json::encode($payload),
                'explain_vi' => $q['explain_vi'],
                'score' => (float) $q['score'],
                'sort_order' => ++$sort,
            ]);
            $copied++;
        }

        ContentWriter::touch((int) $lesson['id'], $r);
        Audit::log($r, 'question.copy', 'lesson', (int) $lesson['id'], ['copied' => $copied]);
        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /** PUT /admin/lessons/{id}/test-config */
    public function testConfig(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);

        $set = [
            'duration_sec' => $v->int('durationSec', 'Thời gian làm bài', 30, 7200, false, 300),
            'pass_score' => $v->num('passScore', 'Điểm đạt', 0, 1000, 6),
            'listen_limit' => $v->int('listenLimit', 'Số lần nghe', 1, 10, false, 2),
            'attempts_limit' => $v->int('attemptsLimit', 'Số lần làm', 1, 50, false),
            'shuffle_questions' => $v->bool('shuffleQuestions', true) ? 1 : 0,
            'shuffle_options' => $v->bool('shuffleOptions', true) ? 1 : 0,
            'auto_submit' => $v->bool('autoSubmit', true) ? 1 : 0,
            'show_answers_after' => $v->bool('showAnswersAfter', true) ? 1 : 0,
        ];
        $v->done();

        Db::exec(
            'INSERT INTO test_configs (lesson_id, duration_sec, pass_score, listen_limit, attempts_limit,
                                       shuffle_questions, shuffle_options, auto_submit, show_answers_after)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE duration_sec = VALUES(duration_sec), pass_score = VALUES(pass_score),
                 listen_limit = VALUES(listen_limit), attempts_limit = VALUES(attempts_limit),
                 shuffle_questions = VALUES(shuffle_questions), shuffle_options = VALUES(shuffle_options),
                 auto_submit = VALUES(auto_submit), show_answers_after = VALUES(show_answers_after)',
            [(int) $lesson['id'], ...array_values($set)],
        );

        ContentBuilder::forgetLesson((int) $lesson['id'], (int) $lesson['level_id']);
        Audit::log($r, 'test_config.update', 'lesson', (int) $lesson['id'], $set);
        return PartContent::testConfig((int) $lesson['id']);
    }

    /* ------------------------------------------------------------------ */
    /* Đề thi thử                                                          */
    /* ------------------------------------------------------------------ */

    /** GET /admin/levels/{id}/mock-tests */
    public function mockTests(Request $r): array
    {
        $rows = Db::all(
            'SELECT m.*, COUNT(q.id) AS qn FROM mock_tests m
             LEFT JOIN questions q ON q.mock_test_id = m.id
             WHERE m.level_id = ? GROUP BY m.id ORDER BY m.sort_order, m.id',
            [$r->id()],
        );
        return array_map(static fn (array $m): array => [
            'id' => (int) $m['id'],
            'title' => (string) $m['title'],
            'status' => (string) $m['status'],
            'questionCount' => (int) $m['qn'],
            'durationSec' => (int) $m['duration_sec'],
            'maxPoints' => (int) $m['max_points'],
            'passPoints' => (int) $m['pass_points'],
            'listenLimit' => (int) $m['listen_limit'],
        ], $rows);
    }

    /** POST /admin/levels/{id}/mock-tests */
    public function createMock(Request $r): Response
    {
        $levelId = $r->id();
        if (Db::val('SELECT id FROM levels WHERE id = ?', [$levelId]) === null) {
            throw HttpError::notFound('Không có cấp này.');
        }

        $v = new Validator($r->body);
        $set = [
            'level_id' => $levelId,
            'title' => $v->str('title', 'Tên đề', min: 1, max: 80),
            'duration_sec' => $v->int('durationSec', 'Thời gian', 60, 10800, false, 3300),
            'max_points' => $v->int('maxPoints', 'Điểm tối đa', 1, 1000, false, 200),
            'pass_points' => $v->int('passPoints', 'Điểm đạt', 0, 1000, false, 120),
            'listen_limit' => $v->int('listenLimit', 'Số lần nghe', 1, 10, false, 2),
            'sort_order' => 1 + (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM mock_tests WHERE level_id = ?', [$levelId]),
        ];
        $copy = $v->arr('copyFromTests', 'Lấy câu từ bài kiểm tra', false);
        $v->done();

        $id = Db::insert('mock_tests', $set);
        $added = 0;
        if ($copy !== []) {
            $added = self::copyIntoMock($id, $levelId, array_map('intval', (array) ($copy['lessonIds'] ?? [])), (int) ($copy['count'] ?? 0));
        }

        ContentBuilder::forgetLevel($levelId);
        Audit::log($r, 'mock.create', 'mock_test', $id, ['title' => $set['title'], 'copied' => $added]);
        return Response::json(['id' => $id, 'copied' => $added], 201);
    }

    /** PATCH /admin/mock-tests/{id} */
    public function updateMock(Request $r): array
    {
        $m = self::mock($r->id());
        $v = new Validator($r->body);
        $set = [];
        foreach ([
            'title' => ['title', fn () => $v->str('title', 'Tên đề', min: 1, max: 80)],
            'status' => ['status', fn () => $v->in('status', 'Trạng thái', ['draft', 'published'])],
            'durationSec' => ['duration_sec', fn () => $v->int('durationSec', 'Thời gian', 60, 10800)],
            'maxPoints' => ['max_points', fn () => $v->int('maxPoints', 'Điểm tối đa', 1, 1000)],
            'passPoints' => ['pass_points', fn () => $v->int('passPoints', 'Điểm đạt', 0, 1000)],
            'listenLimit' => ['listen_limit', fn () => $v->int('listenLimit', 'Số lần nghe', 1, 10)],
        ] as $in => [$col, $read]) {
            if ($v->present($in)) {
                $set[$col] = $read();
            }
        }
        $v->done();

        if ($set !== []) {
            Db::update('mock_tests', $set, ['id' => (int) $m['id']]);
            ContentBuilder::forgetLevel((int) $m['level_id']);
            Audit::log($r, 'mock.update', 'mock_test', (int) $m['id'], $set);
        }
        return ['ok' => true];
    }

    /** DELETE /admin/mock-tests/{id} */
    public function deleteMock(Request $r): Response
    {
        $m = self::mock($r->id());
        Db::exec('DELETE FROM mock_tests WHERE id = ?', [(int) $m['id']]);
        ContentBuilder::forgetLevel((int) $m['level_id']);
        Audit::log($r, 'mock.delete', 'mock_test', (int) $m['id'], ['title' => $m['title']]);
        return Response::noContent();
    }

    /** GET /admin/mock-tests/{id}/questions */
    public function mockQuestions(Request $r): array
    {
        $m = self::mock($r->id());
        return ['questions' => self::rows('mock_test_id = ?', [(int) $m['id']], true)];
    }

    /** POST /admin/mock-tests/{id}/questions */
    public function createMockQuestion(Request $r): Response
    {
        $m = self::mock($r->id());
        [$type, $payload, $explain, $score] = $this->read($r, 'kt');

        $v = new Validator($r->body);
        $section = $v->in('section', 'Phần đề', ['listening', 'reading'], 'reading');
        $v->done();

        $id = Db::insert('questions', [
            'part' => 'mock',
            'mock_test_id' => (int) $m['id'],
            'type' => $type,
            'payload' => Json::encode($payload),
            'explain_vi' => $explain,
            'score' => $score,
            'section' => $section,
            'sort_order' => 1 + (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE mock_test_id = ?', [(int) $m['id']]),
        ]);

        ContentBuilder::forgetLevel((int) $m['level_id']);
        Audit::log($r, 'question.create', 'question', $id, ['mockTestId' => (int) $m['id'], 'type' => $type]);
        return Response::json(self::row($id), 201);
    }

    /** PUT /admin/mock-tests/{id}/questions/order */
    public function reorderMockQuestions(Request $r): Response
    {
        $m = self::mock($r->id());
        $v = new Validator($r->body);
        $ids = $v->ids('ids', 'Danh sách câu');
        $v->done();

        ContentWriter::reorder('questions', 'id', $ids, 'mock_test_id', (int) $m['id']);
        ContentBuilder::forgetLevel((int) $m['level_id']);
        return Response::noContent();
    }

    /** POST /admin/mock-tests/{id}/copy-from-tests */
    public function copyIntoMockTest(Request $r): array
    {
        $m = self::mock($r->id());
        $v = new Validator($r->body);
        $lessonIds = $v->arr('lessonIds', 'Danh sách bài', false);
        $count = (int) $v->int('count', 'Số câu', 1, 200, false, 60);
        $v->done();

        $added = self::copyIntoMock((int) $m['id'], (int) $m['level_id'], array_map('intval', $lessonIds), $count);
        ContentBuilder::forgetLevel((int) $m['level_id']);
        Audit::log($r, 'mock.copy', 'mock_test', (int) $m['id'], ['added' => $added]);
        return ['added' => $added];
    }

    /**
     * Lấy ngẫu nhiên các câu kiểm tra của cấp đưa vào đề thi thử.
     * @param list<int> $lessonIds rỗng = mọi bài của cấp
     */
    private static function copyIntoMock(int $mockId, int $levelId, array $lessonIds, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }
        $params = [$levelId];
        $filter = '';
        if ($lessonIds !== []) {
            $filter = ' AND q.lesson_id IN (' . Db::in($lessonIds) . ')';
            $params = [...$params, ...$lessonIds];
        }

        $rows = Db::all(
            "SELECT q.* FROM questions q JOIN lessons l ON l.id = q.lesson_id
             WHERE q.part = 'kt' AND l.level_id = ?$filter
             ORDER BY RAND() LIMIT " . $count,
            $params,
        );

        $sort = (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE mock_test_id = ?', [$mockId]);
        foreach ($rows as $q) {
            Db::insert('questions', [
                'part' => 'mock',
                'mock_test_id' => $mockId,
                'type' => (string) $q['type'],
                'payload' => (string) $q['payload'],
                'explain_vi' => $q['explain_vi'],
                'score' => (float) $q['score'],
                // Câu nghe xếp vào phần nghe, còn lại phần đọc.
                'section' => in_array($q['type'], ['listen', 'audio'], true) ? 'listening' : 'reading',
                'sort_order' => ++$sort,
            ]);
        }
        return count($rows);
    }

    /* ------------------------------------------------------------------ */
    /* Helper                                                              */
    /* ------------------------------------------------------------------ */

    /** @return array{0:string,1:array<string,mixed>,2:?string,3:float} */
    private function read(Request $r, string $part): array
    {
        $allowed = $part === 'bt' ? QuestionGrader::BT_TYPES : QuestionGrader::KT_TYPES;

        $v = new Validator($r->body);
        $type = (string) $v->in('type', 'Dạng câu', $allowed);
        $explain = $v->strOrNull('explain', 'Giải thích', 1000);
        $score = (float) $v->num('score', 'Điểm', 0.5, 20, 1);
        $payloadIn = $v->arr('payload', 'Nội dung câu');
        $v->done();

        return [$type, QuestionPayload::clean($type, $payloadIn), $explain, $score];
    }

    private static function part(string $p): string
    {
        if (!in_array($p, ['bt', 'kt'], true)) {
            throw HttpError::validation(['part' => 'Phần câu hỏi phải là bt hoặc kt.']);
        }
        return $p;
    }

    /** @return array<string,mixed> */
    private static function find(int $id): array
    {
        $q = Db::one('SELECT * FROM questions WHERE id = ?', [$id]);
        if ($q === null) {
            throw HttpError::notFound('Không tìm thấy câu hỏi.');
        }
        return $q;
    }

    /** @return array<string,mixed> */
    private static function mock(int $id): array
    {
        $m = Db::one('SELECT * FROM mock_tests WHERE id = ?', [$id]);
        if ($m === null) {
            throw HttpError::notFound('Không tìm thấy đề thi thử.');
        }
        return $m;
    }

    /** Xoá cache sau khi sửa một câu: câu của bài thì đếm lại bài, câu của đề thì xoá cache cấp. */
    private static function touch(array $q, Request $r): void
    {
        if ($q['lesson_id'] !== null) {
            ContentWriter::touch((int) $q['lesson_id'], $r);
            return;
        }
        $levelId = (int) Db::val('SELECT level_id FROM mock_tests WHERE id = ?', [(int) $q['mock_test_id']]);
        if ($levelId > 0) {
            ContentBuilder::forgetLevel($levelId);
        }
    }

    /** @return list<array<string,mixed>> */
    private static function rows(string $where, array $params, bool $withSection = false): array
    {
        return array_map(
            static fn (array $q): array => self::toApi($q, $withSection),
            Db::all("SELECT * FROM questions WHERE $where ORDER BY sort_order, id", $params),
        );
    }

    /** @return array<string,mixed> */
    private static function row(int $id): array
    {
        return self::toApi(self::find($id), true);
    }

    /** @param array<string,mixed> $q @return array<string,mixed> */
    private static function toApi(array $q, bool $withSection): array
    {
        $payload = ContentBuilder::json($q['payload'], []);
        $out = [
            'id' => (int) $q['id'],
            'type' => (string) $q['type'],
            'payload' => $payload,
            'explain' => $q['explain_vi'] !== null ? (string) $q['explain_vi'] : null,
            'score' => (float) $q['score'],
            'sortOrder' => (int) $q['sort_order'],
            'promptText' => QuestionGrader::promptText((string) $q['type'], $payload),
        ];
        if ($withSection && $q['section'] !== null) {
            $out['section'] = (string) $q['section'];
        }
        return $out;
    }
}
