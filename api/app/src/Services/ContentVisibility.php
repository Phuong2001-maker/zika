<?php
declare(strict_types=1);
namespace Zika\Services;

use Zika\Core\{Db, HttpError, Request};

/** Trạng thái riêng của con được giữ lại khi cha tắt; quyền hiệu lực là giao của cả cây. */
final class ContentVisibility
{
    public static function levelVisible(int $curriculum, int $level): bool
    {
        return (bool) Db::val('SELECT c.is_visible AND COALESCE(cl.is_visible, v.is_visible) FROM curricula c CROSS JOIN levels v LEFT JOIN curriculum_levels cl ON cl.curriculum_id = c.id AND cl.level_id = v.id WHERE c.id = ? AND v.id = ?', [$curriculum, $level]);
    }

    public static function lessonSql(string $alias = 'l'): string
    {
        return "$alias.status = 'published' AND EXISTS (SELECT 1 FROM curricula cv WHERE cv.id = $alias.curriculum_id AND cv.is_visible = 1) AND COALESCE((SELECT cl.is_visible FROM curriculum_levels cl WHERE cl.curriculum_id = $alias.curriculum_id AND cl.level_id = $alias.level_id), (SELECT vl.is_visible FROM levels vl WHERE vl.id = $alias.level_id), 0) = 1";
    }

    public static function enforce(Request $r): void
    {
        // Khách cũng phải qua đây: nội dung học mở công khai cho khách học thử, bài vừa ẩn không được lộ qua cache.
        if (str_starts_with($r->path, '/admin/') || $r->isPreview() || FeatureAccess::ongoing($r)) return;
        // Kiểm trước khi đọc cache file, tránh link cũ trả nội dung vừa bị ẩn.
        if (preg_match('~^/learn/lessons/(\d+)/~', $r->path, $m)) ContentBuilder::lesson((int) $m[1], false);
        if (preg_match('~^/learn/levels/(\d+)/(?:lessons|review/)~', $r->path, $m)) ContentBuilder::level((int) $m[1], false, Curricula::forRequest($r));
        if (preg_match('~^/tests/mock/(\d+)/summary$~', $r->path, $m) || ($r->path === '/tests/start' && $r->input('mockTestId'))) {
            $id = isset($m[1]) ? (int) $m[1] : (int) $r->input('mockTestId');
            // Đề HSK 3.0 không thuộc giáo trình nào: ẩn cấp của giáo trình không được chặn nó.
            $mock = Db::one('SELECT level_id, for_contest, kind FROM mock_tests WHERE id = ?', [$id]);
            if ($mock && !(int) $mock['for_contest'] && $mock['kind'] === 'mock' && !self::levelVisible(Curricula::forRequest($r), (int) $mock['level_id'])) throw HttpError::notFound();
        }
    }
}
