<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\FeedbackController;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Router;
use Zika\Tests\DbTestCase;

/** Góp ý phía người học: gửi kèm ngữ cảnh màn, quản trị mở đúng bài; đọc lại phản hồi. */
final class FeedbackTest extends DbTestCase
{
    /** @param array<string,mixed> $body */
    private function req(int $uid, array $body = []): Request
    {
        $r = new Request('POST', '/feedback', body: $body, headers: ['user-agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1']);
        $r->user = ['id' => $uid, 'role' => 'learner'];
        return $r;
    }

    private function loi(callable $fn): string
    {
        try {
            $fn();
            return '';
        } catch (HttpError $e) {
            return $e->status . ' ' . $e->errorCode;
        }
    }

    public function testRouteCanDangNhap(): void
    {
        self::assertSame('L', Router::match('POST', '/feedback')['auth']);
        self::assertSame('L', Router::match('GET', '/feedback/mine')['auth']);
    }

    public function testGuiKemNguCanhVaTraDungBai(): void
    {
        $uid = $this->makeUser(['role' => 'learner']);
        $bai = Db::insert('lessons', ['level_id' => 1, 'lesson_no' => 3, 'title' => 'B3', 'status' => 'published',
            'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00']);
        $res = (new FeedbackController())->create($this->req($uid, [
            'category' => 'content_error', 'message' => 'Từ 马 dịch sai nghĩa',
            'ctx' => ['part' => 'lt', 'levelId' => 1, 'lessonNo' => 3, 'route' => '/learn/flashcards/hsk/1/lesson/3', 'capTen' => 'HSK 1',
                'la' => '<script>', 'part2' => 'x'],
        ]));
        self::assertSame(201, $res->status);
        $f = Db::one('SELECT * FROM feedback WHERE user_id = ?', [$uid]);
        $ctx = json_decode((string) $f['ctx'], true);
        self::assertSame(['content_error', 'new', 0], [$f['category'], $f['status'], (int) $f['is_read']]);
        self::assertSame(['lt', 1, 3, $bai, 'HSK 1', 'phone'], [$ctx['part'], $ctx['levelId'], $ctx['lessonNo'], $ctx['lessonId'], $ctx['capTen'], $ctx['device']]);
        // Khoá lạ không lọt vào ngữ cảnh.
        self::assertArrayNotHasKey('la', $ctx);
    }

    public function testKiemNoiDungVaLoai(): void
    {
        $uid = $this->makeUser(['role' => 'learner']);
        $c = new FeedbackController();
        self::assertSame('422 VALIDATION', $this->loi(fn () => $c->create($this->req($uid, ['category' => 'content_error', 'message' => 'ngắn']))));
        self::assertSame('422 VALIDATION', $this->loi(fn () => $c->create($this->req($uid, ['category' => 'spam', 'message' => 'Nội dung đủ dài']))));
    }

    public function testGioiHanMoiGioVaDocLaiPhanHoi(): void
    {
        $uid = $this->makeUser(['role' => 'learner']);
        $c = new FeedbackController();
        for ($i = 0; $i < 10; $i++) {
            $c->create($this->req($uid, ['category' => 'feature', 'message' => "Góp ý số $i nhé"]));
        }
        self::assertSame('429 RATE_LIMITED', $this->loi(fn () => $c->create($this->req($uid, ['category' => 'feature', 'message' => 'Thêm một cái']))));

        Db::exec("UPDATE feedback SET reply = 'Cảm ơn bạn, đã sửa.', status = 'done', replied_at = created_at WHERE user_id = ? ORDER BY id LIMIT 1", [$uid]);
        $mine = $c->mine($this->req($uid));
        self::assertCount(10, $mine);
        self::assertSame('Cảm ơn bạn, đã sửa.', $mine[9]['reply']);
        // Không thấy góp ý của người khác.
        self::assertSame([], $c->mine($this->req($this->makeUser(['role' => 'learner']))));
    }
}
