<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Controllers\Admin\BookController;
use Zika\Core\Config;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Router;

final class BookPageTest extends TestCase
{
    protected function setUp(): void
    {
        $dir = Config::uploadsDir('giao-trinh/hsk1');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/007.webp', 'RIFF-trang-7');
        file_put_contents($dir . '/bia.webp', 'RIFF-bia');
    }

    private function goi(string $ma, string $trang): \Zika\Core\Response
    {
        $r = new Request('GET', "/admin/books/$ma/$trang");
        $r->params = ['ma' => $ma, 'trang' => $trang];
        return (new BookController())->page($r);
    }

    public function testRouteChiChoQuanTriVaKhongTinhGioiHan(): void
    {
        $m = Router::match('GET', '/admin/books/hsk1/7');
        self::assertSame('A', $m['auth']);
        self::assertSame(['ma' => 'hsk1', 'trang' => '7'], $m['params']);
        self::assertFalse($m['opts']['rate']);
        self::assertSame('bia', Router::match('GET', '/admin/books/hsk3/bia')['params']['trang']);
    }

    public function testRouteKhongNhanDuongDanLa(): void
    {
        $this->expectException(HttpError::class);
        Router::match('GET', '/admin/books/..%2F/7');
    }

    public function testTraAnhRiengTu(): void
    {
        $res = $this->goi('hsk1', '7');
        self::assertSame(200, $res->status);
        self::assertSame('image/webp', $res->headers['Content-Type']);
        self::assertStringStartsWith('private', $res->headers['Cache-Control']);
        self::assertSame('RIFF-trang-7', file_get_contents((string) $res->filePath));

        self::assertSame('RIFF-bia', file_get_contents((string) $this->goi('hsk1', 'bia')->filePath));
    }

    public function testThieuTrangThi404(): void
    {
        try {
            $this->goi('hsk1', '8');
            self::fail('Phải 404');
        } catch (HttpError $e) {
            self::assertSame(404, $e->status);
        }
    }
}
