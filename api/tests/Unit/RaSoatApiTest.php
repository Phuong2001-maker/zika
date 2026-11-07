<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\ContentController;
use Zika\Controllers\Admin\OpsController;
use Zika\Controllers\Admin\StatsController;
use Zika\Controllers\AuthController;
use Zika\Controllers\MeController;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Services\QuestionPayload;
use Zika\Tests\DbTestCase;

/** Các lỗi tìm ra khi rà soát API: quyền, đếm quản trị viên, đánh số bài, liên kết Google, đáp án trắc nghiệm. */
final class RaSoatApiTest extends DbTestCase
{
    /** @param array<string,mixed> $body @param array<string,string> $params */
    private function req(string $method, array $user, array $body = [], array $params = []): Request
    {
        $r = new Request($method, '/x', [], $body);
        $r->user = $user;
        $r->params = $params;
        return $r;
    }

    /** @return array<string,mixed> */
    private function dongNguoi(int $id): array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]) ?? [];
    }

    private function loi(callable $f): ?HttpError
    {
        try {
            $f();
            return null;
        } catch (HttpError $e) {
            return $e;
        }
    }

    /* ---------------- 6, 12: quản lý bài ---------------- */

    public function testXacNhanXoaBaiSaiBao422(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $bai = Db::insert('lessons', ['level_id' => 1, 'lesson_no' => 7, 'title' => 'B', 'status' => 'draft',
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00']);

        $e = $this->loi(fn () => (new ContentController())->deleteLesson(
            $this->req('DELETE', ['id' => $admin, 'role' => 'admin'], ['confirm' => 'sai'], ['id' => (string) $bai]),
        ));

        self::assertNotNull($e);
        self::assertSame(422, $e->status);
        self::assertNotNull(Db::val('SELECT id FROM lessons WHERE id = ?', [$bai]));
    }

    public function testDoiThuTuBaiDanhSoLaiTuMot(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $ids = [];
        foreach ([1, 2, 3] as $no) {
            $ids[$no] = Db::insert('lessons', ['level_id' => 1, 'lesson_no' => $no, 'title' => "B$no", 'status' => 'draft',
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00']);
        }

        (new ContentController())->reorderLessons($this->req(
            'PUT',
            ['id' => $admin, 'role' => 'admin'],
            ['ids' => [$ids[3], $ids[1], $ids[2]]],
            ['id' => '1'],
        ));

        self::assertSame(1, (int) Db::val('SELECT lesson_no FROM lessons WHERE id = ?', [$ids[3]]));
        self::assertSame(2, (int) Db::val('SELECT lesson_no FROM lessons WHERE id = ?', [$ids[1]]));
        self::assertSame(3, (int) Db::val('SELECT lesson_no FROM lessons WHERE id = ?', [$ids[2]]));
    }

    /* ---------------- 8: đáp án trắc nghiệm ---------------- */

    public function testPhuongAnTrongKhongLamLechDapAn(): void
    {
        $out = QuestionPayload::clean('pick', ['ask' => 'Q', 'options' => ['', 'B', 'C', 'D'], 'answer' => 1]);
        self::assertSame(['B', 'C', 'D'], $out['options']);
        self::assertSame(0, $out['answer'], 'Đáp án B phải trỏ về B sau khi bỏ ô trống');

        $out = QuestionPayload::clean('pick', ['ask' => 'Q', 'options' => ['A', '', 'C', 'D'], 'answer' => 3]);
        self::assertSame(2, $out['answer']);
    }

    public function testDapAnTroVaoOTrongThiBaoLoi(): void
    {
        $e = $this->loi(fn () => QuestionPayload::clean('pick', ['ask' => 'Q', 'options' => ['', 'B', 'C', 'D'], 'answer' => 0]));
        self::assertNotNull($e);
        self::assertSame(422, $e->status);
    }

    /* ---------------- 10: lần cuối online ---------------- */

    public function testTrangNguoiDungCoLanCuoiOnline(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $hv = $this->makeUser(['last_seen_at' => '2026-11-05 10:00:00']);

        $out = (new StatsController())->user($this->req('GET', ['id' => $admin, 'role' => 'admin'], [], ['id' => (string) $hv]));

        self::assertArrayHasKey('lastSeenAt', $out['user']);
        self::assertStringStartsWith('2026-11-05T10:00:00', $out['user']['lastSeenAt']);
        $chua = $this->makeUser();
        $out = (new StatsController())->user($this->req('GET', ['id' => $admin, 'role' => 'admin'], [], ['id' => (string) $chua]));
        self::assertNull($out['user']['lastSeenAt']);
    }

    /* ---------------- 13: quản trị viên cuối cùng ---------------- */

    public function testKhoaQuanTriKiaRoiTuXoaMinhThiBiChan(): void
    {
        $a = $this->makeUser(['role' => 'admin']);
        $b = $this->makeUser(['role' => 'admin']);

        (new StatsController())->lock($this->req('POST', $this->dongNguoi($a), [], ['id' => (string) $b]));
        self::assertSame('locked', Db::val('SELECT status FROM users WHERE id = ?', [$b]));

        $e = $this->loi(fn () => (new MeController())->destroy($this->req('DELETE', $this->dongNguoi($a), ['confirm' => 'XOA'])));
        self::assertNotNull($e, 'Còn mỗi admin hoạt động thì không được tự xoá');
        self::assertNotNull(Db::val('SELECT id FROM users WHERE id = ?', [$a]));
    }

    public function testKhongKhoaHoacHaQuyenQuanTriCuoiCung(): void
    {
        $a = $this->makeUser(['role' => 'admin']);
        $b = $this->makeUser(['role' => 'admin', 'status' => 'locked']);
        $editor = $this->makeUser(['role' => 'editor']);

        // Người gọi (editor giả lập) khoá hoặc hạ quyền admin duy nhất còn hoạt động.
        $caller = $this->dongNguoi($editor);
        self::assertNotNull($this->loi(fn () => (new StatsController())->lock($this->req('POST', $caller, [], ['id' => (string) $a]))));
        self::assertNotNull($this->loi(fn () => (new StatsController())->role($this->req('PATCH', $caller, ['role' => 'learner'], ['id' => (string) $a]))));
        self::assertNotNull($this->loi(fn () => (new StatsController())->delete($this->req('DELETE', $caller, [], ['id' => (string) $a]))));
        self::assertNotNull($this->loi(fn () => (new StatsController())->bulk($this->req('POST', $caller, ['ids' => [$a], 'action' => 'lock']))));
        self::assertSame('admin', Db::val('SELECT role FROM users WHERE id = ?', [$a]));
        self::assertSame('active', Db::val('SELECT status FROM users WHERE id = ?', [$a]));

        // Admin đã bị khoá thì hạ quyền được: không phải admin dùng được.
        (new StatsController())->role($this->req('PATCH', $caller, ['role' => 'learner'], ['id' => (string) $b]));
        self::assertSame('learner', Db::val('SELECT role FROM users WHERE id = ?', [$b]));
    }

    /* ---------------- 5: góp ý ---------------- */

    public function testBienTapKhongSuaHayTraLoiGopYNgoaiNoiDung(): void
    {
        $editor = $this->dongNguoi($this->makeUser(['role' => 'editor']));
        $admin = $this->dongNguoi($this->makeUser(['role' => 'admin']));
        $fb = Db::insert('feedback', ['category' => 'feature', 'message' => 'Thêm tính năng']);
        $noiDung = Db::insert('feedback', ['category' => 'content_error', 'message' => 'Sai chữ']);

        $ops = new OpsController();
        $e = $this->loi(fn () => $ops->updateFeedback($this->req('PATCH', $editor, ['status' => 'done'], ['id' => (string) $fb])));
        self::assertSame(403, $e?->status);
        $e = $this->loi(fn () => $ops->replyFeedback($this->req('POST', $editor, ['reply' => 'ok'], ['id' => (string) $fb])));
        self::assertSame(403, $e?->status);
        self::assertSame('new', Db::val('SELECT status FROM feedback WHERE id = ?', [$fb]));

        // Góp ý nội dung thì biên tập vẫn xử lý được, admin xử lý được mọi loại.
        $ops->updateFeedback($this->req('PATCH', $editor, ['status' => 'doing'], ['id' => (string) $noiDung]));
        self::assertSame('doing', Db::val('SELECT status FROM feedback WHERE id = ?', [$noiDung]));
        $ops->updateFeedback($this->req('PATCH', $admin, ['status' => 'doing'], ['id' => (string) $fb]));
        self::assertSame('doing', Db::val('SELECT status FROM feedback WHERE id = ?', [$fb]));
    }

    /* ---------------- 15: liên kết Google ---------------- */

    public function testLienKetGoogleVaoEmailChuaXacMinhXoaMatKhauVaPhien(): void
    {
        $ke = $this->makeUser(['password_hash' => 'x', 'email_verified_at' => null]);
        Db::insert('auth_sessions', [
            'user_id' => $ke, 'token_hash' => str_repeat('a', 64), 'last_used_at' => Clock::sql(),
            'expires_at' => Clock::sql(Clock::now()->modify('+1 day')),
        ]);

        AuthController::linkGoogle($this->dongNguoi($ke), ['sub' => 'g-1', 'picture' => null, 'name' => 'Chủ Thật']);

        $u = $this->dongNguoi($ke);
        self::assertNull($u['password_hash'], 'Mật khẩu do kẻ đăng ký trước đặt phải mất');
        self::assertNotNull($u['email_verified_at']);
        self::assertSame('g-1', $u['google_sub']);
        self::assertNotNull(Db::val('SELECT revoked_at FROM auth_sessions WHERE user_id = ?', [$ke]));
    }

    public function testLienKetGoogleVaoEmailDaXacMinhGiuMatKhauVaPhien(): void
    {
        $nguoi = $this->makeUser(['password_hash' => 'x']);
        Db::insert('auth_sessions', [
            'user_id' => $nguoi, 'token_hash' => str_repeat('b', 64), 'last_used_at' => Clock::sql(),
            'expires_at' => Clock::sql(Clock::now()->modify('+1 day')),
        ]);

        AuthController::linkGoogle($this->dongNguoi($nguoi), ['sub' => 'g-2', 'picture' => null, 'name' => 'Ai Đó']);

        self::assertSame('x', Db::val('SELECT password_hash FROM users WHERE id = ?', [$nguoi]));
        self::assertNull(Db::val('SELECT revoked_at FROM auth_sessions WHERE user_id = ?', [$nguoi]));
    }
}
