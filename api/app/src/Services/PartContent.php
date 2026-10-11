<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Controllers\LearnController;
use Zika\Core\Db;
use Zika\Core\Pinyin;

/**
 * Nội dung 5 phần học còn lại: Bài tập, Kiểm tra, Luyện viết, Hội thoại, Ngữ pháp (04 §4.4–4.9).
 * Lật thẻ nằm ở ContentBuilder vì đã dựng từ Giai đoạn 0.
 *
 * LearnController::boot() gọi register() nếu lớp này tồn tại, nên chỉ cần thêm file là 5 phần
 * tự có endpoint, không phải sửa controller.
 */
final class PartContent
{
    /** Cài đặt mặc định khi bài chưa có dòng trong writing_configs / test_configs. */
    private const WRITING_DEFAULT = [
        'startMode' => 'trace', 'grid' => 'mi', 'passCount' => 2,
        'hintAfterMisses' => 2, 'showAnimation' => true, 'highlightRadical' => true,
    ];
    private const TEST_DEFAULT = [
        'durationSec' => 300, 'passScore' => 6.0, 'listenLimit' => 2, 'attemptsLimit' => null,
        'shuffleQuestions' => true, 'shuffleOptions' => true, 'autoSubmit' => true, 'showAnswersAfter' => true,
    ];

    public static function register(): void
    {
        LearnController::registerLesson('exercises', [self::class, 'exercises']);
        LearnController::registerLesson('test', [self::class, 'test']);
        LearnController::registerLesson('writing', [self::class, 'writing']);
        LearnController::registerLesson('dialogue', [self::class, 'dialogue']);
        LearnController::registerLesson('grammar', [self::class, 'grammar']);

        LearnController::registerReview('bt', [self::class, 'reviewBt']);
        LearnController::registerReview('kt', [self::class, 'reviewKt']);
        LearnController::registerReview('lv', [self::class, 'reviewLv']);
        LearnController::registerReview('ht', [self::class, 'reviewHt']);
        LearnController::registerReview('np', [self::class, 'reviewNp']);
    }

    /* ------------------------------------------------------------------ */
    /* Bài tập                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * GET /learn/lessons/{id}/exercises (04 §4.4).
     * Trả cả đáp án: Bài tập chấm ngay ở máy cho mượt, điểm vẫn được server chấm lại khi lưu lượt.
     * @return array<string,mixed>
     */
    public static function exercises(int $lessonId, bool $preview): array
    {
        $l = ContentBuilder::lesson($lessonId, $preview);
        return [
            'lesson' => ContentBuilder::lessonRef($l, $preview),
            'questions' => self::questionRows("part = 'bt' AND lesson_id = ?", [$lessonId]),
            'next' => ContentBuilder::nextLesson($l, $preview, 'cnt_bt'),
        ];
    }

    /** GET /learn/levels/{id}/review/bt — kho câu của cả cấp (04 §4.9). @return array<string,mixed> */
    public static function reviewBt(int $levelId, bool $preview): array
    {
        $lv = ContentBuilder::level($levelId, $preview);
        $ids = ContentBuilder::reviewLessonIds($lv, $preview);
        $in = Db::in($ids);
        return [
            'level' => ['id' => (int) $lv['id'], 'name' => (string) $lv['name']],
            'count' => (int) $lv['review_bt_count'],
            'questions' => self::questionRows("q.part = 'bt' AND q.lesson_id IN ($in)", $ids, true),
        ];
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string,mixed>>
     */
    private static function questionRows(string $where, array $params, bool $withLesson = false): array
    {
        $sql = $withLesson
            ? "SELECT q.*, l.lesson_no FROM questions q JOIN lessons l ON l.id = q.lesson_id WHERE $where ORDER BY l.lesson_no, q.sort_order, q.id"
            : "SELECT * FROM questions WHERE $where ORDER BY sort_order, id";

        return array_map(static function (array $q) use ($withLesson): array {
            $item = [
                'id' => (int) $q['id'],
                'type' => (string) $q['type'],
                'payload' => ContentBuilder::json($q['payload'], []),
                'explain' => ($q['explain_vi'] ?? '') !== '' ? (string) $q['explain_vi'] : null,
                'score' => (float) $q['score'],
            ];
            if ($withLesson) {
                $item['lessonNo'] = (int) $q['lesson_no'];
            }
            return $item;
        }, Db::all($sql, $params));
    }

    /* ------------------------------------------------------------------ */
    /* Kiểm tra                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * GET /learn/lessons/{id}/test (04 §4.5) — chỉ thông tin, KHÔNG có câu hỏi.
     * Câu hỏi chỉ đến trình duyệt qua POST /tests/start, sau khi đã tạo lượt làm bài.
     * @return array<string,mixed>
     */
    public static function test(int $lessonId, bool $preview): array
    {
        $l = ContentBuilder::lesson($lessonId, $preview);
        $rows = Db::all("SELECT type, score FROM questions WHERE part = 'kt' AND lesson_id = ? ORDER BY sort_order, id", [$lessonId]);

        $types = [];
        $max = 0.0;
        foreach ($rows as $q) {
            $max += (float) $q['score'];
            if (!in_array($q['type'], $types, true)) {
                $types[] = (string) $q['type'];
            }
        }

        return [
            'lesson' => ContentBuilder::lessonRef($l, $preview),
            'config' => self::testConfig($lessonId),
            'questionCount' => count($rows),
            'maxScore' => $max,
            'types' => $types,
            'next' => ContentBuilder::nextLesson($l, $preview, 'cnt_kt'),
        ];
    }

    /** GET /learn/levels/{id}/review/kt — danh sách đề thi thử (04 §4.9). @return array<string,mixed> */
    public static function reviewKt(int $levelId, bool $preview): array
    {
        $lv = ContentBuilder::level($levelId, $preview);
        $rows = Db::all(
            'SELECT m.*, COUNT(q.id) AS qn FROM mock_tests m
             LEFT JOIN questions q ON q.mock_test_id = m.id
             WHERE m.level_id = ? AND ' . ($preview ? '1 = 1' : "m.status = 'published'") . '
             GROUP BY m.id ORDER BY m.sort_order, m.id',
            [$levelId],
        );
        return [
            'level' => ['id' => (int) $lv['id'], 'name' => (string) $lv['name']],
            'mockTests' => array_values(array_map(static fn (array $m): array => [
                'id' => (int) $m['id'],
                'title' => (string) $m['title'],
                'questionCount' => (int) $m['qn'],
                'durationSec' => (int) $m['duration_sec'],
                'maxPoints' => (int) $m['max_points'],
                'passPoints' => (int) $m['pass_points'],
            ], array_filter($rows, static fn (array $m): bool => (int) $m['qn'] > 0 || $preview))),
        ];
    }

    /** @return array<string,mixed> cài đặt bài kiểm tra, có mặc định khi admin chưa chỉnh */
    public static function testConfig(int $lessonId): array
    {
        $c = Db::one('SELECT * FROM test_configs WHERE lesson_id = ?', [$lessonId]);
        if ($c === null) {
            return self::TEST_DEFAULT;
        }
        return [
            'durationSec' => (int) $c['duration_sec'],
            'passScore' => (float) $c['pass_score'],
            'listenLimit' => (int) $c['listen_limit'],
            'attemptsLimit' => $c['attempts_limit'] === null ? null : (int) $c['attempts_limit'],
            'shuffleQuestions' => (bool) $c['shuffle_questions'],
            'shuffleOptions' => (bool) $c['shuffle_options'],
            'autoSubmit' => (bool) $c['auto_submit'],
            'showAnswersAfter' => (bool) $c['show_answers_after'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Luyện viết                                                          */
    /* ------------------------------------------------------------------ */

    /** GET /learn/lessons/{id}/writing (04 §4.6). @return array<string,mixed> */
    public static function writing(int $lessonId, bool $preview): array
    {
        $l = ContentBuilder::lesson($lessonId, $preview);
        return [
            'lesson' => ContentBuilder::lessonRef($l, $preview),
            'config' => self::writingConfig($lessonId),
            'chars' => self::charRows('lc.lesson_id = ?', [$lessonId]),
            'next' => ContentBuilder::nextLesson($l, $preview, 'cnt_lv'),
        ];
    }

    /** GET /learn/levels/{id}/review/lv — mọi chữ của cấp, không trùng (04 §4.9). @return array<string,mixed> */
    public static function reviewLv(int $levelId, bool $preview): array
    {
        $lv = ContentBuilder::level($levelId, $preview);
        $ids = ContentBuilder::reviewLessonIds($lv, $preview);
        $in = Db::in($ids);

        $chars = self::charRows("lc.lesson_id IN ($in)", $ids, true);
        // Chữ xuất hiện ở nhiều bài: giữ lần đầu (bài có số nhỏ nhất).
        $seen = [];
        $out = [];
        foreach ($chars as $c) {
            if (isset($seen[$c['hanzi']])) {
                continue;
            }
            $seen[$c['hanzi']] = true;
            $out[] = $c;
        }

        return [
            'level' => ['id' => (int) $lv['id'], 'name' => (string) $lv['name']],
            'config' => self::writingConfig((int) ($ids[0] ?? 0)),
            'chars' => $out,
        ];
    }

    /** @return array<string,mixed> */
    public static function writingConfig(int $lessonId): array
    {
        $c = $lessonId > 0 ? Db::one('SELECT * FROM writing_configs WHERE lesson_id = ?', [$lessonId]) : null;
        if ($c === null) {
            return self::WRITING_DEFAULT;
        }
        return [
            'startMode' => (string) $c['start_mode'],
            'grid' => (string) $c['grid'],
            'passCount' => (int) $c['pass_count'],
            'hintAfterMisses' => (int) $c['hint_after_misses'],
            'showAnimation' => (bool) $c['show_animation'],
            'highlightRadical' => (bool) $c['highlight_radical'],
        ];
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string,mixed>>
     */
    private static function charRows(string $where, array $params, bool $withLesson = false): array
    {
        $rows = Db::all(
            "SELECT lc.id AS lesson_char_id, lc.lesson_id, lc.sort_order, l.lesson_no,
                    c.*, w.hanzi AS word_hanzi, w.meaning_vi AS word_meaning
             FROM lesson_chars lc
             JOIN characters c ON c.id = lc.char_id
             JOIN lessons l ON l.id = lc.lesson_id
             LEFT JOIN words w ON w.id = lc.source_word_id
             WHERE $where AND lc.is_visible = 1
             ORDER BY l.lesson_no, lc.sort_order, lc.id",
            $params,
        );

        return array_map(static function (array $c) use ($withLesson): array {
            $item = [
                'id' => (int) $c['id'],
                'hanzi' => (string) $c['hanzi'],
                'pinyin' => (string) $c['pinyin'],
                'tone' => Pinyin::tone((string) $c['pinyin']),
                'hanViet' => (string) $c['han_viet'],
                'meaning' => (string) $c['meaning_vi'],
                'strokes' => (int) $c['strokes'],
                'structure' => (string) $c['structure'],
                'radical' => $c['radical'] !== null && $c['radical'] !== '' ? (string) $c['radical'] : null,
                // Thêm ?v= theo lần sửa gần nhất để trình duyệt không dùng bản nét cũ.
                'strokeDataUrl' => $c['stroke_data_path'] !== null && $c['stroke_data_path'] !== ''
                    ? (string) $c['stroke_data_path'] . '?v=' . strtotime((string) $c['updated_at'])
                    : null,
                'word' => $c['word_hanzi'] !== null
                    ? ['hanzi' => (string) $c['word_hanzi'], 'meaning' => (string) $c['word_meaning']]
                    : null,
            ];
            if ($withLesson) {
                $item['lessonNo'] = (int) $c['lesson_no'];
            }
            return $item;
        }, $rows);
    }

    /* ------------------------------------------------------------------ */
    /* Hội thoại                                                           */
    /* ------------------------------------------------------------------ */

    /** GET /learn/lessons/{id}/dialogue (04 §4.7). @return array<string,mixed> */
    public static function dialogue(int $lessonId, bool $preview): array
    {
        $l = ContentBuilder::lesson($lessonId, $preview);
        $d = self::dialogueBlock($lessonId, true);

        return [
            'lesson' => ContentBuilder::lessonRef($l, $preview),
            'dialogue' => $d,
            'newWords' => $d === null ? [] : self::newWords($lessonId, $d['lines']),
            'next' => ContentBuilder::nextLesson($l, $preview, 'cnt_ht'),
        ];
    }

    /** GET /learn/levels/{id}/review/ht (04 §4.9). @return array<string,mixed> */
    public static function reviewHt(int $levelId, bool $preview): array
    {
        $lv = ContentBuilder::level($levelId, $preview);
        $out = [];
        foreach (ContentBuilder::reviewLessonIds($lv, $preview) as $lessonId) {
            $d = self::dialogueBlock($lessonId, false);
            if ($d === null || $d['lines'] === []) {
                continue;
            }
            $l = Db::one('SELECT lesson_no, title FROM lessons WHERE id = ?', [$lessonId]);
            $out[] = [
                'lessonId' => $lessonId,
                'lessonNo' => (int) ($l['lesson_no'] ?? 0),
                'lessonTitle' => (string) ($l['title'] ?? ''),
            ] + $d;
        }
        return [
            'level' => ['id' => (int) $lv['id'], 'name' => (string) $lv['name']],
            'dialogues' => $out,
        ];
    }

    /** @return array<string,mixed>|null */
    public static function dialogueBlock(int $lessonId, bool $withQuestions): ?array
    {
        $d = Db::one('SELECT * FROM dialogues WHERE lesson_id = ?', [$lessonId]);
        if ($d === null) {
            return null;
        }
        $id = (int) $d['id'];

        $block = [
            'title' => (string) $d['title'],
            'scene' => (string) $d['scene'],
            'imageUrl' => $d['image_path'] !== null && $d['image_path'] !== '' ? (string) $d['image_path'] : null,
            'speakers' => array_map(static fn (array $s): array => [
                'id' => (int) $s['id'],
                'name' => (string) $s['name'],
                'avatarChar' => (string) $s['avatar_char'],
                'color' => (string) $s['color'],
                'roleVi' => (string) $s['role_vi'],
            ], Db::all('SELECT * FROM dialogue_speakers WHERE dialogue_id = ? ORDER BY sort_order, id', [$id])),
            'lines' => array_map(static fn (array $x): array => [
                'id' => (int) $x['id'],
                'speakerId' => (int) $x['speaker_id'],
                'zh' => (string) $x['zh'],
                'highlight' => ($x['highlight'] ?? '') !== '' ? (string) $x['highlight'] : null,
                'pinyin' => (string) $x['pinyin'],
                'vi' => (string) $x['vi'],
                'audioUrl' => $x['audio_path'] ?: null,
            ], Db::all('SELECT * FROM dialogue_lines WHERE dialogue_id = ? ORDER BY sort_order, id', [$id])),
        ];

        if ($withQuestions) {
            $block['questions'] = array_map(static fn (array $q): array => [
                'prompt' => (string) $q['prompt_zh'],
                'options' => ContentBuilder::json($q['options'], []),
                'answer' => (int) $q['answer'],
            ], Db::all('SELECT * FROM dialogue_questions WHERE dialogue_id = ? ORDER BY sort_order, id', [$id]));
        }

        return $block;
    }

    /**
     * Từ mới của màn hội thoại: từ được tô sáng trong câu thoại, rồi tới từ vựng của bài
     * có xuất hiện trong lời thoại. Không trùng, tối đa 8 (04 §4.7).
     * @param list<array<string,mixed>> $lines
     * @return list<array{hanzi:string,meaning:string}>
     */
    private static function newWords(int $lessonId, array $lines): array
    {
        $words = Db::all('SELECT hanzi, meaning_vi FROM words WHERE lesson_id = ? ORDER BY sort_order, id', [$lessonId]);
        $meaning = [];
        foreach ($words as $w) {
            $meaning[(string) $w['hanzi']] = (string) $w['meaning_vi'];
        }

        $all = implode('', array_map(static fn (array $l): string => (string) $l['zh'], $lines));
        $out = [];
        $seen = [];

        foreach ($lines as $l) {
            $h = (string) ($l['highlight'] ?? '');
            if ($h !== '' && !isset($seen[$h])) {
                $seen[$h] = true;
                $out[] = ['hanzi' => $h, 'meaning' => $meaning[$h] ?? ''];
            }
        }
        foreach ($meaning as $hanzi => $vi) {
            if (count($out) >= 8) {
                break;
            }
            if (!isset($seen[$hanzi]) && mb_strpos($all, $hanzi) !== false) {
                $seen[$hanzi] = true;
                $out[] = ['hanzi' => $hanzi, 'meaning' => $vi];
            }
        }

        return array_slice($out, 0, 8);
    }

    /* ------------------------------------------------------------------ */
    /* Ngữ pháp                                                            */
    /* ------------------------------------------------------------------ */

    /** GET /learn/lessons/{id}/grammar (04 §4.8). @return array<string,mixed> */
    public static function grammar(int $lessonId, bool $preview): array
    {
        $l = ContentBuilder::lesson($lessonId, $preview);
        return [
            'lesson' => ContentBuilder::lessonRef($l, $preview),
            'points' => self::grammarRows($lessonId),
            'next' => ContentBuilder::nextLesson($l, $preview, 'cnt_np'),
        ];
    }

    /** GET /learn/levels/{id}/review/np — bảng tra theo bài (04 §4.9). @return array<string,mixed> */
    public static function reviewNp(int $levelId, bool $preview): array
    {
        $lv = ContentBuilder::level($levelId, $preview);
        $groups = [];
        foreach (ContentBuilder::reviewLessonIds($lv, $preview) as $lessonId) {
            $points = self::grammarRows($lessonId);
            if ($points === []) {
                continue;
            }
            $l = Db::one('SELECT lesson_no, title FROM lessons WHERE id = ?', [$lessonId]);
            $groups[] = [
                'lessonId' => $lessonId,
                'lessonNo' => (int) ($l['lesson_no'] ?? 0),
                'lessonTitle' => (string) ($l['title'] ?? ''),
                // Bảng tổng hợp chỉ cần khối công thức và một ví dụ.
                'points' => array_map(static fn (array $p): array => [
                    'id' => $p['id'],
                    'title' => $p['title'],
                    'subtitle' => $p['subtitle'],
                    'blocks' => $p['blocks'],
                    'example' => $p['examples'][0] ?? null,
                ], $points),
            ];
        }
        return [
            'level' => ['id' => (int) $lv['id'], 'name' => (string) $lv['name']],
            'groups' => $groups,
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function grammarRows(int $lessonId): array
    {
        return array_map(static fn (array $g): array => [
            'id' => (int) $g['id'],
            'title' => (string) $g['title'],
            'subtitle' => (string) $g['subtitle'],
            'explain' => (string) $g['explain_vi'],
            'note' => ($g['note_vi'] ?? '') !== '' ? (string) $g['note_vi'] : null,
            'blocks' => ContentBuilder::json($g['blocks'], []),
            'examples' => ContentBuilder::json($g['examples'], []),
            'practice' => ContentBuilder::json($g['practice'], null),
        ], Db::all('SELECT * FROM grammar_points WHERE lesson_id = ? ORDER BY sort_order, id', [$lessonId]));
    }
}
