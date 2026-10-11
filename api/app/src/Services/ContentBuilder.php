<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Db;
use Zika\Core\FileCache;
use Zika\Core\HttpError;
use Zika\Core\Json;

/**
 * Dựng JSON nội dung học (04 §4) và cache thành file (02 §4.3).
 *
 * Khoá cache:  "levels" · "l{cấp}-lessons" · "l{cấp}-review-{phần}" · "x{bài}-{phần}".
 * Chỉ dựng bản cho NGƯỜI HỌC (bài đang hiện, cấp đang hiện); bài ẩn → 404 và không ghi cache.
 * Bản xem trước của biên tập/quản trị (?preview=1) dựng thẳng từ DB, không cache.
 * Admin lưu bất cứ thứ gì thuộc cấp → ContentBuilder::forgetLevel() xoá mọi cache của cấp đó.
 */
final class ContentBuilder
{
    public const PARTS = ['lt', 'bt', 'kt', 'lv', 'ht', 'np'];
    private const CNT = ['lt' => 'cnt_words', 'bt' => 'cnt_bt', 'kt' => 'cnt_kt', 'lv' => 'cnt_lv', 'ht' => 'cnt_ht', 'np' => 'cnt_np'];

    /* ------------------------------------------------------------------ */
    /* Cache                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @param callable():mixed $build
     * @return array{etag:string,json:string}|null null = bản xem trước (không cache)
     */
    public static function cached(string $key, bool $preview, callable $build): ?array
    {
        return $preview ? null : FileCache::content($key, $build);
    }

    /** Xoá cache nội dung của một cấp: danh sách cấp, danh sách bài, tổng hợp, và nội dung từng bài của cấp. */
    public static function forgetLevel(int $levelId): void
    {
        FileCache::forgetLevel($levelId);
        foreach (Db::col('SELECT id FROM lessons WHERE level_id = ?', [$levelId]) as $id) {
            self::forgetLessonFiles((int) $id);
        }
    }

    /** Xoá cache khi một bài đổi (kể cả bài vừa bị xoá: truyền levelId của nó). */
    public static function forgetLesson(int $lessonId, ?int $levelId = null): void
    {
        $levelId ??= (int) Db::val('SELECT level_id FROM lessons WHERE id = ?', [$lessonId]);
        self::forgetLessonFiles($lessonId);
        if ($levelId > 0) {
            // Bài kế tiếp (“next”) và tổng hợp của cả cấp phụ thuộc vào bài này.
            self::forgetLevel($levelId);
        }
    }

    private static function forgetLessonFiles(int $lessonId): void
    {
        foreach (self::PARTS as $p) {
            FileCache::forget('content', "x{$lessonId}-{$p}");
        }
    }

    /* ------------------------------------------------------------------ */
    /* Kiểm tra quyền xem                                                 */
    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed> dòng levels; cấp ẩn → 404 với người học */
    public static function level(int $levelId, bool $preview): array
    {
        $lv = Db::one('SELECT * FROM levels WHERE id = ?', [$levelId]);
        if ($lv === null || (!$preview && !(int) $lv['is_visible'])) {
            throw HttpError::notFound();
        }
        return $lv;
    }

    /** @return array<string,mixed> dòng lessons + level_visible; bài Nháp / cấp ẩn → 404 với người học */
    public static function lesson(int $lessonId, bool $preview): array
    {
        $l = Db::one('SELECT l.*, v.is_visible AS level_visible, v.name AS level_name FROM lessons l JOIN levels v ON v.id = l.level_id WHERE l.id = ?', [$lessonId]);
        if ($l === null || (!$preview && ($l['status'] !== 'published' || !(int) $l['level_visible']))) {
            throw HttpError::notFound('Bài này chưa có hoặc đã bị ẩn.');
        }
        return $l;
    }

    /** @param array<string,mixed> $l @return array<string,mixed> */
    public static function lessonRef(array $l, bool $preview): array
    {
        $ref = ['id' => (int) $l['id'], 'no' => (int) $l['lesson_no'], 'title' => (string) $l['title'], 'levelId' => (int) $l['level_id']];
        if ($preview) {
            $ref['status'] = (string) $l['status'];
        }
        return $ref;
    }

    /** Điều kiện “bài đang hiện” (xem trước thì mọi bài). */
    private static function statusSql(bool $preview, string $alias = 'l'): string
    {
        return $preview ? '1 = 1' : "$alias.status = 'published'";
    }

    /* ------------------------------------------------------------------ */
    /* Danh sách cấp, danh sách bài                                       */
    /* ------------------------------------------------------------------ */

    /** GET /learn/levels (04 §4.1). Số bài/từ chỉ tính bài đang hiện. @return list<array<string,mixed>> */
    public static function levels(bool $preview): array
    {
        $rows = Db::all(
            "SELECT v.*, COUNT(l.id) AS lesson_count, COALESCE(SUM(l.cnt_words), 0) AS word_count
             FROM levels v LEFT JOIN lessons l ON l.level_id = v.id AND " . self::statusSql($preview) . '
             GROUP BY v.id ORDER BY v.id',
        );
        return array_map(static fn (array $v) => [
            'id' => (int) $v['id'],
            'name' => (string) $v['name'],
            'visible' => (bool) $v['is_visible'],
            'sampleText' => (string) $v['sample_text'],
            'targetWords' => (int) $v['target_words'],
            'lessonCount' => (int) $v['lesson_count'],
            'wordCount' => (int) $v['word_count'],
        ], $rows);
    }

    /** GET /learn/levels/{id}/lessons (04 §4.2). @return array<string,mixed> */
    public static function levelLessons(int $levelId, bool $preview): array
    {
        $lv = self::level($levelId, $preview);
        $lessons = Db::all(
            'SELECT l.*, tc.duration_sec AS test_duration, d.title AS dialogue_title
             FROM lessons l
             LEFT JOIN test_configs tc ON tc.lesson_id = l.id
             LEFT JOIN dialogues d ON d.lesson_id = l.id
             WHERE l.level_id = ? AND ' . self::statusSql($preview) . ' ORDER BY l.lesson_no',
            [$levelId],
        );
        $ids = array_map(static fn (array $l) => (int) $l['id'], $lessons);
        $grammar = [];
        foreach (Db::all('SELECT lesson_id, title FROM grammar_points WHERE lesson_id IN (' . Db::in($ids) . ') ORDER BY lesson_id, sort_order', $ids) as $g) {
            $grammar[(int) $g['lesson_id']][] = (string) $g['title'];
        }

        $out = [];
        $wordCount = 0;
        foreach ($lessons as $l) {
            $id = (int) $l['id'];
            $wordCount += (int) $l['cnt_words'];
            $min = (int) round(((int) ($l['test_duration'] ?? 300)) / 60);
            $item = [
                'id' => $id,
                'no' => (int) $l['lesson_no'],
                'title' => (string) $l['title'],
                'sampleText' => (string) $l['sample_text'],
                'counts' => array_map(static fn (string $c) => (int) $l[$c], self::CNT),
                'sub' => [
                    'bt' => $l['cnt_bt'] . ' câu',
                    'kt' => $l['cnt_kt'] . ' câu · ' . $min . ' phút',
                    'lv' => $l['cnt_lv'] . ' chữ',
                    'ht' => (string) ($l['dialogue_title'] ?? ''),
                    'np' => implode(' · ', $grammar[$id] ?? []),
                ],
            ];
            if ($preview) {
                $item['status'] = (string) $l['status'];
            }
            $out[] = $item;
        }

        // Tổng hợp gộp bài đang hiện (hoặc mọi bài nếu admin tắt “Chỉ gộp các bài đang hiện”).
        $rIds = self::reviewLessonIds($lv, $preview);
        $in = Db::in($rIds);
        $mock = Db::all(
            "SELECT m.id, m.title, m.duration_sec, m.max_points, m.pass_points, COUNT(q.id) AS qn
             FROM mock_tests m LEFT JOIN questions q ON q.mock_test_id = m.id
             WHERE m.level_id = ? AND " . ($preview ? '1 = 1' : "m.status = 'published'") . '
             GROUP BY m.id ORDER BY m.sort_order, m.id',
            [$levelId],
        );
        return [
            'level' => ['id' => (int) $lv['id'], 'name' => (string) $lv['name'], 'wordCount' => $wordCount] + ($preview ? ['visible' => (bool) $lv['is_visible']] : []),
            'lessons' => $out,
            'review' => [
                'lt' => ['wordCount' => (int) Db::val("SELECT COUNT(*) FROM words WHERE lesson_id IN ($in)", $rIds)],
                'bt' => ['count' => (int) $lv['review_bt_count'], 'pool' => (int) Db::val("SELECT COUNT(*) FROM questions WHERE part = 'bt' AND lesson_id IN ($in)", $rIds)],
                'lv' => ['charCount' => (int) Db::val("SELECT COUNT(DISTINCT char_id) FROM lesson_chars WHERE is_visible = 1 AND lesson_id IN ($in)", $rIds)],
                'ht' => ['dialogueCount' => (int) Db::val("SELECT COUNT(*) FROM dialogues d WHERE d.lesson_id IN ($in) AND EXISTS (SELECT 1 FROM dialogue_lines x WHERE x.dialogue_id = d.id)", $rIds)],
                'np' => ['pointCount' => (int) Db::val("SELECT COUNT(*) FROM grammar_points WHERE lesson_id IN ($in)", $rIds)],
                'kt' => ['mockTests' => array_map(static fn (array $m) => [
                    'id' => (int) $m['id'],
                    'title' => (string) $m['title'],
                    'questionCount' => (int) $m['qn'],
                    'durationSec' => (int) $m['duration_sec'],
                    'maxPoints' => (int) $m['max_points'],
                    'passPoints' => (int) $m['pass_points'],
                ], array_values(array_filter($mock, static fn (array $m) => (int) $m['qn'] > 0 || $preview)))],
            ],
        ];
    }

    /**
     * Id các bài được gộp vào Tổng hợp của cấp, theo thứ tự số bài (04 §4.9).
     * @param array<string,mixed> $lv dòng levels
     * @return list<int>
     */
    public static function reviewLessonIds(array $lv, bool $preview): array
    {
        // Xem trước: mọi bài. Người học: bài đang hiện, trừ khi admin tắt “Chỉ gộp các bài đang hiện”.
        $publishedOnly = !$preview && (int) $lv['review_published_only'] === 1;
        return array_map('intval', Db::col(
            'SELECT id FROM lessons WHERE level_id = ?' . ($publishedOnly ? " AND status = 'published'" : '') . ' ORDER BY lesson_no',
            [(int) $lv['id']],
        ));
    }

    /**
     * Bài đang hiện kế tiếp trong cấp (null nếu là bài cuối), kèm 6 từ đầu để xem trước (04 §4.3).
     * @param array<string,mixed> $l
     * @return array<string,mixed>|null
     */
    public static function nextLesson(array $l, bool $preview, ?string $countCol = 'cnt_words'): ?array
    {
        $n = Db::one(
            'SELECT * FROM lessons l WHERE l.level_id = ? AND l.lesson_no > ? AND ' . self::statusSql($preview) . ' ORDER BY l.lesson_no LIMIT 1',
            [(int) $l['level_id'], (int) $l['lesson_no']],
        );
        if ($n === null) {
            return null;
        }
        $preview6 = Db::all('SELECT hanzi, meaning_vi FROM words WHERE lesson_id = ? ORDER BY sort_order, id LIMIT 6', [(int) $n['id']]);
        return [
            'id' => (int) $n['id'],
            'no' => (int) $n['lesson_no'],
            'title' => (string) $n['title'],
            'wordCount' => (int) $n['cnt_words'],
            'count' => $countCol !== null ? (int) $n[$countCol] : null,
            'preview' => array_map(static fn (array $w) => ['hanzi' => (string) $w['hanzi'], 'meaning' => (string) $w['meaning_vi']], $preview6),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Lật thẻ                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Từ + câu ví dụ của các bài (theo thứ tự bài rồi thứ tự từ).
     * @param list<int> $lessonIds
     * @return list<array<string,mixed>>
     */
    public static function words(array $lessonIds, bool $withLesson = false): array
    {
        if ($lessonIds === []) {
            return [];
        }
        $in = Db::in($lessonIds);
        $words = Db::all(
            "SELECT w.*, l.lesson_no FROM words w JOIN lessons l ON l.id = w.lesson_id WHERE w.lesson_id IN ($in)
             ORDER BY FIELD(w.lesson_id, $in), w.sort_order, w.id",
            [...$lessonIds, ...$lessonIds],
        );
        $wIds = array_map(static fn (array $w) => (int) $w['id'], $words);
        $ex = [];
        foreach (Db::all('SELECT * FROM word_examples WHERE word_id IN (' . Db::in($wIds) . ') ORDER BY word_id, sort_order, id', $wIds) as $e) {
            $ex[(int) $e['word_id']][] = $e;
        }
        $out = [];
        foreach ($words as $w) {
            $item = [
                'id' => (int) $w['id'],
                'hanzi' => (string) $w['hanzi'],
                'pinyin' => (string) $w['pinyin'],
                'hanViet' => (string) $w['han_viet'],
                'meaning' => (string) $w['meaning_vi'],
                'pos' => (string) $w['pos'],
                'audioUrl' => $w['audio_path'] ?: null,
                'examples' => array_map(static fn (array $e) => [
                    'zh' => (string) $e['zh'],
                    'highlight' => ($e['highlight'] ?? '') !== '' ? (string) $e['highlight'] : (string) $w['hanzi'],
                    'pinyin' => (string) $e['pinyin'],
                    'vi' => (string) $e['vi'],
                    'audioUrl' => $e['audio_path'] ?: null,
                ], $ex[(int) $w['id']] ?? []),
            ];
            if ($withLesson) {
                $item['lessonId'] = (int) $w['lesson_id'];
                $item['lessonNo'] = (int) $w['lesson_no'];
            }
            $out[] = $item;
        }
        return $out;
    }

    /** GET /learn/lessons/{id}/flashcards (04 §4.3). @return array<string,mixed> */
    public static function flashcards(int $lessonId, bool $preview): array
    {
        $l = self::lesson($lessonId, $preview);
        return [
            'lesson' => self::lessonRef($l, $preview),
            'words' => self::words([$lessonId]),
            'next' => self::nextLesson($l, $preview),
        ];
    }

    /** GET /learn/levels/{id}/review/lt. @return array<string,mixed> */
    public static function reviewLt(int $levelId, bool $preview): array
    {
        $lv = self::level($levelId, $preview);
        return [
            'level' => ['id' => (int) $lv['id'], 'name' => (string) $lv['name']],
            'words' => self::words(self::reviewLessonIds($lv, $preview), true),
        ];
    }

    /** Giải mã cột JSON an toàn. */
    public static function json(mixed $raw, mixed $default = []): mixed
    {
        return is_string($raw) ? Json::decode($raw, $default) : $default;
    }
}
