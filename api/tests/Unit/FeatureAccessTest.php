<?php
declare(strict_types=1);
namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\FeatureAccessController;
use Zika\Controllers\MeController;
use Zika\Core\{Clock, Db, HttpError, Request, Router};
use Zika\Services\FeatureAccess;
use Zika\Tests\DbTestCase;

final class FeatureAccessTest extends DbTestCase
{
    private function req(string $path = '/x', string $role = 'learner', array $body = [], string $method = 'GET', ?int $uid = null): Request
    {
        $r = new Request($method, $path, body: $body);
        $r->user = ['id' => $uid ?? $this->makeUser(['role' => $role]), 'role' => $role];
        return $r;
    }

    private function set(string $key, bool $on, string $role = 'learner'): void
    {
        Db::exec('INSERT INTO feature_access (feature_key, role, enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)', [$key, $role, (int) $on]);
        FeatureAccess::clearCache();
    }

    public function testChaTatKhongGhiDeConVaCacVaiTroDocLap(): void
    {
        $this->set('books.audio', false);
        $this->set('books', false);
        self::assertFalse(FeatureAccess::allows('learner', 'books.answers'));
        self::assertTrue(FeatureAccess::matrix()['learner']['books.answers']);
        self::assertTrue(FeatureAccess::allows('admin', 'books.answers'));
        $this->set('books', true);
        self::assertTrue(FeatureAccess::allows('learner', 'books.answers'));
        self::assertFalse(FeatureAccess::allows('learner', 'books.audio'));
        self::assertTrue(FeatureAccess::allows('editor', 'books.audio'));
    }

    public function testLuuPhienBanVaChongGhiDeCauHinhCu(): void
    {
        $c = new FeatureAccessController();
        $r = $this->req(role: 'admin');
        $config = $c->index($r);
        $save = $this->req(role: 'admin', body: ['revision' => $config['revision'], 'changes' => [['feature' => 'community.chat', 'role' => 'learner', 'enabled' => true]]]);
        $out = $c->save($save);
        self::assertSame($config['revision'] + 1, $out['revision']);
        FeatureAccess::clearCache();
        self::assertTrue(FeatureAccess::allows('learner', 'community.chat'));
        self::assertSame(1, (int) Db::val("SELECT COUNT(*) FROM audit_log WHERE action = 'features.update'"));
        try { $c->save($save); self::fail('Phải chặn phiên bản cũ.'); }
        catch (HttpError $e) { self::assertSame(409, $e->status); }
    }

    public function testChanKhoaLaVaiTroLaVaKhoaTaiKhoan(): void
    {
        $c = new FeatureAccessController();
        foreach ([['feature' => 'khong-co', 'role' => 'learner', 'enabled' => true], ['feature' => 'books', 'role' => 'guest', 'enabled' => false], ['feature' => 'account', 'role' => 'admin', 'enabled' => false], ['feature' => ['books'], 'role' => 'admin', 'enabled' => true]] as $change) {
            try { $c->save($this->req(role: 'admin', body: ['revision' => 0, 'changes' => [$change]])); self::fail('Dữ liệu sai phải bị chặn.'); }
            catch (HttpError $e) { self::assertSame(422, $e->status); }
        }
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM feature_access'));
    }

    public function testDanhMucKhongConUngHoTrucTiep(): void
    {
        $keys = array_column(FeatureAccess::catalog(), 'key');
        self::assertNotContains('support.donate', $keys);
        self::assertContains('support.buddy', $keys);
        self::assertContains('support.links', $keys);
        // Dòng cũ còn trong DB không làm hỏng ma trận: chỉ mục trong danh mục mới được trả về.
        $this->set('support.donate', false);
        self::assertArrayNotHasKey('support.donate', FeatureAccess::matrix()['learner']);
    }

    public function testMacDinhVaChanApiKhiNhapLinkTrucTiep(): void
    {
        self::assertFalse(FeatureAccess::allows('learner', 'learn.tests'));
        self::assertFalse(FeatureAccess::allows('editor', 'community.chat'));
        self::assertTrue(FeatureAccess::allows('admin', 'community.chat'));
        $this->set('books.audio', false);
        $r = $this->req('/learn/books/hn1/nghe/1');
        try { FeatureAccess::enforce($r); self::fail('Phải chặn file nghe.'); }
        catch (HttpError $e) { self::assertSame('FEATURE_DISABLED', $e->errorCode); }
        $r = $this->req('/learn/books/hn1/dap-an/1');
        FeatureAccess::enforce($r);
        $this->set('books.points', false);
        try { FeatureAccess::enforce($this->req('/learn/books/hn1/mo/1', method: 'POST')); self::fail('Phải chặn dùng điểm khi điểm đã tắt.'); }
        catch (HttpError $e) { self::assertSame(403, $e->status); }
        $this->set('account.preferences', false);
        try { FeatureAccess::enforce($this->req('/me/settings')); self::fail('Phải chặn trang cài đặt đã tắt.'); }
        catch (HttpError $e) { self::assertSame(403, $e->status); }
        $this->set('community.chat', true);
        FeatureAccess::enforce($this->req('/chat'));
        self::assertSame('A', Router::match('PUT', '/admin/feature-access')['auth']);
        self::assertSame('L', Router::match('GET', '/me/features')['auth']);
    }

    public function testConTatKhongChanDangKyHayCacConKhac(): void
    {
        $this->set('community.contests.info', false);
        FeatureAccess::enforce($this->req('/contest/register', body: ['contestId' => 1], method: 'POST'));
        FeatureAccess::enforce($this->req('/contest/current'));
        self::assertTrue(FeatureAccess::allows('learner', 'community.contests.ranking'));
        $this->set('community.contests', false);
        try { FeatureAccess::enforce($this->req('/contest/register', method: 'POST')); self::fail('Tắt cha phải chặn con.'); }
        catch (HttpError $e) { self::assertSame(403, $e->status); }
    }

    public function testLuotThiDangLamVanLuuVaNopDuoc(): void
    {
        $uid = $this->makeUser();
        $mock = Db::insert('mock_tests', ['level_id' => 1, 'title' => 'Đề thử']);
        $id = Db::insert('test_attempts', ['user_id' => $uid, 'mock_test_id' => $mock,
            'question_ids' => '[]', 'option_maps' => '{}', 'answers' => '{}', 'flags' => '[]', 'listen_counts' => '{}',
            'started_at' => Clock::sql(), 'deadline_at' => Clock::sql(Clock::now()->modify('+10 minutes'))]);
        $this->set('learn', false);
        $this->set('community', false);
        foreach (['PATCH', 'POST'] as $method) {
            $r = $this->req("/tests/attempts/$id" . ($method === 'POST' ? '/submit' : ''), method: $method, uid: $uid);
            FeatureAccess::enforce($r);
        }
        $r = $this->req('/tests/start', body: ['mockTestId' => $mock], method: 'POST', uid: $uid);
        FeatureAccess::enforce($r);
        $features = (new MeController())->features($r);
        self::assertSame($mock, $features['activeTests'][0]['mockId']);
        try { FeatureAccess::enforce($this->req("/tests/attempts/$id/submit", method: 'POST')); self::fail('Không được dùng lượt của người khác.'); }
        catch (HttpError $e) { self::assertSame(403, $e->status); }
        Db::update('test_attempts', ['submitted_at' => Clock::sql()], ['id' => $id]);
        self::assertFalse(FeatureAccess::ongoing($r));
    }

    /** Server phải xét quyền theo đúng phần controller sẽ mở, không theo khoá lạ hay cách viết số khác. */
    public function testBatDauThiXetDungPhanControllerSeMo(): void
    {
        $hsk = Db::insert('mock_tests', ['level_id' => 1, 'title' => 'Đề HSK 3.0', 'kind' => 'hsk30']);
        $chan = function (array $body, string $vi, ?int $uid = null): void {
            try { FeatureAccess::enforce($this->req('/tests/start', body: $body, method: 'POST', uid: $uid)); self::fail($vi); }
            catch (HttpError $e) { self::assertSame(403, $e->status, $vi); }
        };

        // Mặc định Kiểm tra tắt với học viên: kèm mockId (controller không đọc) hay mockTestId cũng không lách sang HSK 3.0.
        $chan(['lessonId' => 5, 'mockId' => $hsk], 'lessonId + mockId phải xét theo Kiểm tra.');
        $chan(['lessonId' => 5, 'mockTestId' => $hsk], 'Có lessonId thì luôn xét theo Kiểm tra.');

        // Mở Kiểm tra, tắt HSK 3.0: viết "0" + id vẫn là đề HSK 3.0 (Validator đọc ra cùng số).
        $this->set('learn.tests', true);
        $this->set('learn.hsk30', false);
        $chan(['mockTestId' => '0' . $hsk], 'mockTestId "0xx" phải xét theo HSK 3.0.');
        $chan(['mockTestId' => (string) $hsk], 'mockTestId dạng chuỗi phải xét theo HSK 3.0.');

        // Lượt luyện tập dở chỉ cho làm tiếp đúng lượt đó, không mở lượt thi mới.
        $uid = $this->makeUser();
        Db::insert('test_attempts', ['user_id' => $uid, 'mock_test_id' => $hsk, 'mode' => 'practice',
            'question_ids' => '[]', 'option_maps' => '{}', 'answers' => '{}', 'flags' => '[]', 'listen_counts' => '{}',
            'started_at' => Clock::sql(), 'deadline_at' => Clock::sql(Clock::now()->modify('+10 minutes'))]);
        $chan(['mockTestId' => $hsk, 'mode' => 'exam'], 'Có lượt luyện tập dở không được mở lượt thi mới.', $uid);
        FeatureAccess::enforce($this->req('/tests/start', body: ['mockTestId' => $hsk, 'mode' => 'practice'], method: 'POST', uid: $uid));
    }
}
