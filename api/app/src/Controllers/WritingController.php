<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Validator;
use Zika\Services\ContentBuilder;
use Zika\Services\PartContent;

/** Luyện viết — kết quả từng chữ (04 §8). */
final class WritingController
{
    /** POST /writing/result  { charId, mode, mistakes, passed } */
    public function result(Request $r): array
    {
        $v = new Validator($r->body);
        $charId = (int) $v->int('charId', 'Chữ', 1);
        $mode = (string) $v->in('mode', 'Chế độ viết', ['trace', 'free'], 'trace');
        $mistakes = (int) $v->int('mistakes', 'Số nét sai', 0, 200, false, 0);
        $passed = $v->bool('passed');
        $lessonId = $v->int('lessonId', 'Bài', 1, null, false);
        $v->done();

        if (Db::val('SELECT id FROM characters WHERE id = ?', [$charId]) === null) {
            throw HttpError::notFound('Không có chữ này.');
        }

        // Chỉ chế độ “Tự viết” mới tính là viết đạt (03 §5.2).
        $pass = $passed && $mode === 'free' ? 1 : 0;

        Db::exec(
            'INSERT INTO char_stats (user_id, char_id, attempts, mistakes, passes, last_at)
             VALUES (?, ?, 1, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, mistakes = mistakes + VALUES(mistakes),
                                     passes = passes + VALUES(passes), last_at = NOW()',
            [$r->userId(), $charId, $mistakes, $pass],
        );

        $passes = (int) Db::val('SELECT passes FROM char_stats WHERE user_id = ? AND char_id = ?', [$r->userId(), $charId]);
        $need = (int) PartContent::writingConfig($lessonId ?? 0)['passCount'];

        return ['passes' => $passes, 'charDone' => $passes >= $need, 'passCount' => $need];
    }

    /** GET /writing/weak?levelId=2 — chữ hay sai của người này trong cấp. */
    public function weak(Request $r): array
    {
        $levelId = $r->qInt('levelId', 0, 1, 6);
        if ($levelId === 0) {
            throw HttpError::validation(['levelId' => 'Thiếu cấp.']);
        }

        $lv = Db::one('SELECT * FROM levels WHERE id = ?', [$levelId]);
        if ($lv === null) {
            throw HttpError::notFound();
        }
        $lessonIds = ContentBuilder::reviewLessonIds($lv, false);
        if ($lessonIds === []) {
            return ['charIds' => []];
        }

        $ids = Db::col(
            'SELECT DISTINCT cs.char_id
               FROM char_stats cs
               JOIN lesson_chars lc ON lc.char_id = cs.char_id
              WHERE cs.user_id = ? AND lc.lesson_id IN (' . Db::in($lessonIds) . ')
                AND (cs.mistakes >= 3 OR (cs.attempts > 0 AND cs.passes = 0))',
            [$r->userId(), ...$lessonIds],
        );

        return ['charIds' => array_map('intval', $ids)];
    }
}
