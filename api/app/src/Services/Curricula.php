<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;

/** Giáo trình là cha của bài học: mỗi học viên học đúng một giáo trình do admin gán. */
final class Curricula
{
    /**
     * Giáo trình HSK giữ toàn bộ dữ liệu cũ (migration 013), không xoá được. Công cụ nạp cũ không gửi id thì vào đây,
     * chữ mẫu cấp lấy từ levels.sample_text. Không còn là giáo trình mặc định của học viên: xem defaultId().
     */
    public const HSK_ID = 1;

    /** Danh sách đóng vì web phải biết viết "Chọn cấp HSK" hay "Chọn quyển"; chữ tự do thì không ghép câu được. */
    public const LEVEL_LABELS = ['HSK', 'Quyển'];

    /** Học viên chưa gán ($curriculumId null) học giáo trình mặc định. */
    public static function levelLabel(?int $curriculumId): string
    {
        $label = Db::val('SELECT level_label FROM curricula WHERE id = ?', [$curriculumId ?? self::defaultId()]);
        return $label === null ? self::LEVEL_LABELS[0] : (string) $label;
    }

    /**
     * Giáo trình của học viên chưa được phân quyền (users.curriculum_id NULL), cả tài khoản cũ lẫn mới đăng ký.
     * Lưu bằng cờ chứ không ghi id vào users, nên admin đổi mặc định thì mọi học viên chưa phân quyền đổi theo.
     * Chưa có giáo trình nào bật cờ (server vừa chạy migration 016, chưa nạp Hán ngữ) thì về HSK như trước.
     */
    public static function defaultId(): int
    {
        $id = Db::val('SELECT id FROM curricula WHERE is_default = 1 ORDER BY id LIMIT 1');
        return $id === null ? self::HSK_ID : (int) $id;
    }

    public static function ofUser(int $userId): int
    {
        $id = Db::val('SELECT curriculum_id FROM users WHERE id = ?', [$userId]);
        return $id === null ? self::defaultId() : (int) $id;
    }

    /** Xem trước từ trang soạn bài thì xem đúng giáo trình của bài đó, không phải giáo trình của admin. */
    public static function forRequest(Request $r): int
    {
        if ($r->isPreview() && ($id = $r->qInt('curriculum')) > 0) {
            return $id;
        }
        return $r->user === null ? self::defaultId() : self::ofUser($r->userId());
    }

    /** Bài của giáo trình khác coi như không có, kể cả khi người học tự gõ id trên URL. */
    public static function assertLesson(int $lessonId, int $curriculumId): void
    {
        $owner = Db::val('SELECT curriculum_id FROM lessons WHERE id = ?', [$lessonId]);
        if ($owner !== null && (int) $owner !== $curriculumId) {
            throw HttpError::notFound('Bài này chưa có hoặc đã bị ẩn.');
        }
    }

    /** Id giáo trình gửi lên từ khu soạn bài; không gửi thì là giáo trình HSK để công cụ nạp HSK cũ vẫn chạy. */
    public static function resolve(int $id): int
    {
        if ($id <= 0) {
            return self::HSK_ID;
        }
        if (Db::val('SELECT id FROM curricula WHERE id = ?', [$id]) === null) {
            throw HttpError::notFound('Không có giáo trình này.');
        }
        return $id;
    }
}
