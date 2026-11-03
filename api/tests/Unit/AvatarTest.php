<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\MeController;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Tests\DbTestCase;

/** Ảnh đại diện: nén nhỏ, xoá tệp cũ để không đầy ổ hosting. */
final class AvatarTest extends DbTestCase
{
    /** Ảnh PNG giả lập ảnh chụp điện thoại (chữ nhật, nặng hơn nhiều so với ảnh đại diện). */
    private function anh(int $w, int $h, int $mau): array
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, $mau);
        for ($i = 0; $i < 400; $i++) {
            imagesetpixel($im, ($i * 37) % $w, ($i * 53) % $h, 0xFFFFFF - $mau);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ava');
        imagepng($im, $tmp);
        return ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'name' => 'a.png'];
    }

    private function goi(int $uid, ?array $file, string $method = 'POST'): array
    {
        $r = new Request($method, '/me/avatar', files: $file ? ['file' => $file] : []);
        $r->user = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        return $method === 'POST' ? (new MeController())->avatar($r) : (new MeController())->removeAvatar($r);
    }

    private function tep(string $url): string
    {
        return Config::uploadsDir(substr($url, strlen(Config::uploadsUrl(''))));
    }

    public function testNenVe192VuongVaXoaAnhCuKhiDoi(): void
    {
        $uid = $this->makeUser();
        $a = $this->goi($uid, $this->anh(1600, 900, 0x3366CC))['avatarUrl'];
        self::assertStringStartsWith(Config::uploadsUrl('avatar/'), $a);
        [$w, $h, $type] = getimagesize($this->tep($a));
        self::assertSame([192, 192, IMAGETYPE_WEBP], [$w, $h, $type]);
        self::assertLessThan(20 * 1024, filesize($this->tep($a)));

        $b = $this->goi($uid, $this->anh(800, 1200, 0xCC3333))['avatarUrl'];
        self::assertNotSame($a, $b);
        self::assertFileDoesNotExist($this->tep($a));
        self::assertFileExists($this->tep($b));
        self::assertSame($b, Db::val('SELECT avatar_path FROM users WHERE id = ?', [$uid]));
    }

    public function testAnhDungChungKhongBiXoaKhiMotNguoiDoi(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $chung = $this->goi($u1, $this->anh(500, 500, 0x22AA55))['avatarUrl'];
        self::assertSame($chung, $this->goi($u2, $this->anh(500, 500, 0x22AA55))['avatarUrl']);
        $this->goi($u1, null, 'DELETE');
        self::assertNull(Db::val('SELECT avatar_path FROM users WHERE id = ?', [$u1]));
        self::assertFileExists($this->tep($chung), 'Người thứ hai vẫn đang dùng ảnh này');
        $this->goi($u2, null, 'DELETE');
        self::assertFileDoesNotExist($this->tep($chung));
    }

    public function testKhongDungAnhGoogleVaAnhNgoaiThuMucAvatar(): void
    {
        $uid = $this->makeUser(['avatar_path' => 'https://lh3.googleusercontent.com/a/xyz']);
        $this->goi($uid, null, 'DELETE');
        self::assertNull(Db::val('SELECT avatar_path FROM users WHERE id = ?', [$uid]));
        // Đường dẫn lạ không bao giờ bị đem đi xoá.
        MeController::xoaAnhDaiDien(Config::uploadsUrl('avatar/../../config.php'));
        self::assertTrue(true);
    }

    /** Xoá tài khoản: nhận "XOA" không dấu như màn Tài khoản hướng dẫn, kèm mật khẩu; xoá luôn ảnh. */
    public function testXoaTaiKhoanNhanXoaKhongDauVaDonAnh(): void
    {
        $uid = $this->makeUser(['password_hash' => \Zika\Core\Auth::hashPassword('MatKhau@123')]);
        $anh = $this->goi($uid, $this->anh(400, 400, 0x7744AA))['avatarUrl'];

        $xoa = function (array $body) use ($uid): void {
            $r = new Request('DELETE', '/me', body: $body);
            $r->user = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
            (new MeController())->destroy($r);
        };
        try {
            $xoa(['confirm' => 'XOA', 'password' => 'sai']);
            self::fail('Sai mật khẩu phải bị chặn');
        } catch (HttpError $e) {
            self::assertSame(422, $e->status);
        }
        $xoa(['confirm' => 'xoa', 'password' => 'MatKhau@123']);
        self::assertNull(Db::one('SELECT id FROM users WHERE id = ?', [$uid]));
        self::assertFileDoesNotExist($this->tep($anh));
    }

    public function testChanAnhQuaNhieuDiemAnh(): void
    {
        $uid = $this->makeUser();
        // PNG 6000×4100 một màu: tệp nhỏ nhưng giải nén ra ~98MB — phải bị chặn trước khi GD mở.
        $im = imagecreate(6000, 4100);
        imagecolorallocate($im, 10, 20, 30);
        $tmp = tempnam(sys_get_temp_dir(), 'ava');
        imagepng($im, $tmp);
        try {
            $this->goi($uid, ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'name' => 'x.png']);
            self::fail('Phải chặn ảnh quá lớn');
        } catch (HttpError $e) {
            self::assertSame(413, $e->status);
        }
    }
}
