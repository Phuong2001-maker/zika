<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\DemoLearnerController;
use Zika\Core\{Config, Db, HttpError, Request, Router};
use Zika\Tests\DbTestCase;

final class DemoLearnerTest extends DbTestCase
{
    private function req(array $body = [], int $id = 0): Request
    {
        $r = new Request('POST', '/admin/demo-learners', body: $body);
        $r->params = $id ? ['id' => (string) $id] : [];
        return $r;
    }

    private function learner(): array
    {
        return ['ten' => 'Người minh họa', 'muc' => 'vua', 'phut' => 120, 'ngay' => 5, 'bai' => 8, 'hien' => true, 'dangKy' => true, 'thi' => false];
    }

    private function reward(int $id): array
    {
        return ['nguoiId' => $id, 'cuocThi' => 'Thử sức HSK 2', 'thang' => '2026-11', 'ngay' => '2026-11-09', 'hang' => 1, 'giai' => 'Giải Nhất', 'tien' => 150000, 'trangThai' => 'cho', 'hien' => true];
    }

    public function testTatCaRouteChiDanhChoAdmin(): void
    {
        self::assertSame('A', Router::match('POST', '/admin/demo-learners/avatar')['auth']);
        foreach (['demo-learners', 'demo-rewards'] as $path) {
            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
                self::assertSame('A', Router::match($method, '/admin/' . $path . (in_array($method, ['PUT', 'DELETE']) ? '/1' : ''))['auth']);
            }
        }
    }

    public function testAvatarNenVuongLuuVaDungChungDonDepDung(): void
    {
        $c = new DemoLearnerController();
        $im = imagecreatetruecolor(600, 400);
        imagefill($im, 0, 0, 0x348765);
        $tmp = tempnam(sys_get_temp_dir(), 'demo-avatar-');
        imagepng($im, $tmp);
        try {
            $out = $c->avatar(new Request('POST', '/admin/demo-learners/avatar', files: ['file' => ['tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]]));
        } finally { unlink($tmp); }
        $url = $out['avatarUrl'];
        self::assertStringStartsWith(Config::uploadsUrl('demo-avatar/'), $url);
        $file = Config::uploadsDir(substr($url, strlen(Config::uploadsUrl(''))));
        $size = getimagesize($file);
        self::assertSame([192, 192, IMAGETYPE_WEBP], array_slice($size, 0, 3));
        $a = $c->saveLearner($this->req($this->learner() + ['avatarUrl' => $url]))['id'];
        $b = $c->saveLearner($this->req($this->learner() + ['avatarUrl' => $url]))['id'];
        self::assertSame($url, $c->learners($this->req())[0]['avatarUrl']);
        $c->saveLearner($this->req($this->learner() + ['avatarUrl' => null], $a));
        self::assertFileExists($file);
        $c->deleteLearner($this->req(id: $b));
        self::assertFileDoesNotExist($file);
    }

    public function testAvatarChanUrlNgoaiVaTepKhongPhaiAnh(): void
    {
        $c = new DemoLearnerController();
        foreach (['https://example.com/a.png', Config::uploadsUrl('demo-avatar/../../config.php')] as $url) {
            try { $c->saveLearner($this->req($this->learner() + ['avatarUrl' => $url])); self::fail('Phải chặn URL lạ.'); }
            catch (HttpError $e) { self::assertSame(422, $e->status); }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'demo-avatar-');
        file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        try {
            $c->avatar(new Request('POST', '/admin/demo-learners/avatar', files: ['file' => ['tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]]));
            self::fail('Phải chặn SVG.');
        } catch (HttpError $e) { self::assertSame(415, $e->status); }
        finally { unlink($tmp); }
    }

    public function testLuuDocSuaAnVaXoaKhongDoiDuLieuThat(): void
    {
        $c = new DemoLearnerController();
        $user = $this->makeUser();
        $id = $c->saveLearner($this->req($this->learner()))['id'];
        $giai = $c->saveReward($this->req($this->reward($id)))['id'];
        self::assertSame(120, $c->learners($this->req())[0]['phut']);
        self::assertSame(150000, $c->rewards($this->req())[0]['tien']);
        $c->saveLearner($this->req(array_replace($this->learner(), ['ten' => 'Tên mới', 'hien' => false]), $id));
        self::assertFalse($c->learners($this->req())[0]['hien']);
        self::assertSame('Tên mới', $c->rewards($this->req())[0]['tenNguoi']);
        $c->saveReward($this->req(array_replace($this->reward($id), ['trangThai' => 'da-trao', 'hien' => false]), $giai));
        self::assertFalse($c->rewards($this->req())[0]['hien']);
        self::assertSame('da-trao', $c->rewards($this->req())[0]['trangThai']);
        $c->deleteLearner($this->req(id: $id));
        self::assertSame([], $c->learners($this->req()));
        self::assertSame([], $c->rewards($this->req()));
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM users WHERE id = ?', [$user]));
    }

    public function testXoaMotGiaiGiuNguoiHoc(): void
    {
        $c = new DemoLearnerController();
        $id = $c->saveLearner($this->req($this->learner()))['id'];
        $giai = $c->saveReward($this->req($this->reward($id)))['id'];
        $c->deleteReward($this->req(id: $giai));
        self::assertCount(1, $c->learners($this->req()));
        self::assertSame([], $c->rewards($this->req()));
    }

    public function testDuLieuSaiBiChanVaKhongGhiDb(): void
    {
        $c = new DemoLearnerController();
        $id = $c->saveLearner($this->req($this->learner()))['id'];
        foreach ([['thang' => '2026-13'], ['ngay' => '2026-02-30'], ['tien' => -1], ['hang' => 0], ['trangThai' => 'khac']] as $sai) {
            try {
                $c->saveReward($this->req(array_replace($this->reward($id), $sai)));
                self::fail('Dữ liệu sai phải bị từ chối.');
            } catch (HttpError $e) { self::assertSame(422, $e->status); }
        }
        self::assertSame([], $c->rewards($this->req()));
        try { $c->saveReward($this->req($this->reward(999999))); self::fail('Không được dùng người không tồn tại.'); }
        catch (HttpError $e) { self::assertSame(404, $e->status); }
        try { $c->saveLearner($this->req(array_replace($this->learner(), ['phut' => -1]))); self::fail('Không được lưu số âm.'); }
        catch (HttpError $e) { self::assertSame(422, $e->status); }
        try { $c->deleteLearner($this->req(id: 999999)); self::fail('Không được xóa ID không tồn tại.'); }
        catch (HttpError $e) { self::assertSame(404, $e->status); }
    }
}
