<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Json;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Ua;
use Zika\Core\Validator;
use Zika\Services\Curricula;

/**
 * Góp ý · báo lỗi phía người học (07 §1.3): gửi từ bảng Chuyển nhanh ở mọi màn, kèm ngữ cảnh màn đang mở để biên tập
 * mở đúng chỗ sửa (/admin/feedback). Trước 08/11/2026 khu quản trị đã có mà người học chưa gửi được.
 */
final class FeedbackController
{
    public const LOAI = ['content_error', 'pronunciation', 'display', 'feature', 'content_suggestion', 'other'];
    private const PHAN = ['lt', 'bt', 'kt', 'lv', 'ht', 'np'];
    /** Một người gửi tối đa chừng này góp ý mỗi giờ: đủ cho người báo nhiều lỗi liền, chặn bấm gửi liên tục. */
    private const TOI_DA_MOT_GIO = 10;

    /** POST /feedback {category, message, ctx} */
    public function create(Request $r): Response
    {
        $uid = $r->userId();
        $v = new Validator($r->body);
        $loai = (string) $v->in('category', 'Loại góp ý', self::LOAI);
        $noiDung = $v->str('message', 'Nội dung', min: 5, max: 2000);
        $ctxGui = $r->body['ctx'] ?? [];
        $v->done();

        $mot = Clock::sql(Clock::now()->modify('-1 hour'));
        if ((int) Db::val('SELECT COUNT(*) FROM feedback WHERE user_id = ? AND created_at >= ?', [$uid, $mot]) >= self::TOI_DA_MOT_GIO) {
            throw new HttpError(429, 'RATE_LIMITED', 'Bạn đã gửi nhiều góp ý trong một giờ. Nghỉ chút rồi gửi tiếp nhé.');
        }

        $id = Db::insert('feedback', [
            'user_id' => $uid,
            'category' => $loai,
            'message' => $noiDung,
            'ctx' => Json::encode(self::ngucCanh(is_array($ctxGui) ? $ctxGui : [], $uid, $r)),
            'created_at' => Clock::sql(),
        ]);
        return Response::json(['id' => $id], 201);
    }

    /** GET /feedback/mine — góp ý đã gửi và phản hồi của quản trị (SMTP chưa chạy thì đây là nơi duy nhất đọc được). */
    public function mine(Request $r): array
    {
        return array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'category' => (string) $f['category'],
            'message' => (string) $f['message'],
            'status' => (string) $f['status'],
            'reply' => $f['reply'],
            'createdAt' => Clock::iso((string) $f['created_at']),
            'repliedAt' => Clock::iso($f['replied_at']),
        ], Db::all('SELECT id, category, message, status, reply, created_at, replied_at FROM feedback WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$r->userId()]));
    }

    /**
     * Ngữ cảnh lưu cùng góp ý. Chỉ nhận các khoá đã biết, đúng kiểu (web gửi gì cũng không chèn được rác vào JSON);
     * bài (lessonId) tra ở server theo giáo trình của người gửi để quản trị bấm "Mở để sửa" là tới đúng bài.
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private static function ngucCanh(array $c, int $uid, Request $r): array
    {
        $ra = [];
        $part = (string) ($c['part'] ?? '');
        if (in_array($part, self::PHAN, true)) {
            $ra['part'] = $part;
        }
        foreach (['levelId' => 6, 'lessonNo' => 300, 'questionNo' => 500, 'trang' => 999] as $k => $max) {
            $n = filter_var($c[$k] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
            if ($n !== false) {
                $ra[$k] = $n;
            }
        }
        $route = (string) ($c['route'] ?? '');
        if (str_starts_with($route, '/') && strlen($route) <= 200) {
            $ra['route'] = $route;
        }
        foreach (['capTen' => 30, 'book' => 8] as $k => $max) {
            $s = trim((string) ($c[$k] ?? ''));
            if ($s !== '' && mb_strlen($s) <= $max) {
                $ra[$k] = $s;
            }
        }
        if (isset($ra['levelId'], $ra['lessonNo'])) {
            $bai = Db::val(
                'SELECT id FROM lessons WHERE curriculum_id = ? AND level_id = ? AND lesson_no = ?',
                [Curricula::ofUser($uid), $ra['levelId'], $ra['lessonNo']],
            );
            if ($bai !== null) {
                $ra['lessonId'] = (int) $bai;
            }
        }
        return $ra + Ua::parse($r->userAgent());
    }
}
