<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Pinyin;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\ContentBuilder;
use Zika\Services\ContentWriter;

/** Quản trị · Cấp, Bài học, Từ vựng (04 §10.3). Quyền E (biên tập + quản trị). */
final class ContentController
{
    private const POS = ['n', 'v', 'adj', 'adv', 'mw', 'phrase', 'num', 'pron', 'prep', 'conj', 'part', 'other'];

    /** Trần cho một lượt nhập từ Excel. */
    private const BULK_MAX = 500;

    /** Tên loại từ hiển thị trong trình soạn, cũng dùng khi nhập CSV bằng chữ tiếng Việt. */
    public const POS_VI = [
        'n' => 'danh từ', 'v' => 'động từ', 'adj' => 'tính từ', 'adv' => 'phó từ',
        'mw' => 'lượng từ', 'phrase' => 'cụm từ', 'num' => 'số từ', 'pron' => 'đại từ',
        'prep' => 'giới từ', 'conj' => 'liên từ', 'part' => 'trợ từ', 'other' => 'khác',
    ];

    /* ------------------------------------------------------------------ */
    /* Cấp                                                                 */
    /* ------------------------------------------------------------------ */

    /** GET /admin/levels */
    public function levels(Request $r): array
    {
        $rows = Db::all(
            'SELECT v.*,
                    (SELECT COUNT(*) FROM words w WHERE w.level_id = v.id) AS entered_words,
                    (SELECT COUNT(*) FROM lessons l WHERE l.level_id = v.id) AS lesson_count
             FROM levels v ORDER BY v.id',
        );
        return array_map(static fn (array $v): array => [
            'id' => (int) $v['id'],
            'name' => (string) $v['name'],
            'targetWords' => (int) $v['target_words'],
            'sampleText' => (string) $v['sample_text'],
            'visible' => (bool) $v['is_visible'],
            'enteredWords' => (int) $v['entered_words'],
            'lessonCount' => (int) $v['lesson_count'],
            'reviewPublishedOnly' => (bool) $v['review_published_only'],
            'reviewBtCount' => (int) $v['review_bt_count'],
        ], $rows);
    }

    /** PATCH /admin/levels/{id} */
    public function updateLevel(Request $r): array
    {
        $id = $r->id();
        if (Db::val('SELECT id FROM levels WHERE id = ?', [$id]) === null) {
            throw HttpError::notFound('Không có cấp này.');
        }

        $v = new Validator($r->body);
        $set = [];
        if ($v->present('visible')) {
            $set['is_visible'] = $v->bool('visible') ? 1 : 0;
        }
        if ($v->present('targetWords')) {
            $set['target_words'] = $v->int('targetWords', 'Số từ mục tiêu', 0, 10000);
        }
        if ($v->present('sampleText')) {
            $set['sample_text'] = $v->str('sampleText', 'Từ ví dụ', false, 0, 60);
        }
        if ($v->present('reviewPublishedOnly')) {
            $set['review_published_only'] = $v->bool('reviewPublishedOnly') ? 1 : 0;
        }
        if ($v->present('reviewBtCount')) {
            $set['review_bt_count'] = $v->int('reviewBtCount', 'Số câu bài tập tổng hợp', 5, 200);
        }
        $v->done();

        if ($set !== []) {
            Db::update('levels', $set, ['id' => $id]);
            ContentBuilder::forgetLevel($id);
            Audit::log($r, 'level.update', 'level', $id, $set);
        }
        return ['ok' => true];
    }

    /* ------------------------------------------------------------------ */
    /* Bài học                                                             */
    /* ------------------------------------------------------------------ */

    /** GET /admin/levels/{id}/lessons — ma trận 6 phần của cấp. */
    public function lessons(Request $r): array
    {
        $levelId = $r->id();
        $rows = Db::all(
            'SELECT l.*, u.display_name AS updated_by_name
             FROM lessons l LEFT JOIN users u ON u.id = l.updated_by
             WHERE l.level_id = ? ORDER BY l.lesson_no',
            [$levelId],
        );

        $lessons = [];
        $complete = 0;
        $missing = 0;
        $draft = 0;
        foreach ($rows as $l) {
            $counts = [
                'words' => (int) $l['cnt_words'], 'bt' => (int) $l['cnt_bt'], 'kt' => (int) $l['cnt_kt'],
                'lv' => (int) $l['cnt_lv'], 'ht' => (int) $l['cnt_ht'], 'np' => (int) $l['cnt_np'],
            ];
            $full = !in_array(0, $counts, true);
            if ($l['status'] === 'draft') {
                $draft++;
            } elseif ($full) {
                $complete++;
            } else {
                $missing++;
            }
            $lessons[] = [
                'id' => (int) $l['id'],
                'no' => (int) $l['lesson_no'],
                'title' => (string) $l['title'],
                'sampleText' => (string) $l['sample_text'],
                'status' => (string) $l['status'],
                'counts' => $counts,
                'updatedAt' => (string) $l['updated_at'],
                'updatedByName' => $l['updated_by_name'] !== null ? (string) $l['updated_by_name'] : null,
            ];
        }

        $mock = Db::one(
            'SELECT COUNT(DISTINCT m.id) AS n, COUNT(q.id) AS qn,
                    MAX(m.duration_sec) AS dur, MAX(m.pass_points) AS pass, MAX(m.max_points) AS maxp
             FROM mock_tests m LEFT JOIN questions q ON q.mock_test_id = m.id
             WHERE m.level_id = ?',
            [$levelId],
        ) ?? [];

        return [
            'lessons' => $lessons,
            'kpi' => ['total' => count($lessons), 'complete' => $complete, 'missing' => $missing, 'draft' => $draft],
            'mock' => [
                'count' => (int) ($mock['n'] ?? 0),
                'questionCount' => (int) ($mock['qn'] ?? 0),
                'durationMin' => (int) round(((int) ($mock['dur'] ?? 3300)) / 60),
                'passPoints' => (int) ($mock['pass'] ?? 120),
                'maxPoints' => (int) ($mock['maxp'] ?? 200),
            ],
        ];
    }

    /** POST /admin/levels/{id}/lessons — thêm bài mới ở cuối, trạng thái Nháp. */
    public function createLesson(Request $r): Response
    {
        $levelId = $r->id();
        if (Db::val('SELECT id FROM levels WHERE id = ?', [$levelId]) === null) {
            throw HttpError::notFound('Không có cấp này.');
        }

        $v = new Validator($r->body);
        $title = $v->str('title', 'Tên bài', min: 1, max: 120);
        $v->done();

        $no = 1 + (int) Db::val('SELECT COALESCE(MAX(lesson_no), 0) FROM lessons WHERE level_id = ?', [$levelId]);
        $id = Db::insert('lessons', [
            'level_id' => $levelId,
            'lesson_no' => $no,
            'title' => $title,
            'status' => 'draft',
            'updated_by' => $r->userId(),
        ]);

        ContentBuilder::forgetLevel($levelId);
        Audit::log($r, 'lesson.create', 'lesson', $id, ['levelId' => $levelId, 'no' => $no, 'title' => $title]);
        return Response::json(['id' => $id, 'no' => $no], 201);
    }

    /** GET /admin/lessons/{id} */
    public function lesson(Request $r): array
    {
        $l = ContentWriter::lesson($r->id());
        $name = $l['updated_by'] !== null
            ? Db::val('SELECT display_name FROM users WHERE id = ?', [(int) $l['updated_by']])
            : null;

        return [
            'id' => (int) $l['id'],
            'levelId' => (int) $l['level_id'],
            'no' => (int) $l['lesson_no'],
            'title' => (string) $l['title'],
            'sampleText' => (string) $l['sample_text'],
            'status' => (string) $l['status'],
            'counts' => [
                'words' => (int) $l['cnt_words'], 'bt' => (int) $l['cnt_bt'], 'kt' => (int) $l['cnt_kt'],
                'lv' => (int) $l['cnt_lv'], 'ht' => (int) $l['cnt_ht'], 'np' => (int) $l['cnt_np'],
            ],
            'updatedAt' => (string) $l['updated_at'],
            'updatedByName' => $name !== null ? (string) $name : null,
        ];
    }

    /** PATCH /admin/lessons/{id} */
    public function updateLesson(Request $r): array
    {
        $l = ContentWriter::lesson($r->id());

        $v = new Validator($r->body);
        $set = [];
        if ($v->present('title')) {
            $set['title'] = $v->str('title', 'Tên bài', min: 1, max: 120);
        }
        if ($v->present('status')) {
            $set['status'] = $v->in('status', 'Trạng thái', ['draft', 'published']);
        }
        if ($v->present('sampleText')) {
            $set['sample_text'] = $v->str('sampleText', 'Từ ví dụ', false, 0, 60);
        }
        $v->done();

        if ($set !== []) {
            $set['updated_by'] = $r->userId();
            Db::update('lessons', $set, ['id' => (int) $l['id']]);
            ContentBuilder::forgetLesson((int) $l['id'], (int) $l['level_id']);
            Audit::log($r, 'lesson.update', 'lesson', (int) $l['id'], $set);
        }
        return ['ok' => true];
    }

    /**
     * DELETE /admin/lessons/{id}
     * Phải gửi { confirm: "Bài 6" } cho khớp để tránh xoá nhầm.
     * Từ vựng không bị xoá mà quay về “chưa gắn bài” (khoá ngoại ON DELETE SET NULL);
     * câu hỏi, chữ luyện viết, hội thoại, ngữ pháp bị xoá theo (CASCADE).
     */
    public function deleteLesson(Request $r): Response
    {
        $l = ContentWriter::lesson($r->id());
        $expect = 'Bài ' . (int) $l['lesson_no'];

        $v = new Validator($r->body);
        $confirm = $v->str('confirm', 'Xác nhận', min: 1, max: 40);
        $v->done();
        if (trim($confirm) !== $expect) {
            throw HttpError::validation(['confirm' => "Gõ đúng “$expect” để xoá bài này."]);
        }

        Db::exec('DELETE FROM lessons WHERE id = ?', [(int) $l['id']]);
        ContentBuilder::forgetLesson((int) $l['id'], (int) $l['level_id']);
        Audit::log($r, 'lesson.delete', 'lesson', (int) $l['id'], ['no' => (int) $l['lesson_no'], 'title' => $l['title']]);
        return Response::noContent();
    }

    /** PUT /admin/levels/{id}/lessons/order — kéo thả đổi thứ tự, đánh lại số bài. */
    public function reorderLessons(Request $r): Response
    {
        $levelId = $r->id();
        $v = new Validator($r->body);
        $ids = $v->ids('ids', 'Danh sách bài');
        $v->done();

        Db::tx(static function () use ($ids, $levelId): void {
            // Đổi sang số âm trước để không đụng khoá duy nhất (level_id, lesson_no).
            foreach ($ids as $i => $id) {
                Db::exec('UPDATE lessons SET lesson_no = ? WHERE id = ? AND level_id = ?', [-($i + 1), $id, $levelId]);
            }
            foreach ($ids as $i => $id) {
                Db::exec('UPDATE lessons SET lesson_no = ? WHERE id = ? AND level_id = ?', [$i + 1, $id, $levelId]);
            }
        });

        ContentBuilder::forgetLevel($levelId);
        Audit::log($r, 'lesson.reorder', 'level', $levelId, ['ids' => $ids]);
        return Response::noContent();
    }

    /* ------------------------------------------------------------------ */
    /* Từ vựng                                                             */
    /* ------------------------------------------------------------------ */

    /** GET /admin/words?level=&lesson=&q=&page=&per= */
    public function words(Request $r): Response
    {
        $where = ['1 = 1'];
        $params = [];
        if (($level = $r->qInt('level')) > 0) {
            $where[] = 'w.level_id = ?';
            $params[] = $level;
        }
        if (($lesson = $r->qInt('lesson')) > 0) {
            $where[] = 'w.lesson_id = ?';
            $params[] = $lesson;
        }
        if (($q = trim((string) $r->q('q', ''))) !== '') {
            $where[] = '(w.hanzi LIKE ? OR w.pinyin LIKE ? OR w.meaning_vi LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $sql = implode(' AND ', $where);

        $per = $r->qInt('per', 20, 1, 200);
        $page = $r->qInt('page', 1, 1);
        $total = (int) Db::val("SELECT COUNT(*) FROM words w WHERE $sql", $params);

        $rows = Db::all(
            "SELECT w.*, l.lesson_no,
                    (SELECT COUNT(*) FROM word_examples e WHERE e.word_id = w.id) AS example_count
             FROM words w LEFT JOIN lessons l ON l.id = w.lesson_id
             WHERE $sql
             ORDER BY w.level_id, COALESCE(l.lesson_no, 9999), w.sort_order, w.id
             LIMIT $per OFFSET " . (($page - 1) * $per),
            $params,
        );

        return Response::json(
            array_map(static fn (array $w): array => [
                'id' => (int) $w['id'],
                'levelId' => (int) $w['level_id'],
                'lessonId' => $w['lesson_id'] !== null ? (int) $w['lesson_id'] : null,
                'lessonNo' => $w['lesson_no'] !== null ? (int) $w['lesson_no'] : null,
                'hanzi' => (string) $w['hanzi'],
                'pinyin' => (string) $w['pinyin'],
                'hanViet' => (string) $w['han_viet'],
                'meaning' => (string) $w['meaning_vi'],
                'pos' => (string) $w['pos'],
                'exampleCount' => (int) $w['example_count'],
            ], $rows),
            200,
            ['total' => $total, 'page' => $page, 'per' => $per],
        );
    }

    /** GET /admin/words/{id} — từ + câu ví dụ. */
    public function word(Request $r): array
    {
        $w = Db::one('SELECT * FROM words WHERE id = ?', [$r->id()]);
        if ($w === null) {
            throw HttpError::notFound('Không tìm thấy từ này.');
        }
        return self::wordToApi($w);
    }

    /** POST /admin/words */
    public function createWord(Request $r): Response
    {
        [$set, $examples] = $this->readWord($r, null);

        $id = Db::tx(static function () use ($set, $examples): int {
            $id = Db::insert('words', $set);
            self::saveExamples($id, $examples);
            return $id;
        });

        if ($set['lesson_id'] !== null) {
            ContentWriter::touch((int) $set['lesson_id'], $r);
        } else {
            ContentBuilder::forgetLevel((int) $set['level_id']);
        }
        Audit::log($r, 'word.create', 'word', $id, ['hanzi' => $set['hanzi']]);
        return Response::json(self::wordToApi(Db::one('SELECT * FROM words WHERE id = ?', [$id])), 201);
    }

    /** PUT /admin/words/{id} */
    public function updateWord(Request $r): array
    {
        $cur = Db::one('SELECT * FROM words WHERE id = ?', [$r->id()]);
        if ($cur === null) {
            throw HttpError::notFound('Không tìm thấy từ này.');
        }
        [$set, $examples] = $this->readWord($r, $cur);
        $id = (int) $cur['id'];

        Db::tx(static function () use ($id, $set, $examples): void {
            Db::update('words', $set, ['id' => $id]);
            Db::exec('DELETE FROM word_examples WHERE word_id = ?', [$id]);
            self::saveExamples($id, $examples);
        });

        // Đổi bài thì cả bài cũ và bài mới đều phải đếm lại.
        foreach (array_unique(array_filter([$cur['lesson_id'], $set['lesson_id']])) as $lessonId) {
            ContentWriter::touch((int) $lessonId, $r);
        }
        ContentBuilder::forgetLevel((int) $set['level_id']);
        Audit::log($r, 'word.update', 'word', $id, ['hanzi' => $set['hanzi']]);
        return self::wordToApi(Db::one('SELECT * FROM words WHERE id = ?', [$id]));
    }

    /** DELETE /admin/words/{id} */
    public function deleteWord(Request $r): Response
    {
        $w = Db::one('SELECT * FROM words WHERE id = ?', [$r->id()]);
        if ($w === null) {
            throw HttpError::notFound('Không tìm thấy từ này.');
        }
        Db::exec('DELETE FROM words WHERE id = ?', [(int) $w['id']]);

        if ($w['lesson_id'] !== null) {
            ContentWriter::touch((int) $w['lesson_id'], $r);
        }
        ContentBuilder::forgetLevel((int) $w['level_id']);
        Audit::log($r, 'word.delete', 'word', (int) $w['id'], ['hanzi' => $w['hanzi']]);
        return Response::noContent();
    }

    /** PUT /admin/lessons/{id}/words/order */
    public function reorderWords(Request $r): Response
    {
        $l = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);
        $ids = $v->ids('ids', 'Danh sách từ');
        $v->done();

        ContentWriter::reorder('words', 'id', $ids, 'lesson_id', (int) $l['id']);
        ContentWriter::touch((int) $l['id'], $r);
        return Response::noContent();
    }

    /**
     * POST /admin/words/bulk — dán nhiều từ một lúc.
     * Thiếu pinyin/nghĩa thì tra từ điển nội bộ; vẫn thiếu thì bỏ qua và báo lý do.
     */
    public function bulkWords(Request $r): array
    {
        $v = new Validator($r->body);
        $levelId = (int) $v->int('levelId', 'Cấp', 1, 6);
        $lessonId = $v->int('lessonId', 'Bài', 1, null, false);
        $mode = (string) $v->in('mode', 'Cách nhập', ['append', 'replace'], 'append');
        $items = $v->arr('items', 'Danh sách từ');
        $v->done();

        if ($lessonId !== null && (int) Db::val('SELECT level_id FROM lessons WHERE id = ?', [$lessonId]) !== $levelId) {
            throw HttpError::validation(['lessonId' => 'Bài này không thuộc cấp đã chọn.']);
        }
        if (count($items) > self::BULK_MAX) {
            throw HttpError::validation(['items' => 'Mỗi lần nhập tối đa ' . self::BULK_MAX . ' từ.']);
        }
        // Ghi đè chỉ xoá từ của đúng bài đang chọn; từ chưa gắn bài (lesson_id NULL) không đụng tới.
        if ($mode === 'replace' && $lessonId !== null) {
            Db::exec('DELETE FROM words WHERE lesson_id = ?', [$lessonId]);
        }

        $created = 0;
        $skipped = [];
        $sort = (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM words WHERE lesson_id = ?', [$lessonId]);

        foreach ($items as $raw) {
            $hanzi = trim((string) ($raw['hanzi'] ?? ''));
            if (!Pinyin::isHanzi($hanzi) || mb_strlen($hanzi) > 32) {
                $skipped[] = ['hanzi' => $hanzi, 'reason' => 'Không phải chữ Hán.'];
                continue;
            }
            if (Db::val('SELECT id FROM words WHERE level_id = ? AND hanzi = ?', [$levelId, $hanzi]) !== null) {
                $skipped[] = ['hanzi' => $hanzi, 'reason' => 'Cấp này đã có từ đó.'];
                continue;
            }

            $pinyin = trim((string) ($raw['pinyin'] ?? ''));
            $meaning = trim((string) ($raw['meaning'] ?? ''));
            $hanViet = trim((string) ($raw['hanViet'] ?? ''));
            $pos = (string) ($raw['pos'] ?? '');

            if ($pinyin === '' || $meaning === '') {
                $d = Db::one('SELECT * FROM dict_words WHERE hanzi = ? ORDER BY id LIMIT 1', [$hanzi]);
                if ($d !== null) {
                    $pinyin = $pinyin !== '' ? $pinyin : (string) $d['pinyin'];
                    $meaning = $meaning !== '' ? $meaning : (string) $d['meaning_vi'];
                    $hanViet = $hanViet !== '' ? $hanViet : (string) $d['han_viet'];
                    $pos = $pos !== '' ? $pos : (string) $d['pos'];
                }
            }
            if ($meaning === '') {
                $skipped[] = ['hanzi' => $hanzi, 'reason' => 'Chưa có nghĩa tiếng Việt.'];
                continue;
            }

            $wordId = Db::insert('words', [
                'level_id' => $levelId,
                'lesson_id' => $lessonId,
                'hanzi' => $hanzi,
                'pinyin' => mb_substr($pinyin, 0, 120),
                'han_viet' => mb_substr($hanViet, 0, 120),
                'meaning_vi' => mb_substr($meaning, 0, 255),
                'pos' => in_array($pos, self::POS, true) ? $pos : 'n',
                'sort_order' => ++$sort,
            ]);
            self::saveExamples($wordId, self::cleanExamples($raw['examples'] ?? [], $hanzi));
            $created++;
        }

        if ($lessonId !== null) {
            ContentWriter::touch($lessonId, $r);
        }
        ContentBuilder::forgetLevel($levelId);
        Audit::log($r, 'word.bulk', 'lesson', $lessonId, ['created' => $created, 'skipped' => count($skipped)]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    /* ------------------------------------------------------------------ */
    /* Helper                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Đọc và kiểm tra dữ liệu một từ (04 §10.3).
     * @param array<string,mixed>|null $cur dòng hiện tại khi sửa
     * @return array{0:array<string,mixed>,1:list<array<string,string>>}
     */
    private function readWord(Request $r, ?array $cur): array
    {
        $v = new Validator($r->body);
        $levelId = (int) $v->int('levelId', 'Cấp', 1, 6, $cur === null, $cur !== null ? (int) $cur['level_id'] : null);
        $lessonId = $v->int('lessonId', 'Bài', 1, null, false, $cur !== null && $cur['lesson_id'] !== null ? (int) $cur['lesson_id'] : null);
        $hanzi = $v->str('hanzi', 'Chữ Hán', min: 1, max: 32);
        $pinyin = $v->str('pinyin', 'Pinyin', false, 0, 120);
        $hanViet = $v->str('hanViet', 'Hán Việt', false, 0, 120);
        $meaning = $v->str('meaning', 'Nghĩa tiếng Việt', min: 1, max: 255);
        $pos = $v->in('pos', 'Loại từ', self::POS, 'n');
        $note = $v->strOrNull('note', 'Ghi chú', 2000);

        if ($hanzi !== '' && !Pinyin::isHanzi($hanzi)) {
            $v->error('hanzi', 'Chỉ nhập chữ Hán.');
        }
        if ($lessonId !== null) {
            $lv = Db::val('SELECT level_id FROM lessons WHERE id = ?', [$lessonId]);
            if ($lv === null) {
                $v->error('lessonId', 'Không có bài này.');
            } elseif ((int) $lv !== $levelId) {
                $v->error('lessonId', 'Bài này không thuộc cấp đã chọn.');
            }
        }

        // Mỗi cấp không được có 2 từ cùng chữ Hán.
        $dupe = Db::val(
            'SELECT id FROM words WHERE level_id = ? AND hanzi = ?' . ($cur !== null ? ' AND id <> ?' : ''),
            $cur !== null ? [$levelId, $hanzi, (int) $cur['id']] : [$levelId, $hanzi],
        );
        if ($dupe !== null) {
            $v->error('hanzi', 'Cấp này đã có từ “' . $hanzi . '”.');
        }

        $examples = [];
        foreach (array_slice($v->arr('examples', 'Câu ví dụ', false), 0, 3) as $i => $e) {
            $zh = trim((string) ($e['zh'] ?? ''));
            if ($zh === '') {
                continue;
            }
            if (mb_strlen($zh) > 255) {
                $v->error('examples', 'Câu ví dụ ' . ($i + 1) . ' dài quá 255 ký tự.');
                continue;
            }
            $highlight = trim((string) ($e['highlight'] ?? '')) ?: $hanzi;
            if (mb_strpos($zh, $highlight) === false) {
                $v->error('examples', 'Câu ví dụ ' . ($i + 1) . ' không chứa từ ' . $highlight . '.');
                continue;
            }
            $examples[] = [
                'zh' => $zh,
                'pinyin' => mb_substr(trim((string) ($e['pinyin'] ?? '')), 0, 500),
                'vi' => mb_substr(trim((string) ($e['vi'] ?? '')), 0, 500),
                'highlight' => $highlight === $hanzi ? null : mb_substr($highlight, 0, 32),
            ];
        }

        $v->done();

        $sort = $cur !== null
            ? (int) $cur['sort_order']
            : 1 + (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM words WHERE lesson_id = ?', [$lessonId]);

        return [[
            'level_id' => $levelId,
            'lesson_id' => $lessonId,
            'hanzi' => $hanzi,
            'pinyin' => $pinyin,
            'han_viet' => $hanViet,
            'meaning_vi' => $meaning,
            'pos' => $pos,
            'note' => $note,
            'sort_order' => $sort,
        ], $examples];
    }

    /** @param list<array<string,string|null>> $examples */
    /**
     * Làm sạch câu ví dụ khi nhập hàng loạt. Khác readWord(): ví dụ sai ở đây chỉ bị bỏ chứ không
     * làm hỏng cả từ — nhập 200 từ không nên dừng vì một câu ví dụ gõ thiếu chữ.
     * @param mixed $in
     * @return list<array<string,mixed>>
     */
    private static function cleanExamples(mixed $in, string $hanzi): array
    {
        $out = [];
        foreach (array_slice(is_array($in) ? $in : [], 0, 3) as $e) {
            $zh = trim((string) (is_array($e) ? ($e['zh'] ?? '') : ''));
            if ($zh === '' || mb_strlen($zh) > 255) {
                continue;
            }
            $highlight = trim((string) ($e['highlight'] ?? '')) ?: $hanzi;
            if (mb_strpos($zh, $highlight) === false) {
                $highlight = $hanzi;
            }
            $out[] = [
                'zh' => $zh,
                'pinyin' => mb_substr(trim((string) ($e['pinyin'] ?? '')), 0, 500),
                'vi' => mb_substr(trim((string) ($e['vi'] ?? '')), 0, 500),
                'highlight' => $highlight === $hanzi ? null : mb_substr($highlight, 0, 32),
            ];
        }
        return $out;
    }

    private static function saveExamples(int $wordId, array $examples): void
    {
        foreach ($examples as $i => $e) {
            Db::insert('word_examples', [
                'word_id' => $wordId,
                'sort_order' => $i + 1,
                'zh' => $e['zh'],
                'pinyin' => $e['pinyin'],
                'vi' => $e['vi'],
                'highlight' => $e['highlight'],
            ]);
        }
    }

    /** @param array<string,mixed> $w @return array<string,mixed> */
    public static function wordToApi(array $w): array
    {
        $id = (int) $w['id'];
        return [
            'id' => $id,
            'levelId' => (int) $w['level_id'],
            'lessonId' => $w['lesson_id'] !== null ? (int) $w['lesson_id'] : null,
            'hanzi' => (string) $w['hanzi'],
            'pinyin' => (string) $w['pinyin'],
            'hanViet' => (string) $w['han_viet'],
            'meaning' => (string) $w['meaning_vi'],
            'pos' => (string) $w['pos'],
            'note' => $w['note'] !== null ? (string) $w['note'] : null,
            'sortOrder' => (int) $w['sort_order'],
            'examples' => array_map(static fn (array $e): array => [
                'id' => (int) $e['id'],
                'zh' => (string) $e['zh'],
                'pinyin' => (string) $e['pinyin'],
                'vi' => (string) $e['vi'],
                'highlight' => $e['highlight'] !== null ? (string) $e['highlight'] : null,
            ], Db::all('SELECT * FROM word_examples WHERE word_id = ? ORDER BY sort_order, id', [$id])),
        ];
    }
}
