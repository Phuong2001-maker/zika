<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Db;
use Zika\Core\Request;

/**
 * Việc phải làm sau mỗi lần admin sửa nội dung của một bài:
 *   1. đếm lại cnt_* trên bảng lessons (giao diện đọc số này, không đếm lại mỗi lần hiển thị),
 *   2. sinh lại sample_text (2 từ đầu),
 *   3. ghi người sửa,
 *   4. xoá cache nội dung của bài và của cấp.
 *
 * Mọi controller trong Controllers/Admin gọi touch() ở cuối thao tác ghi.
 */
final class ContentWriter
{
    public static function touch(int $lessonId, ?Request $r = null): void
    {
        $l = Db::one('SELECT id, level_id FROM lessons WHERE id = ?', [$lessonId]);
        if ($l === null) {
            return;
        }

        $counts = [
            'cnt_words' => (int) Db::val('SELECT COUNT(*) FROM words WHERE lesson_id = ?', [$lessonId]),
            'cnt_bt' => (int) Db::val("SELECT COUNT(*) FROM questions WHERE part = 'bt' AND lesson_id = ?", [$lessonId]),
            'cnt_kt' => (int) Db::val("SELECT COUNT(*) FROM questions WHERE part = 'kt' AND lesson_id = ?", [$lessonId]),
            'cnt_lv' => (int) Db::val('SELECT COUNT(*) FROM lesson_chars WHERE lesson_id = ? AND is_visible = 1', [$lessonId]),
            'cnt_ht' => (int) Db::val(
                'SELECT COUNT(*) FROM dialogue_lines dl JOIN dialogues d ON d.id = dl.dialogue_id WHERE d.lesson_id = ?',
                [$lessonId],
            ),
            'cnt_np' => (int) Db::val('SELECT COUNT(*) FROM grammar_points WHERE lesson_id = ?', [$lessonId]),
        ];

        $counts['sample_text'] = self::sampleText($lessonId);
        if ($r !== null && $r->user !== null) {
            $counts['updated_by'] = (int) $r->user['id'];
        }

        Db::update('lessons', $counts, ['id' => $lessonId]);
        ContentBuilder::forgetLesson($lessonId, (int) $l['level_id']);
    }

    /** “鸡蛋 · 牛奶” — 2 từ đầu của bài. */
    public static function sampleText(int $lessonId): string
    {
        $hz = Db::col('SELECT hanzi FROM words WHERE lesson_id = ? ORDER BY sort_order, id LIMIT 2', [$lessonId]);
        return implode(' · ', array_map('strval', $hz));
    }

    /** Đánh số lại sort_order theo danh sách id gửi lên; chỉ nhận id thuộc đúng phạm vi. */
    public static function reorder(string $table, string $idCol, array $ids, string $scopeCol, int $scopeId, string $sortCol = 'sort_order'): int
    {
        $n = 0;
        Db::tx(static function () use ($table, $idCol, $ids, $scopeCol, $scopeId, $sortCol, &$n): void {
            foreach (array_values($ids) as $i => $id) {
                $n += Db::exec(
                    "UPDATE `$table` SET `$sortCol` = ? WHERE `$idCol` = ? AND `$scopeCol` = ?",
                    [$i + 1, (int) $id, $scopeId],
                );
            }
        });
        return $n;
    }

    /** Lấy dòng bài, 404 nếu không có (admin xem được cả bài Nháp). @return array<string,mixed> */
    public static function lesson(int $lessonId): array
    {
        $l = Db::one('SELECT * FROM lessons WHERE id = ?', [$lessonId]);
        if ($l === null) {
            throw \Zika\Core\HttpError::notFound('Không tìm thấy bài học.');
        }
        return $l;
    }
}
