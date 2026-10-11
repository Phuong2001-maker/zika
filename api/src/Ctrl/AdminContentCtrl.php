<?php
declare(strict_types=1);

namespace Zika\Ctrl;

use Zika\Auth;
use Zika\Db;
use Zika\Http\ApiError;
use Zika\Http\Req;
use Zika\Http\Res;
use Zika\Util;

/**
 * Soạn nội dung trong khu Quản trị.
 *
 * Trạng thái 6 phần của mỗi bài được tính lại sau mỗi lần lưu (hàm refreshParts)
 * nên ma trận "Đủ / Thiếu / Nháp" ở trang Bài học HSK luôn đúng mà không cần
 * admin tự đánh dấu.
 */
final class AdminContentCtrl
{
    /** GET /api/admin/levels/{hsk}/lessons — ma trận bài học của một cấp. */
    public static function lessons(Req $req): never
    {
        Auth::needStaff($req);
        $hsk = $req->paramInt('hsk');

        $rows = Db::all(
            "SELECT l.*,
                    (SELECT COUNT(*) FROM words w     WHERE w.lesson_id = l.id)                       AS n_study,
                    (SELECT COUNT(*) FROM questions q WHERE q.lesson_id = l.id AND q.bank = 'bt')     AS n_bt,
                    (SELECT COUNT(*) FROM questions q WHERE q.lesson_id = l.id AND q.bank = 'kt')     AS n_kt,
                    (SELECT COUNT(*) FROM hanzi h     WHERE h.lesson_id = l.id)                       AS n_lv,
                    (SELECT COUNT(*) FROM dialogue_lines dl
                       JOIN dialogues d ON d.id = dl.dialogue_id
                      WHERE d.lesson_id = l.id)                                                       AS n_ht,
                    (SELECT COUNT(*) FROM grammar_points g WHERE g.lesson_id = l.id)                  AS n_np
               FROM lessons l
              WHERE l.hsk = ?
              ORDER BY l.sort, l.no",
            [$hsk]
        );

        $states = [];
        foreach (Db::all(
            'SELECT lp.* FROM lesson_parts lp JOIN lessons l ON l.id = lp.lesson_id WHERE l.hsk = ?',
            [$hsk]
        ) as $r) {
            $states[(int) $r['lesson_id']][$r['part']] = $r['state'];
        }

        $lessons = [];
        $full    = 0;
        $missing = 0;
        $draft   = 0;

        foreach ($rows as $r) {
            $id    = (int) $r['id'];
            $parts = [];
            foreach (['study' => 'n_study', 'bt' => 'n_bt', 'kt' => 'n_kt',
                      'lv' => 'n_lv', 'ht' => 'n_ht', 'np' => 'n_np'] as $part => $col) {
                $parts[$part] = [
                    'count' => (int) $r[$col],
                    'state' => $states[$id][$part] ?? ((int) $r[$col] > 0 ? 'ready' : 'missing'),
                ];
            }

            $isFull = !in_array('missing', array_column($parts, 'state'), true);
            if ($r['status'] === 'draft') {
                $draft++;
            } elseif ($isFull) {
                $full++;
            } else {
                $missing++;
            }

            $lessons[] = [
                'id'     => $id,
                'no'     => (int) $r['no'],
                'title'  => $r['title'],
                'sample' => $r['sample'],
                'status' => $r['status'],
                'sort'   => (int) $r['sort'],
                'parts'  => $parts,
                'full'   => $isFull,
            ];
        }

        Res::ok([
            'hsk'     => $hsk,
            'summary' => ['lessons' => count($lessons), 'full' => $full, 'missing' => $missing, 'draft' => $draft],
            'lessons' => $lessons,
            'exam'    => Db::one('SELECT * FROM mock_exams WHERE hsk = ? LIMIT 1', [$hsk]),
        ]);
    }

    /** POST /api/admin/lessons, PATCH /api/admin/lessons/{id} */
    public static function saveLesson(Req $req): never
    {
        Auth::needStaff($req);
        $id = $req->paramInt('id');

        if ($id > 0) {
            $cur = Db::one('SELECT * FROM lessons WHERE id = ?', [$id]);
            if ($cur === null) {
                throw ApiError::notFound('Không có bài này.');
            }
            $data = [];
            foreach (['title' => 'title', 'sample' => 'sample'] as $in => $col) {
                if (array_key_exists($in, $req->body())) {
                    $data[$col] = $req->str($in);
                }
            }
            if (($st = $req->str('status')) !== '' && in_array($st, ['draft', 'published'], true)) {
                $data['status'] = $st;
            }
            if (array_key_exists('no', $req->body())) {
                $data['no'] = max(1, $req->int('no'));
            }
            if (array_key_exists('sort', $req->body())) {
                $data['sort'] = max(0, $req->int('sort'));
            }

            if ($data !== []) {
                Db::update('lessons', $data, 'id', $id);
            }
            Res::ok(['id' => $id]);
        }

        $hsk = $req->int('hsk');
        if ($hsk < 1 || $hsk > 6) {
            throw ApiError::badRequest('Cấp HSK phải từ 1 đến 6.');
        }

        $no = $req->int('no');
        if ($no <= 0) {
            $no = 1 + (int) (Db::val('SELECT COALESCE(MAX(no), 0) FROM lessons WHERE hsk = ?', [$hsk]) ?? 0);
        }
        if (Db::val('SELECT id FROM lessons WHERE hsk = ? AND no = ?', [$hsk, $no]) !== null) {
            throw ApiError::conflict("HSK $hsk đã có Bài $no.");
        }

        $newId = Db::insert('lessons', [
            'hsk'    => $hsk,
            'no'     => $no,
            'title'  => $req->need('title'),
            'sample' => $req->str('sample'),
            'status' => 'draft',
            'sort'   => $no,
        ]);
        self::refreshParts($newId);

        Res::created(['id' => $newId]);
    }

    /** DELETE /api/admin/lessons/{id} */
    public static function deleteLesson(Req $req): never
    {
        Auth::needStaff($req, adminOnly: true);
        Db::run('DELETE FROM lessons WHERE id = ?', [$req->paramInt('id')]);
        Res::ok();
    }

    /** PUT /api/admin/levels/{hsk}/lesson-order — body: { ids: [...] } (kéo để sắp xếp). */
    public static function reorderLessons(Req $req): never
    {
        Auth::needStaff($req);
        $hsk = $req->paramInt('hsk');
        $ids = array_map('intval', $req->arr('ids'));

        Db::tx(static function () use ($hsk, $ids): void {
            foreach ($ids as $i => $id) {
                Db::run('UPDATE lessons SET sort = ? WHERE id = ? AND hsk = ?', [$i + 1, $id, $hsk]);
            }
        });

        Res::ok();
    }

    // ───────────────────────── Từ vựng ─────────────────────────

    /** GET /api/admin/words?hsk=2 — trang Từ vựng HSK (danh sách cấp + từ của cấp đang chọn). */
    public static function wordsOverview(Req $req): never
    {
        Auth::needStaff($req);
        $hsk = $req->qInt('hsk', 1);

        $levels = Db::all(
            'SELECT lv.*,
                    (SELECT COUNT(*) FROM words w JOIN lessons l ON l.id = w.lesson_id WHERE l.hsk = lv.hsk) AS entered
               FROM levels lv ORDER BY lv.hsk'
        );

        $words = Db::all(
            'SELECT w.*, l.no AS lesson_no, l.title AS lesson_title
               FROM words w JOIN lessons l ON l.id = w.lesson_id
              WHERE l.hsk = ?
              ORDER BY l.sort, l.no, w.sort, w.id',
            [$hsk]
        );

        Res::ok([
            'levels' => array_map(static fn($l) => [
                'hsk'        => (int) $l['hsk'],
                'sample'     => $l['sample'],
                'visible'    => (bool) $l['visible'],
                'entered'    => (int) $l['entered'],
                'totalWords' => (int) $l['total_words'],
            ], $levels),
            'hsk'   => $hsk,
            'words' => array_map(static fn($w) => [
                'id'          => (int) $w['id'],
                'lessonId'    => (int) $w['lesson_id'],
                'lessonNo'    => (int) $w['lesson_no'],
                'lessonTitle' => $w['lesson_title'],
                'hz'          => $w['hz'],
                'pinyin'      => $w['pinyin'],
                'pinyinParts' => Util::pinyinParts($w['pinyin']),
                'hv'          => $w['hv'],
                'meaning'     => $w['meaning'],
                'wordClass'   => $w['word_class'],
            ], $words),
        ]);
    }

    /** GET /api/admin/lessons/{id}/words */
    public static function lessonWords(Req $req): never
    {
        Auth::needStaff($req);
        $lesson = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);

        $words = Db::all('SELECT * FROM words WHERE lesson_id = ? ORDER BY sort, id', [$lesson['id']]);
        $exs   = [];
        if ($words !== []) {
            $ids = array_column($words, 'id');
            $in  = implode(',', array_fill(0, count($ids), '?'));
            foreach (Db::all("SELECT * FROM word_examples WHERE word_id IN ($in) ORDER BY word_id, sort", $ids) as $e) {
                $exs[(int) $e['word_id']][] = [
                    'id'      => (int) $e['id'],
                    'pre'     => $e['pre'],
                    'target'  => $e['target'],
                    'post'    => $e['post'],
                    'pinyin'  => $e['pinyin'],
                    'meaning' => $e['meaning'],
                ];
            }
        }

        Res::ok([
            'lesson' => ContentCtrl::lessonBrief($lesson),
            'words'  => array_map(static fn($w) => [
                'id'        => (int) $w['id'],
                'hz'        => $w['hz'],
                'pinyin'    => $w['pinyin'],
                'hv'        => $w['hv'],
                'meaning'   => $w['meaning'],
                'wordClass' => $w['word_class'],
                'sort'      => (int) $w['sort'],
                'examples'  => $exs[(int) $w['id']] ?? [],
            ], $words),
        ]);
    }

    /**
     * PUT /api/admin/lessons/{id}/words
     * body: { words: [{ id?, hz, pinyin, hv, meaning, wordClass, examples: [...] }] }
     *
     * Lưu cả bộ từ của một bài trong một transaction: từ nào biến mất khỏi danh
     * sách gửi lên thì bị xoá, nhờ vậy giao diện chỉ cần gửi trạng thái cuối.
     */
    public static function saveWords(Req $req): never
    {
        Auth::needStaff($req);
        $lesson   = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);
        $lessonId = (int) $lesson['id'];
        $items    = $req->arr('words');

        Db::tx(static function () use ($items, $lessonId): void {
            $keep = [];

            foreach ($items as $i => $w) {
                $hz = trim((string) ($w['hz'] ?? ''));
                if ($hz === '') {
                    continue;
                }
                $row = [
                    'lesson_id'  => $lessonId,
                    'hz'         => mb_substr($hz, 0, 20),
                    'pinyin'     => mb_substr(trim((string) ($w['pinyin'] ?? '')), 0, 60),
                    'hv'         => mb_substr(trim((string) ($w['hv'] ?? '')), 0, 60),
                    'meaning'    => mb_substr(trim((string) ($w['meaning'] ?? '')), 0, 190),
                    'word_class' => mb_substr(trim((string) ($w['wordClass'] ?? '')), 0, 30),
                    'sort'       => $i + 1,
                ];

                $id = (int) ($w['id'] ?? 0);
                if ($id > 0 && Db::val('SELECT id FROM words WHERE id = ? AND lesson_id = ?', [$id, $lessonId]) !== null) {
                    Db::update('words', $row, 'id', $id);
                } else {
                    $id = Db::insert('words', $row);
                }
                $keep[] = $id;

                // Ví dụ: xoá hết rồi ghi lại cho đơn giản, mỗi từ chỉ có 1-3 ví dụ.
                Db::run('DELETE FROM word_examples WHERE word_id = ?', [$id]);
                foreach ((array) ($w['examples'] ?? []) as $j => $e) {
                    $target = trim((string) ($e['target'] ?? ''));
                    $pre    = trim((string) ($e['pre'] ?? ''));
                    $post   = trim((string) ($e['post'] ?? ''));
                    if ($target === '' && $pre === '' && $post === '') {
                        continue;
                    }
                    Db::insert('word_examples', [
                        'word_id' => $id,
                        'pre'     => mb_substr($pre, 0, 190),
                        'target'  => mb_substr($target, 0, 40),
                        'post'    => mb_substr($post, 0, 190),
                        'pinyin'  => mb_substr(trim((string) ($e['pinyin'] ?? '')), 0, 255),
                        'meaning' => mb_substr(trim((string) ($e['meaning'] ?? '')), 0, 255),
                        'sort'    => $j + 1,
                    ]);
                }
            }

            if ($keep === []) {
                Db::run('DELETE FROM words WHERE lesson_id = ?', [$lessonId]);
            } else {
                $in = implode(',', array_fill(0, count($keep), '?'));
                Db::run(
                    "DELETE FROM words WHERE lesson_id = ? AND id NOT IN ($in)",
                    array_merge([$lessonId], $keep)
                );
            }
        });

        self::refreshParts($lessonId);
        Res::ok(['count' => (int) Db::val('SELECT COUNT(*) FROM words WHERE lesson_id = ?', [$lessonId])]);
    }

    // ───────────────────── Bài tập & Kiểm tra ─────────────────────

    /** GET /api/admin/lessons/{id}/questions?bank=bt */
    public static function questions(Req $req): never
    {
        Auth::needStaff($req);
        $lesson = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);
        $bank   = self::bank($req->q('bank', 'bt'));

        $rows = Db::all(
            'SELECT * FROM questions WHERE lesson_id = ? AND bank = ? ORDER BY sort, id',
            [$lesson['id'], $bank]
        );

        Res::ok([
            'lesson'    => ContentCtrl::lessonBrief($lesson),
            'bank'      => $bank,
            'types'     => self::types($bank),
            'questions' => array_map(static fn($r) => [
                'id'      => (int) $r['id'],
                'kind'    => $r['kind'],
                'prompt'  => $r['prompt'],
                'score'   => (int) $r['score'],
                'sort'    => (int) $r['sort'],
                'explain' => $r['explain'],
                'data'    => Util::jsonDecode($r['data'], []),
                'answer'  => Util::jsonDecode($r['answer'], []),
            ], $rows),
        ]);
    }

    /**
     * PUT /api/admin/lessons/{id}/questions?bank=bt
     * body: { questions: [{ id?, kind, data, answer, explain, score }] }
     */
    public static function saveQuestions(Req $req): never
    {
        Auth::needStaff($req);
        $lesson   = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);
        $lessonId = (int) $lesson['id'];
        $bank     = self::bank($req->q('bank', 'bt'));
        $items    = $req->arr('questions');
        $allowed  = array_keys(self::types($bank));

        Db::tx(static function () use ($items, $lessonId, $bank, $allowed): void {
            $keep = [];

            foreach ($items as $i => $q) {
                $kind = (string) ($q['kind'] ?? '');
                if (!in_array($kind, $allowed, true)) {
                    throw ApiError::badRequest('Dạng câu không dùng được cho phần này: ' . $kind);
                }

                [$data, $answer] = self::normalizeQuestion($kind, (array) ($q['data'] ?? []), (array) ($q['answer'] ?? []));

                $row = [
                    'lesson_id' => $lessonId,
                    'bank'      => $bank,
                    'kind'      => $kind,
                    'prompt'    => mb_substr(self::promptOf($kind, $data), 0, 255),
                    'data'      => Util::jsonEncode($data),
                    'answer'    => Util::jsonEncode($answer),
                    'explain'   => trim((string) ($q['explain'] ?? '')) ?: null,
                    'score'     => max(1, min(10, (int) ($q['score'] ?? 1))),
                    'sort'      => $i + 1,
                ];

                $id = (int) ($q['id'] ?? 0);
                if ($id > 0 && Db::val(
                    'SELECT id FROM questions WHERE id = ? AND lesson_id = ? AND bank = ?',
                    [$id, $lessonId, $bank]
                ) !== null) {
                    Db::update('questions', $row, 'id', $id);
                } else {
                    $id = Db::insert('questions', $row);
                }
                $keep[] = $id;
            }

            if ($keep === []) {
                Db::run('DELETE FROM questions WHERE lesson_id = ? AND bank = ?', [$lessonId, $bank]);
            } else {
                $in = implode(',', array_fill(0, count($keep), '?'));
                Db::run(
                    "DELETE FROM questions WHERE lesson_id = ? AND bank = ? AND id NOT IN ($in)",
                    array_merge([$lessonId, $bank], $keep)
                );
            }
        });

        self::refreshParts($lessonId);
        Res::ok(['count' => (int) Db::val(
            'SELECT COUNT(*) FROM questions WHERE lesson_id = ? AND bank = ?',
            [$lessonId, $bank]
        )]);
    }

    // ───────────────────────── Luyện viết ─────────────────────────

    /** GET /api/admin/lessons/{id}/hanzi */
    public static function hanziList(Req $req): never
    {
        Auth::needStaff($req);
        $lesson = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);

        $rows = Db::all('SELECT * FROM hanzi WHERE lesson_id = ? ORDER BY sort, id', [$lesson['id']]);

        Res::ok([
            'lesson' => ContentCtrl::lessonBrief($lesson),
            'chars'  => array_map(static fn($r) => [
                'id'          => (int) $r['id'],
                'hz'          => $r['hz'],
                'pinyin'      => $r['pinyin'],
                'tone'        => Util::tone($r['pinyin']),
                'hv'          => $r['hv'],
                'meaning'     => $r['meaning'],
                'strokes'     => (int) $r['strokes'],
                'struct'      => $r['struct'],
                'wordHz'      => $r['word_hz'],
                'wordMeaning' => $r['word_meaning'],
                'sort'        => (int) $r['sort'],
            ], $rows),
        ]);
    }

    /** PUT /api/admin/lessons/{id}/hanzi — body: { chars: [...] } */
    public static function saveHanzi(Req $req): never
    {
        Auth::needStaff($req);
        $lesson   = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);
        $lessonId = (int) $lesson['id'];
        $items    = $req->arr('chars');

        Db::tx(static function () use ($items, $lessonId): void {
            $keep = [];

            foreach ($items as $i => $c) {
                $hz = trim((string) ($c['hz'] ?? ''));
                if ($hz === '') {
                    continue;
                }
                $row = [
                    'lesson_id'    => $lessonId,
                    'hz'           => mb_substr($hz, 0, 4),
                    'pinyin'       => mb_substr(trim((string) ($c['pinyin'] ?? '')), 0, 20),
                    'hv'           => mb_substr(trim((string) ($c['hv'] ?? '')), 0, 40),
                    'meaning'      => mb_substr(trim((string) ($c['meaning'] ?? '')), 0, 120),
                    'strokes'      => max(0, min(60, (int) ($c['strokes'] ?? 0))),
                    'struct'       => mb_substr(trim((string) ($c['struct'] ?? '')), 0, 120),
                    'word_hz'      => mb_substr(trim((string) ($c['wordHz'] ?? '')), 0, 20),
                    'word_meaning' => mb_substr(trim((string) ($c['wordMeaning'] ?? '')), 0, 120),
                    'sort'         => $i + 1,
                ];

                $id = (int) ($c['id'] ?? 0);
                if ($id > 0 && Db::val('SELECT id FROM hanzi WHERE id = ? AND lesson_id = ?', [$id, $lessonId]) !== null) {
                    Db::update('hanzi', $row, 'id', $id);
                } else {
                    $id = Db::insert('hanzi', $row);
                }
                $keep[] = $id;

                // Thứ tự nét (nếu giao diện gửi lên)
                if (isset($c['order']) && is_array($c['order'])) {
                    Db::run('DELETE FROM hanzi_strokes WHERE hanzi_id = ?', [$id]);
                    foreach ($c['order'] as $k => $s) {
                        $d = trim((string) ($s['d'] ?? ''));
                        if ($d === '') {
                            continue;
                        }
                        Db::insert('hanzi_strokes', [
                            'hanzi_id' => $id,
                            'ord'      => $k + 1,
                            'path_d'   => mb_substr($d, 0, 2000),
                            'name'     => mb_substr(trim((string) ($s['name'] ?? '')), 0, 40),
                        ]);
                    }
                }
            }

            if ($keep === []) {
                Db::run('DELETE FROM hanzi WHERE lesson_id = ?', [$lessonId]);
            } else {
                $in = implode(',', array_fill(0, count($keep), '?'));
                Db::run(
                    "DELETE FROM hanzi WHERE lesson_id = ? AND id NOT IN ($in)",
                    array_merge([$lessonId], $keep)
                );
            }
        });

        self::refreshParts($lessonId);
        Res::ok();
    }

    // ───────────────────────── Hội thoại ─────────────────────────

    /** GET /api/admin/lessons/{id}/dialogue */
    public static function dialogueGet(Req $req): never
    {
        Auth::needStaff($req);
        $lesson = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);

        $dlg = Db::one('SELECT * FROM dialogues WHERE lesson_id = ? ORDER BY id LIMIT 1', [$lesson['id']]);
        if ($dlg === null) {
            Res::ok([
                'lesson'   => ContentCtrl::lessonBrief($lesson),
                'title'    => '',
                'speakers' => [],
                'lines'    => [],
                'quiz'     => ['q' => '', 'opts' => ['', '', ''], 'ans' => 0],
            ]);
        }

        Res::ok([
            'lesson'   => ContentCtrl::lessonBrief($lesson),
            'id'       => (int) $dlg['id'],
            'title'    => $dlg['title'],
            'speakers' => Db::all(
                'SELECT id, name, ava, color, role FROM dialogue_speakers WHERE dialogue_id = ? ORDER BY sort, id',
                [$dlg['id']]
            ),
            'lines' => array_map(static fn($l) => [
                'id'        => (int) $l['id'],
                'speakerId' => (int) $l['speaker_id'],
                'pre'       => $l['pre'],
                'target'    => $l['target'],
                'post'      => $l['post'],
                'pinyin'    => $l['pinyin'],
                'meaning'   => $l['meaning'],
            ], Db::all('SELECT * FROM dialogue_lines WHERE dialogue_id = ? ORDER BY sort, id', [$dlg['id']])),
            'quiz' => [
                'q'    => $dlg['quiz_q'],
                'opts' => Util::jsonDecode($dlg['quiz_opts'], []),
                'ans'  => (int) $dlg['quiz_ans'],
            ],
        ]);
    }

    /**
     * PUT /api/admin/lessons/{id}/dialogue
     * body: { title, speakers: [{key, name, ava, color, role}], lines: [{speaker, pre, target, post, pinyin, meaning}], quiz }
     *
     * Dòng hội thoại trỏ tới người nói bằng "key" (tên nhân vật) thay vì id, nhờ
     * vậy giao diện thêm/xoá nhân vật không cần biết id trong CSDL.
     */
    public static function dialogueSave(Req $req): never
    {
        Auth::needStaff($req);
        $lesson   = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);
        $lessonId = (int) $lesson['id'];

        $title    = $req->str('title');
        $speakers = $req->arr('speakers');
        $lines    = $req->arr('lines');
        $quiz     = $req->body()['quiz'] ?? [];

        Db::tx(static function () use ($lessonId, $title, $speakers, $lines, $quiz): void {
            // Một bài chỉ có một hội thoại: xoá sạch rồi ghi lại (dữ liệu nhỏ).
            Db::run('DELETE FROM dialogues WHERE lesson_id = ?', [$lessonId]);

            $opts = array_values(array_filter(array_map(
                static fn($o) => mb_substr(trim((string) $o), 0, 190),
                (array) ($quiz['opts'] ?? [])
            ), static fn($o) => $o !== ''));

            $dlgId = Db::insert('dialogues', [
                'lesson_id' => $lessonId,
                'title'     => mb_substr($title, 0, 120),
                'quiz_q'    => mb_substr(trim((string) ($quiz['q'] ?? '')), 0, 255),
                'quiz_opts' => Util::jsonEncode($opts),
                'quiz_ans'  => max(0, (int) ($quiz['ans'] ?? 0)),
            ]);

            $map = [];
            foreach ($speakers as $i => $s) {
                $name = trim((string) ($s['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $map[$name] = Db::insert('dialogue_speakers', [
                    'dialogue_id' => $dlgId,
                    'name'        => mb_substr($name, 0, 40),
                    'ava'         => mb_substr(trim((string) ($s['ava'] ?? mb_substr($name, -1))), 0, 4),
                    'color'       => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($s['color'] ?? ''))
                                     ? (string) $s['color'] : '#DCEBFF',
                    'role'        => mb_substr(trim((string) ($s['role'] ?? '')), 0, 40),
                    'sort'        => $i + 1,
                ]);
            }

            foreach ($lines as $i => $l) {
                $who = trim((string) ($l['speaker'] ?? ''));
                if (!isset($map[$who])) {
                    continue;
                }
                Db::insert('dialogue_lines', [
                    'dialogue_id' => $dlgId,
                    'speaker_id'  => $map[$who],
                    'pre'         => mb_substr(trim((string) ($l['pre'] ?? '')), 0, 190),
                    'target'      => mb_substr(trim((string) ($l['target'] ?? '')), 0, 40),
                    'post'        => mb_substr(trim((string) ($l['post'] ?? '')), 0, 190),
                    'pinyin'      => mb_substr(trim((string) ($l['pinyin'] ?? '')), 0, 255),
                    'meaning'     => mb_substr(trim((string) ($l['meaning'] ?? '')), 0, 255),
                    'sort'        => $i + 1,
                ]);
            }
        });

        self::refreshParts($lessonId);
        Res::ok();
    }

    // ───────────────────────── Ngữ pháp ─────────────────────────

    /** GET /api/admin/lessons/{id}/grammar */
    public static function grammarGet(Req $req): never
    {
        Auth::needStaff($req);
        $lesson = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);

        $points = Db::all('SELECT * FROM grammar_points WHERE lesson_id = ? ORDER BY sort, id', [$lesson['id']]);
        $blocks = [];
        $forms  = [];

        if ($points !== []) {
            $ids = array_column($points, 'id');
            $in  = implode(',', array_fill(0, count($ids), '?'));
            foreach (Db::all("SELECT * FROM grammar_blocks WHERE point_id IN ($in) ORDER BY point_id, sort", $ids) as $b) {
                $blocks[(int) $b['point_id']][] = ['label' => $b['label'], 'hz' => $b['hz'], 'color' => $b['color']];
            }
            foreach (Db::all("SELECT * FROM grammar_forms WHERE point_id IN ($in) ORDER BY point_id, sort", $ids) as $f) {
                $forms[(int) $f['point_id']][] = [
                    'kind' => $f['kind'], 'formula' => $f['formula'], 'pre' => $f['pre'],
                    'target' => $f['target'], 'post' => $f['post'],
                    'pinyin' => $f['pinyin'], 'meaning' => $f['meaning'],
                ];
            }
        }

        Res::ok([
            'lesson' => ContentCtrl::lessonBrief($lesson),
            'points' => array_map(static fn($p) => [
                'id'         => (int) $p['id'],
                'title'      => $p['title'],
                'sub'        => $p['sub'],
                'explain'    => $p['explain'],
                'note'       => $p['note'],
                'answer'     => $p['answer'],
                'tiles'      => Util::jsonDecode($p['tiles'], []),
                'practiceVi' => $p['practice_vi'],
                'blocks'     => $blocks[(int) $p['id']] ?? [],
                'forms'      => $forms[(int) $p['id']] ?? [],
            ], $points),
        ]);
    }

    /** PUT /api/admin/lessons/{id}/grammar — body: { points: [...] } */
    public static function grammarSave(Req $req): never
    {
        Auth::needStaff($req);
        $lesson   = ContentCtrl::lesson($req->paramInt('id'), forAdmin: true);
        $lessonId = (int) $lesson['id'];
        $points   = $req->arr('points');

        Db::tx(static function () use ($lessonId, $points): void {
            Db::run('DELETE FROM grammar_points WHERE lesson_id = ?', [$lessonId]);

            foreach ($points as $i => $p) {
                $title = trim((string) ($p['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $tiles = array_values(array_filter(array_map(
                    static fn($t) => mb_substr(trim((string) $t), 0, 40),
                    (array) ($p['tiles'] ?? [])
                ), static fn($t) => $t !== ''));

                $pid = Db::insert('grammar_points', [
                    'lesson_id'   => $lessonId,
                    'title'       => mb_substr($title, 0, 80),
                    'sub'         => mb_substr(trim((string) ($p['sub'] ?? '')), 0, 120),
                    'explain'     => trim((string) ($p['explain'] ?? '')) ?: null,
                    'note'        => trim((string) ($p['note'] ?? '')) ?: null,
                    'answer'      => mb_substr(trim((string) ($p['answer'] ?? '')), 0, 120),
                    'tiles'       => Util::jsonEncode($tiles),
                    'practice_vi' => mb_substr(trim((string) ($p['practiceVi'] ?? '')), 0, 190),
                    'sort'        => $i + 1,
                ]);

                foreach ((array) ($p['blocks'] ?? []) as $k => $b) {
                    $label = trim((string) ($b['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    Db::insert('grammar_blocks', [
                        'point_id' => $pid,
                        'label'    => mb_substr($label, 0, 40),
                        'hz'       => mb_substr(trim((string) ($b['hz'] ?? '')), 0, 40),
                        'color'    => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($b['color'] ?? ''))
                                      ? (string) $b['color'] : '#DCEBFF',
                        'sort'     => $k + 1,
                    ]);
                }

                foreach ((array) ($p['forms'] ?? []) as $k => $f) {
                    Db::insert('grammar_forms', [
                        'point_id' => $pid,
                        'kind'     => mb_substr(trim((string) ($f['kind'] ?? '')), 0, 40),
                        'formula'  => mb_substr(trim((string) ($f['formula'] ?? '')), 0, 120),
                        'pre'      => mb_substr(trim((string) ($f['pre'] ?? '')), 0, 120),
                        'target'   => mb_substr(trim((string) ($f['target'] ?? '')), 0, 40),
                        'post'     => mb_substr(trim((string) ($f['post'] ?? '')), 0, 120),
                        'pinyin'   => mb_substr(trim((string) ($f['pinyin'] ?? '')), 0, 190),
                        'meaning'  => mb_substr(trim((string) ($f['meaning'] ?? '')), 0, 190),
                        'sort'     => $k + 1,
                    ]);
                }
            }
        });

        self::refreshParts($lessonId);
        Res::ok();
    }

    // ─────────────────────────── helper ───────────────────────────

    /** Danh sách dạng câu cho từng phần, dùng chung cho giao diện soạn bài. */
    public static function types(string $bank): array
    {
        if ($bank === 'bt') {
            return [
                'meaning' => 'Chọn nghĩa',
                'blank'   => 'Điền chỗ trống',
                'audio'   => 'Nghe chọn chữ',
                'pinyin'  => 'Chọn pinyin',
                'order'   => 'Sắp xếp câu',
                'trans'   => 'Dịch sang tiếng Việt',
                'tf'      => 'Đúng / Sai',
                'match'   => 'Nối từ',
            ];
        }
        return [
            'fill'    => 'Chọn từ điền câu',
            'listen'  => 'Nghe và chọn',
            'meaning' => 'Chọn nghĩa',
            'pick'    => 'Chọn câu đúng',
            'order'   => 'Sắp xếp câu',
            'trans'   => 'Dịch sang tiếng Việt',
            'tf'      => 'Đúng / Sai',
            'match'   => 'Nối từ',
            'tone'    => 'Thanh điệu',
            'toZh'    => 'Chọn câu tiếng Trung',
        ];
    }

    private static function bank(?string $b): string
    {
        $b = (string) $b;
        if (!in_array($b, ['bt', 'kt'], true)) {
            throw ApiError::badRequest('Phần câu hỏi phải là bt hoặc kt.');
        }
        return $b;
    }

    /**
     * Làm sạch data/answer theo từng dạng câu.
     * @return array{0:array,1:array}
     */
    private static function normalizeQuestion(string $kind, array $data, array $answer): array
    {
        $str = static fn($v, int $len = 255) => mb_substr(trim((string) $v), 0, $len);
        $out = [];
        $ans = [];

        if (in_array($kind, \Zika\Grader::CHOICE, true)) {
            $opts = array_values(array_filter(array_map(
                static fn($o) => $str($o, 190),
                (array) ($data['opts'] ?? [])
            ), static fn($o) => $o !== ''));

            if (count($opts) < 2) {
                throw ApiError::badRequest('Câu trắc nghiệm cần ít nhất 2 phương án.');
            }

            $out['opts']   = $opts;
            $out['hzOpts'] = !empty($data['hzOpts']);
            $idx           = (int) ($answer['ans'] ?? 0);
            $ans['ans']    = max(0, min(count($opts) - 1, $idx));

            foreach (['hz', 'ask', 'vi', 'audioText', 'pre', 'post'] as $f) {
                if (!empty($data[$f])) {
                    $out[$f] = $str($data[$f], 190);
                }
            }
        }

        switch ($kind) {
            case 'order':
                $tiles = array_values(array_filter(array_map(
                    static fn($t) => $str($t, 40),
                    (array) ($data['tiles'] ?? [])
                ), static fn($t) => $t !== ''));
                if (count($tiles) < 2) {
                    throw ApiError::badRequest('Câu sắp xếp cần ít nhất 2 thẻ chữ.');
                }
                $out['tiles'] = $tiles;
                $out['vi']    = $str($data['vi'] ?? '', 190);
                $text         = $str($answer['text'] ?? '', 190);
                // Đáp án phải ghép được từ đúng bộ thẻ, nếu không thì không ai
                // làm đúng được câu này.
                if ($text === '') {
                    $text = implode('', $tiles);
                }
                $ans['text'] = $text;
                break;

            case 'trans':
                $out['zh']     = $str($data['zh'] ?? '', 190);
                $out['pinyin'] = $str($data['pinyin'] ?? '', 255);
                $out['sample'] = $str($data['sample'] ?? '', 255);
                $kw = array_values(array_filter(array_map(
                    static fn($t) => $str($t, 60),
                    (array) ($answer['keywords'] ?? [])
                ), static fn($t) => $t !== ''));
                if ($kw === []) {
                    throw ApiError::badRequest('Câu dịch cần ít nhất một từ khoá để chấm.');
                }
                $ans['keywords'] = $kw;
                break;

            case 'tf':
                $out['passage'] = $str($data['passage'] ?? '', 255);
                $out['stmt']    = $str($data['stmt'] ?? '', 255);
                $ans['tf']      = !empty($answer['tf']);
                break;

            case 'match':
                $pairs = [];
                foreach ((array) ($data['pairs'] ?? []) as $p) {
                    $a = $str($p[0] ?? ($p['hz'] ?? ''), 40);
                    $b = $str($p[1] ?? ($p['vi'] ?? ''), 190);
                    if ($a !== '' && $b !== '') {
                        $pairs[] = [$a, $b];
                    }
                }
                if (count($pairs) < 2) {
                    throw ApiError::badRequest('Câu nối từ cần ít nhất 2 cặp.');
                }
                $out['pairs'] = $pairs;
                break;
        }

        return [$out, $ans];
    }

    /** Dòng mô tả ngắn hiện trong danh sách câu hỏi của admin. */
    private static function promptOf(string $kind, array $data): string
    {
        return match ($kind) {
            'meaning', 'pinyin', 'tone' => ($data['hz'] ?? '') . ' → ' . ($kind === 'meaning' ? 'nghĩa?' : ($kind === 'tone' ? 'thanh điệu?' : 'pinyin?')),
            'blank', 'fill'  => ($data['pre'] ?? '') . '___' . ($data['post'] ?? ''),
            'audio', 'listen' => 'Nghe: ' . ($data['audioText'] ?? ''),
            'order'  => implode(' / ', (array) ($data['tiles'] ?? [])),
            'trans'  => (string) ($data['zh'] ?? ''),
            'tf'     => '★ ' . ($data['stmt'] ?? ''),
            'match'  => implode(' · ', array_map(static fn($p) => (string) ($p[0] ?? ''), (array) ($data['pairs'] ?? []))),
            'pick', 'toZh' => (string) ($data['ask'] ?? ($data['vi'] ?? '')),
            default  => '',
        };
    }

    /**
     * Tính lại trạng thái 6 phần của một bài dựa vào dữ liệu thật đang có.
     * Ngưỡng "đủ" đặt thấp (có ít nhất 1 bản ghi) để admin soạn dần vẫn thấy tiến triển.
     */
    public static function refreshParts(int $lessonId): void
    {
        $counts = [
            'study' => (int) Db::val('SELECT COUNT(*) FROM words WHERE lesson_id = ?', [$lessonId]),
            'bt'    => (int) Db::val("SELECT COUNT(*) FROM questions WHERE lesson_id = ? AND bank = 'bt'", [$lessonId]),
            'kt'    => (int) Db::val("SELECT COUNT(*) FROM questions WHERE lesson_id = ? AND bank = 'kt'", [$lessonId]),
            'lv'    => (int) Db::val('SELECT COUNT(*) FROM hanzi WHERE lesson_id = ?', [$lessonId]),
            'ht'    => (int) Db::val(
                'SELECT COUNT(*) FROM dialogue_lines dl JOIN dialogues d ON d.id = dl.dialogue_id WHERE d.lesson_id = ?',
                [$lessonId]
            ),
            'np'    => (int) Db::val('SELECT COUNT(*) FROM grammar_points WHERE lesson_id = ?', [$lessonId]),
        ];

        // Mốc coi là soạn xong; dưới mốc mà vẫn có dữ liệu thì tính là nháp.
        $full = ['study' => 5, 'bt' => 5, 'kt' => 5, 'lv' => 2, 'ht' => 4, 'np' => 1];

        foreach ($counts as $part => $n) {
            $state = $n === 0 ? 'missing' : ($n >= $full[$part] ? 'ready' : 'draft');
            Db::run(
                'INSERT INTO lesson_parts (lesson_id, part, state) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE state = VALUES(state)',
                [$lessonId, $part, $state]
            );
        }
    }
}
