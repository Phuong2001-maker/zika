<?php
declare(strict_types=1);
namespace Zika\Controllers\Admin;

use Zika\Core\{Audit, Db, HttpError, Request};
use Zika\Services\ContentBuilder;

final class ContentVisibilityController
{
    public function index(Request $r): array
    {
        $tree = [];
        foreach (Db::all('SELECT id, name, level_label, is_visible FROM curricula ORDER BY id') as $c) {
            $levels = Db::all('SELECT v.id, COALESCE(cl.is_visible, v.is_visible) AS is_visible FROM levels v LEFT JOIN curriculum_levels cl ON cl.level_id = v.id AND cl.curriculum_id = ? ORDER BY v.id', [(int) $c['id']]);
            foreach ($levels as &$lv) {
                $lv = ['id' => (int) $lv['id'], 'visible' => (bool) $lv['is_visible'], 'lessons' => Db::all('SELECT id, lesson_no AS no, title, status FROM lessons WHERE curriculum_id = ? AND level_id = ? ORDER BY lesson_no', [(int) $c['id'], (int) $lv['id']])];
                foreach ($lv['lessons'] as &$lesson) $lesson = ['id' => (int) $lesson['id'], 'no' => (int) $lesson['no'], 'title' => $lesson['title'], 'visible' => $lesson['status'] === 'published'];
                unset($lesson);
            }
            unset($lv);
            $tree[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'label' => $c['level_label'], 'visible' => (bool) $c['is_visible'], 'levels' => $levels];
        }
        return ['curricula' => $tree];
    }

    public function save(Request $r): array
    {
        $kind = $r->input('kind');
        $curriculum = filter_var($r->input('curriculumId'), FILTER_VALIDATE_INT);
        $level = filter_var($r->input('levelId'), FILTER_VALIDATE_INT);
        $lesson = filter_var($r->input('lessonId'), FILTER_VALIDATE_INT);
        $visible = $r->input('visible');
        if (!in_array($kind, ['curriculum', 'level', 'lesson'], true) || !$curriculum || !is_bool($visible) || ($kind !== 'curriculum' && (!$level || $level < 1 || $level > 6)) || ($kind === 'lesson' && !$lesson)) throw HttpError::validation(['visible' => 'Lựa chọn ẩn/hiện không hợp lệ.']);
        Db::tx(static function () use ($r, $kind, $curriculum, $level, $lesson, $visible) {
            if (!Db::one('SELECT id FROM curricula WHERE id = ? FOR UPDATE', [$curriculum])) throw HttpError::notFound();
            if ($kind === 'curriculum') Db::exec('UPDATE curricula SET is_visible = ? WHERE id = ?', [(int) $visible, $curriculum]);
            elseif ($kind === 'level') Db::exec('INSERT INTO curriculum_levels (curriculum_id, level_id, is_visible) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible)', [$curriculum, $level, (int) $visible]);
            else {
                if (!Db::val('SELECT id FROM lessons WHERE id = ? AND curriculum_id = ? AND level_id = ?', [$lesson, $curriculum, $level])) throw HttpError::notFound();
                Db::exec('UPDATE lessons SET status = ? WHERE id = ?', [$visible ? 'published' : 'draft', $lesson]);
            }
            Audit::log($r, 'content.visibility', 'curricula', $curriculum, ['kind' => $kind, 'levelId' => $level, 'lessonId' => $lesson, 'visible' => $visible]);
        });
        // Cả danh sách cấp, tổng hợp và gợi ý bài tiếp theo đều phụ thuộc trạng thái này.
        foreach ($kind === 'curriculum' ? range(1, 6) : [$level] as $id) ContentBuilder::forgetLevel((int) $id);
        return $this->index($r);
    }
}
