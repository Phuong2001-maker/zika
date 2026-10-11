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
 * Nội dung học: cấp HSK, bài, từ vựng, câu hỏi, chữ Hán, hội thoại, ngữ pháp.
 *
 * Quy ước chung:
 *  - 6 phần học dùng mã: study (lật thẻ), bt, kt, lv, ht, np.
 *  - Cấp có visible = 0 thì người học chỉ thấy trạng thái "Sắp có", không lấy
 *    được nội dung; admin vẫn xem được qua các API /api/admin/*.
 */
final class ContentCtrl
{
    public const PARTS = ['study', 'bt', 'kt', 'lv', 'ht', 'np'];

    /** GET /api/levels?part=study — 6 thẻ cấp HSK ở trang Bàn học và các trang Chọn cấp. */
    public static function levels(Req $req): never
    {
        $part = self::part($req->q('part', 'study'));
        $user = Auth::user($req);
        $uid  = $user === null ? 0 : (int) $user['id'];

        $levels = Db::all('SELECT * FROM levels ORDER BY hsk');

        // Số bài và số từ thật đang có trong CSDL của từng cấp.
        $counts = Db::all(
            "SELECT l.hsk,
                    COUNT(DISTINCT l.id) AS lessons,
                    COUNT(w.id)          AS words
               FROM lessons l
               LEFT JOIN words w ON w.lesson_id = l.id
              WHERE l.status = 'published'
              GROUP BY l.hsk"
        );
        $byHsk = [];
        foreach ($counts as $c) {
            $byHsk[(int) $c['hsk']] = $c;
        }

        // Tiến độ của người học, gộp theo cấp.
        $prog = [];
        if ($uid > 0) {
            $rows = Db::all(
                'SELECT l.hsk,
                        SUM(p.part = ? AND p.done_at IS NOT NULL) AS done,
                        SUM(p.part = ? AND p.position > 0)        AS started,
                        SUM(CASE WHEN p.part = ? THEN p.known ELSE 0 END) AS known,
                        MAX(CASE WHEN p.part = ? THEN p.updated_at END)   AS last_at
                   FROM user_part_progress p
                   JOIN lessons l ON l.id = p.lesson_id
                  WHERE p.user_id = ?
                  GROUP BY l.hsk',
                [$part, $part, $part, $part, $uid]
            );
            foreach ($rows as $r) {
                $prog[(int) $r['hsk']] = $r;
            }
        }

        $out = [];
        foreach ($levels as $lv) {
            $hsk     = (int) $lv['hsk'];
            $lessons = (int) ($byHsk[$hsk]['lessons'] ?? 0);
            $words   = (int) ($byHsk[$hsk]['words'] ?? 0);
            $p       = $prog[$hsk] ?? null;
            $done    = (int) ($p['done'] ?? 0);
            $known   = (int) ($p['known'] ?? 0);
            $soon    = !((bool) $lv['visible']) || $lessons === 0;

            $out[] = [
                'hsk'        => $hsk,
                'sample'     => $lv['sample'],
                'totalWords' => (int) $lv['total_words'],   // chuẩn HSK
                'words'       => $words,                    // đã soạn trong web
                'lessons'    => $lessons,
                'soon'       => $soon,
                'done'       => $done,
                'known'      => $known,
                'started'    => (int) ($p['started'] ?? 0),
                'lastAt'     => $p['last_at'] ?? null,
                'pct'        => $lessons > 0 ? (int) round($done / $lessons * 100) : 0,
            ];
        }

        Res::ok($out);
    }

    /** GET /api/levels/{hsk}/lessons?part=study — trang Chọn bài. */
    public static function lessons(Req $req): never
    {
        $hsk  = $req->paramInt('hsk');
        $part = self::part($req->q('part', 'study'));
        $user = Auth::user($req);
        $uid  = $user === null ? 0 : (int) $user['id'];

        $level = Db::one('SELECT * FROM levels WHERE hsk = ?', [$hsk]);
        if ($level === null) {
            throw ApiError::notFound('Không có cấp HSK ' . $hsk);
        }
        if (!(bool) $level['visible']) {
            throw ApiError::notFound('Cấp HSK ' . $hsk . ' chưa mở.');
        }

        $rows = Db::all(
            "SELECT l.id, l.no, l.title, l.sample,
                    (SELECT COUNT(*) FROM words w WHERE w.lesson_id = l.id)                       AS words,
                    (SELECT COUNT(*) FROM questions q WHERE q.lesson_id = l.id AND q.bank = 'bt') AS bt_count,
                    (SELECT COUNT(*) FROM questions q WHERE q.lesson_id = l.id AND q.bank = 'kt') AS kt_count,
                    (SELECT COUNT(*) FROM hanzi h WHERE h.lesson_id = l.id)                       AS lv_count,
                    (SELECT COUNT(*) FROM dialogues d WHERE d.lesson_id = l.id)                   AS ht_count,
                    (SELECT COUNT(*) FROM grammar_points g WHERE g.lesson_id = l.id)              AS np_count,
                    lp.state AS part_state
               FROM lessons l
               LEFT JOIN lesson_parts lp ON lp.lesson_id = l.id AND lp.part = ?
              WHERE l.hsk = ? AND l.status = 'published'
              ORDER BY l.sort, l.no",
            [$part, $hsk]
        );

        $prog = [];
        if ($uid > 0) {
            foreach (Db::all(
                'SELECT * FROM user_part_progress WHERE user_id = ? AND part = ?',
                [$uid, $part]
            ) as $r) {
                $prog[(int) $r['lesson_id']] = $r;
            }
        }

        $countCol = ['study' => 'words', 'bt' => 'bt_count', 'kt' => 'kt_count',
                     'lv' => 'lv_count', 'ht' => 'ht_count', 'np' => 'np_count'][$part];

        $lessons = [];
        foreach ($rows as $r) {
            $id    = (int) $r['id'];
            $count = (int) $r[$countCol];
            $p     = $prog[$id] ?? null;

            $lessons[] = [
                'id'        => $id,
                'no'        => (int) $r['no'],
                'title'     => $r['title'],
                'sample'    => $r['sample'],
                'count'     => $count,
                'ready'     => $count > 0 && ($r['part_state'] ?? 'ready') === 'ready',
                'position'  => (int) ($p['position'] ?? 0),
                'known'     => (int) ($p['known'] ?? 0),
                'bestScore' => isset($p['best_score']) ? (int) $p['best_score'] : null,
                'lastScore' => isset($p['last_score']) ? (int) $p['last_score'] : null,
                'doneAt'    => $p['done_at'] ?? null,
            ];
        }

        Res::ok([
            'hsk'     => $hsk,
            'sample'  => $level['sample'],
            'part'    => $part,
            'lessons' => $lessons,
            'exam'    => Db::one("SELECT * FROM mock_exams WHERE hsk = ? AND status = 'published' LIMIT 1", [$hsk]),
        ]);
    }

    /** GET /api/lessons/{id}/words — bộ thẻ của một bài. */
    public static function words(Req $req): never
    {
        $lesson = self::lesson($req->paramInt('id'));
        $user   = Auth::user($req);

        $words = Db::all(
            'SELECT * FROM words WHERE lesson_id = ? ORDER BY sort, id',
            [$lesson['id']]
        );
        Res::ok([
            'lesson' => self::lessonBrief($lesson),
            'words'  => self::shapeWords($words, $user === null ? 0 : (int) $user['id']),
        ]);
    }

    /** GET /api/levels/{hsk}/words — bộ thẻ Tổng hợp của cả cấp. */
    public static function levelWords(Req $req): never
    {
        $hsk  = $req->paramInt('hsk');
        $user = Auth::user($req);

        $words = Db::all(
            "SELECT w.*, l.no AS lesson_no, l.title AS lesson_title
               FROM words w
               JOIN lessons l ON l.id = w.lesson_id
              WHERE l.hsk = ? AND l.status = 'published'
              ORDER BY l.sort, l.no, w.sort, w.id",
            [$hsk]
        );
        if ($words === []) {
            throw ApiError::notFound('Cấp này chưa có từ nào.');
        }

        Res::ok([
            'lesson' => ['id' => 0, 'hsk' => $hsk, 'no' => 0, 'title' => 'Tổng hợp HSK ' . $hsk, 'sample' => ''],
            'words'  => self::shapeWords($words, $user === null ? 0 : (int) $user['id']),
        ]);
    }

    /** GET /api/lessons/{id}/exercises — Bài tập (có kèm đáp án vì chấm ngay tại máy). */
    public static function exercises(Req $req): never
    {
        $lesson = self::lesson($req->paramInt('id'));
        $rows   = Db::all(
            "SELECT * FROM questions WHERE lesson_id = ? AND bank = 'bt' ORDER BY sort, id",
            [$lesson['id']]
        );
        if ($rows === []) {
            throw ApiError::notFound('Bài này chưa có bài tập.');
        }

        Res::ok([
            'lesson'    => self::lessonBrief($lesson),
            'questions' => array_map(static fn($r) => self::shapeQuestion($r, true), $rows),
        ]);
    }

    /**
     * GET /api/lessons/{id}/test — Bài kiểm tra.
     *
     * Không gửi đáp án: giao diện chỉ thu câu trả lời rồi POST lên
     * /api/attempts/test để server chấm. Nhờ vậy xem source hay mở DevTools
     * cũng không thấy được đáp án.
     */
    public static function test(Req $req): never
    {
        $lesson = self::lesson($req->paramInt('id'));
        $rows   = Db::all(
            "SELECT * FROM questions WHERE lesson_id = ? AND bank = 'kt' ORDER BY sort, id",
            [$lesson['id']]
        );
        if ($rows === []) {
            throw ApiError::notFound('Bài này chưa có bài kiểm tra.');
        }

        $minutes = max(1, (int) ceil(count($rows) * 0.5)); // 10 câu ~ 5 phút
        Res::ok([
            'lesson'     => self::lessonBrief($lesson),
            'minutes'    => $minutes,
            'seconds'    => $minutes * 60,
            'questions'  => array_map(static fn($r) => self::shapeQuestion($r, false), $rows),
        ]);
    }

    /** GET /api/lessons/{id}/hanzi — Luyện viết. */
    public static function hanzi(Req $req): never
    {
        $lesson = self::lesson($req->paramInt('id'));
        $rows   = Db::all('SELECT * FROM hanzi WHERE lesson_id = ? ORDER BY sort, id', [$lesson['id']]);
        if ($rows === []) {
            throw ApiError::notFound('Bài này chưa có chữ để luyện viết.');
        }

        $ids     = array_column($rows, 'id');
        $strokes = [];
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (Db::all("SELECT * FROM hanzi_strokes WHERE hanzi_id IN ($in) ORDER BY hanzi_id, ord", $ids) as $s) {
                $strokes[(int) $s['hanzi_id']][] = [
                    'ord'  => (int) $s['ord'],
                    'd'    => $s['path_d'],
                    'name' => $s['name'],
                ];
            }
        }

        Res::ok([
            'lesson' => self::lessonBrief($lesson),
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
                'order'       => $strokes[(int) $r['id']] ?? [],
            ], $rows),
        ]);
    }

    /** GET /api/lessons/{id}/dialogue — Đọc hội thoại. */
    public static function dialogue(Req $req): never
    {
        $lesson = self::lesson($req->paramInt('id'));
        $dlg    = Db::one('SELECT * FROM dialogues WHERE lesson_id = ? ORDER BY id LIMIT 1', [$lesson['id']]);
        if ($dlg === null) {
            throw ApiError::notFound('Bài này chưa có hội thoại.');
        }

        $speakers = Db::all('SELECT * FROM dialogue_speakers WHERE dialogue_id = ? ORDER BY sort, id', [$dlg['id']]);
        $lines    = Db::all('SELECT * FROM dialogue_lines WHERE dialogue_id = ? ORDER BY sort, id', [$dlg['id']]);

        // Từ mới của bài, hiện ở cột bên phải màn hội thoại.
        $words = Db::all(
            'SELECT hz, meaning FROM words WHERE lesson_id = ? ORDER BY sort, id LIMIT 12',
            [$lesson['id']]
        );

        Res::ok([
            'lesson'   => self::lessonBrief($lesson),
            'title'    => $dlg['title'],
            'speakers' => array_map(static fn($s) => [
                'id'    => (int) $s['id'],
                'name'  => $s['name'],
                'ava'   => $s['ava'],
                'color' => $s['color'],
                'role'  => $s['role'],
            ], $speakers),
            'lines' => array_map(static fn($l) => [
                'id'        => (int) $l['id'],
                'speakerId' => (int) $l['speaker_id'],
                'pre'       => $l['pre'],
                'target'    => $l['target'],
                'post'      => $l['post'],
                'pinyin'    => $l['pinyin'],
                'meaning'   => $l['meaning'],
            ], $lines),
            'quiz' => $dlg['quiz_q'] === '' ? null : [
                'q'    => $dlg['quiz_q'],
                'opts' => Util::jsonDecode($dlg['quiz_opts'], []),
                'ans'  => (int) $dlg['quiz_ans'],
            ],
            'words' => array_map(static fn($w) => ['hz' => $w['hz'], 'vi' => $w['meaning']], $words),
        ]);
    }

    /** GET /api/lessons/{id}/grammar — Cấu trúc ngữ pháp. */
    public static function grammar(Req $req): never
    {
        $lesson = self::lesson($req->paramInt('id'));
        $points = Db::all('SELECT * FROM grammar_points WHERE lesson_id = ? ORDER BY sort, id', [$lesson['id']]);
        if ($points === []) {
            throw ApiError::notFound('Bài này chưa có phần ngữ pháp.');
        }

        $ids    = array_column($points, 'id');
        $in     = implode(',', array_fill(0, count($ids), '?'));
        $blocks = [];
        $forms  = [];

        foreach (Db::all("SELECT * FROM grammar_blocks WHERE point_id IN ($in) ORDER BY point_id, sort", $ids) as $b) {
            $blocks[(int) $b['point_id']][] = [
                'label' => $b['label'],
                'hz'    => $b['hz'],
                'color' => $b['color'],
            ];
        }
        foreach (Db::all("SELECT * FROM grammar_forms WHERE point_id IN ($in) ORDER BY point_id, sort", $ids) as $f) {
            $forms[(int) $f['point_id']][] = [
                'kind'    => $f['kind'],
                'formula' => $f['formula'],
                'pre'     => $f['pre'],
                'target'  => $f['target'],
                'post'    => $f['post'],
                'pinyin'  => $f['pinyin'],
                'meaning' => $f['meaning'],
            ];
        }

        Res::ok([
            'lesson' => self::lessonBrief($lesson),
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

    // ─────────────────────────── helper ───────────────────────────

    public static function part(?string $p): string
    {
        $p = (string) $p;
        if (!in_array($p, self::PARTS, true)) {
            throw ApiError::badRequest('Phần học không hợp lệ: ' . $p);
        }
        return $p;
    }

    /** Lấy bài, chặn bài nháp và bài thuộc cấp đang ẩn. */
    public static function lesson(int $id, bool $forAdmin = false): array
    {
        $row = Db::one(
            'SELECT l.*, lv.visible FROM lessons l JOIN levels lv ON lv.hsk = l.hsk WHERE l.id = ?',
            [$id]
        );
        if ($row === null) {
            throw ApiError::notFound('Không tìm thấy bài học.');
        }
        if (!$forAdmin && ($row['status'] !== 'published' || !(bool) $row['visible'])) {
            throw ApiError::notFound('Bài học này chưa mở.');
        }
        return $row;
    }

    public static function lessonBrief(array $l): array
    {
        return [
            'id'     => (int) $l['id'],
            'hsk'    => (int) $l['hsk'],
            'no'     => (int) $l['no'],
            'title'  => $l['title'],
            'sample' => $l['sample'],
        ];
    }

    /** Thêm pinyin đã tách thanh điệu, ví dụ và trạng thái học của từng từ. */
    private static function shapeWords(array $rows, int $userId): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $in  = implode(',', array_fill(0, count($ids), '?'));

        $exs = [];
        foreach (Db::all("SELECT * FROM word_examples WHERE word_id IN ($in) ORDER BY word_id, sort", $ids) as $e) {
            $exs[(int) $e['word_id']][] = [
                'pre'     => $e['pre'],
                'target'  => $e['target'],
                'post'    => $e['post'],
                'pinyin'  => $e['pinyin'],
                'meaning' => $e['meaning'],
            ];
        }

        $states = [];
        if ($userId > 0) {
            foreach (Db::all(
                "SELECT word_id, state FROM user_word_progress WHERE user_id = ? AND word_id IN ($in)",
                array_merge([$userId], $ids)
            ) as $s) {
                $states[(int) $s['word_id']] = $s['state'];
            }
        }

        return array_map(static function ($r) use ($exs, $states) {
            $id = (int) $r['id'];
            return [
                'id'         => $id,
                'hz'         => $r['hz'],
                'chars'      => preg_split('//u', $r['hz'], -1, PREG_SPLIT_NO_EMPTY) ?: [],
                'pinyin'     => $r['pinyin'],
                'pinyinParts' => Util::pinyinParts($r['pinyin']),
                'hv'         => $r['hv'],
                'meaning'    => $r['meaning'],
                'wordClass'  => $r['word_class'],
                'lessonNo'   => isset($r['lesson_no']) ? (int) $r['lesson_no'] : null,
                'examples'   => $exs[$id] ?? [],
                'state'      => $states[$id] ?? 'new',
            ];
        }, $rows);
    }

    /**
     * Chuẩn hoá một câu hỏi để gửi ra giao diện.
     * $withAnswer = false thì bỏ hẳn đáp án và lời giải.
     */
    public static function shapeQuestion(array $r, bool $withAnswer): array
    {
        $data = Util::jsonDecode($r['data'], []);
        $out  = [
            'id'    => (int) $r['id'],
            'kind'  => $r['kind'],
            'score' => (int) $r['score'],
        ] + (is_array($data) ? $data : []);

        // Câu nối từ: trộn cột nghĩa để thứ tự không trùng cột chữ Hán,
        // nhưng vẫn giữ nguyên key gốc ở server để chấm.
        if ($r['kind'] === 'match' && isset($out['pairs']) && is_array($out['pairs'])) {
            $left  = array_map(static fn($p) => $p[0] ?? '', $out['pairs']);
            $right = array_map(static fn($p) => $p[1] ?? '', $out['pairs']);
            $order = range(0, count($right) - 1);
            // Trộn theo id câu hỏi để mỗi lần tải lại vẫn cùng thứ tự
            // (người học không bị đảo đáp án giữa lúc đang làm).
            mt_srand((int) $r['id']);
            shuffle($order);
            mt_srand();

            $out['left']  = $left;
            $out['right'] = array_map(static fn($k) => $right[$k], $order);
            if ($withAnswer) {
                // key[i] = vị trí (trong cột phải đã trộn) của nghĩa đúng cho left[i]
                $out['key'] = array_map(static fn($i) => array_search($i, $order, true), range(0, count($left) - 1));
            }
            unset($out['pairs']);
        }

        if ($withAnswer) {
            $ans = Util::jsonDecode($r['answer'], []);
            $out['answer']  = is_array($ans) ? $ans : [];
            $out['explain'] = $r['explain'];
        }

        return $out;
    }
}
