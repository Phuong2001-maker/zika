<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\ContentController;
use Zika\Controllers\Admin\CurriculumController;
use Zika\Controllers\LearnController;
use Zika\Core\Db;
use Zika\Core\FileCache;
use Zika\Core\HttpError;
use Zika\Core\Json;
use Zika\Core\Request;
use Zika\Services\ContentBuilder;
use Zika\Services\Curricula;
use Zika\Services\ProgressService;
use Zika\Tests\DbTestCase;

/** Giáo trình là cha của bài: mỗi học viên chỉ thấy và học được bài của giáo trình mình. */
final class CurriculumTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Db::exec('UPDATE levels SET is_visible = 1 WHERE id = 1');
        FileCache::forgetAllContent();
    }

    private function giaoTrinh(string $ten = 'Hán ngữ'): int
    {
        return Db::insert('curricula', ['name' => $ten]);
    }

    private function bai(int $curriculumId, int $no, string $status = 'published'): int
    {
        return Db::insert('lessons', [
            'curriculum_id' => $curriculumId, 'level_id' => 1, 'lesson_no' => $no, 'title' => "Bài $no của $curriculumId",
            'status' => $status, 'cnt_words' => 3,
        ]);
    }

    private function nguoiHoc(?int $curriculumId = null): int
    {
        return $this->makeUser(['role' => 'learner', 'curriculum_id' => $curriculumId]);
    }

    private function req(string $method, string $path, int $userId, string $role = 'learner', array $query = [], array $body = []): Request
    {
        $r = new Request($method, $path, $query, $body);
        $r->user = ['id' => $userId, 'role' => $role];
        return $r;
    }

    /** @return array<string,mixed> */
    private function json(\Zika\Core\Response $res): array
    {
        return Json::decode($res->body, [])['data'];
    }

    public function testDuLieuCuThuocGiaoTrinhMacDinh(): void
    {
        $id = Db::insert('lessons', ['level_id' => 1, 'lesson_no' => 1, 'title' => 'Bài cũ']);
        self::assertSame(Curricula::DEFAULT_ID, (int) Db::val('SELECT curriculum_id FROM lessons WHERE id = ?', [$id]));

        $w = Db::insert('words', ['level_id' => 1, 'hanzi' => '好', 'pinyin' => 'hǎo', 'meaning_vi' => 'tốt']);
        self::assertSame(Curricula::DEFAULT_ID, (int) Db::val('SELECT curriculum_id FROM words WHERE id = ?', [$w]));
    }

    public function testHaiGiaoTrinhCungCoBaiMotVaCungMotTu(): void
    {
        $c = $this->giaoTrinh();
        $this->bai(Curricula::DEFAULT_ID, 1);
        $this->bai($c, 1);
        Db::insert('words', ['level_id' => 1, 'hanzi' => '好', 'pinyin' => 'hǎo', 'meaning_vi' => 'tốt']);
        Db::insert('words', ['curriculum_id' => $c, 'level_id' => 1, 'hanzi' => '好', 'pinyin' => 'hǎo', 'meaning_vi' => 'tốt']);

        self::assertSame(2, (int) Db::val('SELECT COUNT(*) FROM lessons WHERE level_id = 1 AND lesson_no = 1'));
        self::assertSame(2, (int) Db::val("SELECT COUNT(*) FROM words WHERE hanzi = '好'"));
    }

    public function testNguoiHocChiThayBaiCuaGiaoTrinhMinh(): void
    {
        $c = $this->giaoTrinh();
        $baiCu = $this->bai(Curricula::DEFAULT_ID, 1);
        $baiMoi = $this->bai($c, 1);

        $cu = $this->req('GET', '/learn/levels/1/lessons', $this->nguoiHoc());
        $cu->params = ['id' => '1'];
        self::assertSame([$baiCu], array_column($this->json((new LearnController())->levelLessons($cu))['lessons'], 'id'));

        $moi = $this->req('GET', '/learn/levels/1/lessons', $this->nguoiHoc($c));
        $moi->params = ['id' => '1'];
        self::assertSame([$baiMoi], array_column($this->json((new LearnController())->levelLessons($moi))['lessons'], 'id'));

        $cap = $this->req('GET', '/learn/levels', $this->nguoiHoc($c));
        self::assertSame(1, $this->json((new LearnController())->levels($cap))[0]['lessonCount']);
    }

    public function testBaiCuaGiaoTrinhKhacTraVe404(): void
    {
        $c = $this->giaoTrinh();
        $bai = $this->bai(Curricula::DEFAULT_ID, 1);
        $r = $this->req('GET', "/learn/lessons/$bai/flashcards", $this->nguoiHoc($c));
        $r->params = ['id' => (string) $bai, 'part' => 'flashcards'];

        $this->expectException(HttpError::class);
        (new LearnController())->lesson($r);
    }

    public function testBanCacheCuaGiaoTrinhNayKhongLotSangGiaoTrinhKia(): void
    {
        $c = $this->giaoTrinh();
        $this->bai(Curricula::DEFAULT_ID, 1);
        $this->bai(Curricula::DEFAULT_ID, 2);
        $this->bai($c, 1);

        foreach ([[null, 2], [$c, 1], [null, 2]] as [$gt, $soBai]) {
            $r = $this->req('GET', '/learn/levels', $this->nguoiHoc($gt));
            self::assertSame($soBai, $this->json((new LearnController())->levels($r))[0]['lessonCount']);
        }

        // Sửa bài ở cấp đó thì cache mọi giáo trình đều phải mất.
        $this->bai($c, 2);
        ContentBuilder::forgetLevel(1);
        $r = $this->req('GET', '/learn/levels', $this->nguoiHoc($c));
        self::assertSame(2, $this->json((new LearnController())->levels($r))[0]['lessonCount']);
    }

    public function testBaiKeTiepKhongNhayTheoSangGiaoTrinhKhac(): void
    {
        $c = $this->giaoTrinh();
        $this->bai(Curricula::DEFAULT_ID, 2);
        $motBai = $this->bai($c, 1);
        $l = Db::one('SELECT * FROM lessons WHERE id = ?', [$motBai]);
        self::assertNull(ContentBuilder::nextLesson($l, false));
    }

    public function testTienDoChiTinhBaiCuaGiaoTrinhDangHoc(): void
    {
        $c = $this->giaoTrinh();
        $baiCu = $this->bai(Curricula::DEFAULT_ID, 1);
        $this->bai($c, 1);
        $u = $this->nguoiHoc();

        ProgressService::complete($u, $baiCu, 'lt');
        self::assertSame(1, ProgressService::level($u, 1, 'lt')['doneCount']);

        Db::update('users', ['curriculum_id' => $c], ['id' => $u]);
        $moi = ProgressService::level($u, 1, 'lt');
        self::assertSame(0, $moi['doneCount']);
        self::assertSame(1, $moi['lessonCount']);
        self::assertSame([], $moi['lessons']);

        // Gán lại bộ cũ thì tiến độ còn nguyên.
        Db::update('users', ['curriculum_id' => null], ['id' => $u]);
        self::assertSame(1, ProgressService::level($u, 1, 'lt')['doneCount']);
    }

    public function testNguoiHocKhongGhiDuocTienDoBaiGiaoTrinhKhac(): void
    {
        $c = $this->giaoTrinh();
        $bai = $this->bai(Curricula::DEFAULT_ID, 1);

        $this->expectException(HttpError::class);
        ProgressService::save($this->nguoiHoc($c), $bai, 'lt', 1, 3, null);
    }

    public function testBienTapLamThuDuocBaiMoiGiaoTrinh(): void
    {
        $c = $this->giaoTrinh();
        $bai = $this->bai($c, 1);
        $staff = $this->makeUser(['role' => 'editor']);
        self::assertSame($bai, (int) ProgressService::visibleLesson($bai, $staff)['id']);
    }

    public function testTaoBaiDanhSoRiengTheoGiaoTrinh(): void
    {
        $c = $this->giaoTrinh();
        $this->bai(Curricula::DEFAULT_ID, 1);
        $this->bai(Curricula::DEFAULT_ID, 2);

        $r = $this->req('POST', '/admin/levels/1/lessons', $this->makeUser(['role' => 'admin']), 'admin', [], ['title' => 'Bài đầu', 'curriculumId' => $c]);
        $r->params = ['id' => '1'];
        $res = (new ContentController())->createLesson($r);

        self::assertSame(1, $this->json($res)['no']);
        self::assertSame($c, (int) Db::val('SELECT curriculum_id FROM lessons WHERE id = ?', [$this->json($res)['id']]));
    }

    public function testTaoBaiVaoGiaoTrinhKhongCoBiTuChoi(): void
    {
        $r = $this->req('POST', '/admin/levels/1/lessons', $this->makeUser(['role' => 'admin']), 'admin', [], ['title' => 'Bài đầu', 'curriculumId' => 999]);
        $r->params = ['id' => '1'];

        $this->expectException(HttpError::class);
        (new ContentController())->createLesson($r);
    }

    public function testNhapTuTheoGiaoTrinhCuaBaiVaKhongTrungGiuaHaiBo(): void
    {
        $c = $this->giaoTrinh();
        $bai = $this->bai($c, 1);
        Db::insert('words', ['level_id' => 1, 'hanzi' => '好', 'pinyin' => 'hǎo', 'meaning_vi' => 'tốt']);

        $r = $this->req('POST', '/admin/words/bulk', $this->makeUser(['role' => 'admin']), 'admin', [], ['levelId' => 1, 'lessonId' => $bai, 'items' => [['hanzi' => '好', 'pinyin' => 'hǎo', 'meaning' => 'tốt']]]);
        $out = (new ContentController())->bulkWords($r);

        self::assertSame(1, $out['created']);
        self::assertSame($c, (int) Db::val('SELECT curriculum_id FROM words WHERE lesson_id = ?', [$bai]));

        // Lần hai cùng giáo trình thì là trùng.
        self::assertSame(0, (new ContentController())->bulkWords($r)['created']);
    }

    public function testKhongXoaGiaoTrinhMacDinhVaGiaoTrinhConBai(): void
    {
        $c = $this->giaoTrinh();
        $this->bai($c, 1);
        foreach ([Curricula::DEFAULT_ID, $c] as $id) {
            $r = $this->req('DELETE', "/admin/curricula/$id", $this->makeUser(['role' => 'admin']), 'admin');
            $r->params = ['id' => (string) $id];
            try {
                (new CurriculumController())->delete($r);
                self::fail('Phải bị từ chối.');
            } catch (HttpError $e) {
                self::assertSame(409, $e->status);
            }
        }
    }

    public function testXoaGiaoTrinhTrongThiHocVienVeMacDinh(): void
    {
        $c = $this->giaoTrinh();
        $u = $this->nguoiHoc($c);
        $r = $this->req('DELETE', "/admin/curricula/$c", $this->makeUser(['role' => 'admin']), 'admin');
        $r->params = ['id' => (string) $c];
        (new CurriculumController())->delete($r);

        self::assertSame(Curricula::DEFAULT_ID, Curricula::ofUser($u));
    }

    public function testGanGiaoTrinhXoaLuotTongHopCu(): void
    {
        $c = $this->giaoTrinh();
        $u = $this->nguoiHoc();
        ProgressService::saveReview($u, 1, 'bt', 3, 10, ['attemptId' => 5]);

        $r = $this->req('PUT', '/admin/curricula/assign', $this->makeUser(['role' => 'admin']), 'admin', [], ['rows' => [['userId' => $u, 'curriculumId' => $c]]]);
        self::assertSame(['changed' => 1], (new CurriculumController())->assign($r));

        self::assertSame($c, Curricula::ofUser($u));
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM review_progress WHERE user_id = ?', [$u]));

        // Gán lại đúng giá trị cũ thì không tính là đổi.
        self::assertSame(['changed' => 0], (new CurriculumController())->assign($r));
    }

    public function testKhongGanGiaoTrinhChoNguoiKhongPhaiHocVien(): void
    {
        $c = $this->giaoTrinh();
        $staff = $this->makeUser(['role' => 'editor']);
        $r = $this->req('PUT', '/admin/curricula/assign', $this->makeUser(['role' => 'admin']), 'admin', [], ['rows' => [['userId' => $staff, 'curriculumId' => $c]]]);

        self::assertSame(['changed' => 0], (new CurriculumController())->assign($r));
    }

    public function testXemTruocTheoGiaoTrinhCuaBaiDangSoan(): void
    {
        $c = $this->giaoTrinh();
        $this->bai($c, 1, 'draft');
        $admin = $this->makeUser(['role' => 'admin']);

        $r = $this->req('GET', '/learn/levels/1/lessons', $admin, 'admin', ['preview' => '1', 'curriculum' => (string) $c]);
        $r->params = ['id' => '1'];
        self::assertCount(1, $this->json((new LearnController())->levelLessons($r))['lessons']);

        // Người học gửi tham số curriculum cũng không đổi được giáo trình.
        $hoc = $this->req('GET', '/learn/levels', $this->nguoiHoc(), 'learner', ['preview' => '1', 'curriculum' => (string) $c]);
        self::assertSame(Curricula::DEFAULT_ID, Curricula::forRequest($hoc));
    }
}
