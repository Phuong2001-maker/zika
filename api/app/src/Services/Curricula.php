<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;

/** Giáo trình là cha của bài học: mỗi học viên học đúng một giáo trình do admin gán. */
final class Curricula
{
    /** Giáo trình giữ toàn bộ dữ liệu cũ (migration 013), không xoá được. Học viên chưa gán học theo bộ này. */
    public const DEFAULT_ID = 1;

    public static function ofUser(int $userId): int
    {
        $id = Db::val('SELECT curriculum_id FROM users WHERE id = ?', [$userId]);
        return $id === null ? self::DEFAULT_ID : (int) $id;
    }

    /** Xem trước từ trang soạn bài thì xem đúng giáo trình của bài đó, không phải giáo trình của admin. */
    public static function forRequest(Request $r): int
    {
        if ($r->isPreview() && ($id = $r->qInt('curriculum')) > 0) {
            return $id;
        }
        return self::ofUser($r->userId());
    }

    /** Bài của giáo trình khác coi như không có, kể cả khi người học tự gõ id trên URL. */
    public static function assertLesson(int $lessonId, int $curriculumId): void
    {
        $owner = Db::val('SELECT curriculum_id FROM lessons WHERE id = ?', [$lessonId]);
        if ($owner !== null && (int) $owner !== $curriculumId) {
            throw HttpError::notFound('Bài này chưa có hoặc đã bị ẩn.');
        }
    }

    /** Id giáo trình gửi lên từ khu soạn bài; không gửi thì là giáo trình mặc định để công cụ nạp cũ vẫn chạy. */
    public static function resolve(int $id): int
    {
        if ($id <= 0) {
            return self::DEFAULT_ID;
        }
        if (Db::val('SELECT id FROM curricula WHERE id = ?', [$id]) === null) {
            throw HttpError::notFound('Không có giáo trình này.');
        }
        return $id;
    }
}
