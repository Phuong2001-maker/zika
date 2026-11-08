<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\BookController;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Router;
use Zika\Tests\DbTestCase;

/** Giá sách giáo trình: ảnh trang, file nghe tua được, đáp án — đọc từ sach.json do dung-sach.py dựng. */
final class BookPageTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $goc = Config::uploadsDir('giao-trinh');
        foreach (glob("$goc/*/sach.json") ?: [] as $f) {
            unlink($f);
        }
        // hsk1: không file nghe, không đáp án · hn3: đủ cả hai.
        $this->sach('hsk1', 'hsk', 1, [], null);
        $this->sach('hn3', 'hanngu', 3, [['so' => 1, 'bai' => 1, 'ten' => 'Từ mới', 'giay' => 75, 'kb' => 1]],
            ['soTrang' => 1, 'cao' => [1600], 'bai' => [['so' => 1, 'trang' => 1]]]);
        file_put_contents("$goc/hsk1/007.webp", 'RIFF-trang-7');
        file_put_contents("$goc/hsk1/bia.webp", 'RIFF-bia');
        @mkdir("$goc/hn3/nghe");
        @mkdir("$goc/hn3/dap-an");
        file_put_contents("$goc/hn3/nghe/01.mp3", '0123456789');
        file_put_contents("$goc/hn3/dap-an/001.webp", 'RIFF-dap-an');
    }

    /** @param list<array<string,mixed>> $nghe @param array<string,mixed>|null $dapAn */
    private function sach(string $ma, string $bo, int $cap, array $nghe, ?array $dapAn): void
    {
        $dir = Config::uploadsDir("giao-trinh/$ma");
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/sach.json", json_encode([
            'ma' => $ma, 'bo' => $bo, 'cap' => $cap, 'ten' => "Sách $ma", 'phu' => '', 'soTrang' => 9, 'rong' => 1200,
            'cao' => array_fill(0, 9, 1600), 'bai' => [['so' => 1, 'ten' => '你好', 'tenVi' => 'Xin chào', 'trang' => 3]],
            'nghe' => $nghe, 'dapAn' => $dapAn,
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string,string> $params @param array<string,string> $headers */
    private function req(string $role, array $params = [], array $headers = [], ?int $curriculum = null): Request
    {
        $r = new Request('GET', '/learn/books', headers: $headers);
        $r->params = $params;
        $r->user = ['id' => $this->makeUser(['role' => $role, 'curriculum_id' => $curriculum]), 'role' => $role];
        return $r;
    }

    private function hanNgu(): int
    {
        return Db::insert('curricula', ['name' => 'Hán ngữ 6 tập', 'level_label' => 'Quyển']);
    }

    public function testRouteDoiDangNhapVaKhongTinhGioiHan(): void
    {
        foreach (['/learn/books/hsk2/12', '/learn/books/hn6/bia', '/learn/books/hn3/nghe/4', '/learn/books/hn4/dap-an/2'] as $p) {
            $m = Router::match('GET', $p);
            self::assertSame('L', $m['auth'], $p);
            self::assertFalse($m['opts']['rate'] ?? true, $p);
        }
        self::assertSame('L', Router::match('GET', '/learn/books')['auth']);
        self::assertSame('hn1', Router::match('GET', '/learn/books/hn1')['params']['ma']);
        // Lối cũ của quản trị vẫn chạy cho trình duyệt còn giữ đường dẫn.
        self::assertSame('A', Router::match('GET', '/admin/books/hsk1/7')['auth']);
    }

    public function testRouteKhongNhanDuongDanLa(): void
    {
        $this->expectException(HttpError::class);
        Router::match('GET', '/learn/books/..%2F/7');
    }

    public function testHocVienChiThayBoSachCuaGiaoTrinhMinh(): void
    {
        $hn = $this->hanNgu();
        $c = new BookController();
        // Chưa phân quyền → giáo trình mặc định; trong test mặc định là HSK (id 1).
        self::assertSame(['hsk1'], array_column($c->index($this->req('learner')), 'ma'));
        self::assertSame(['hn3'], array_column($c->index($this->req('learner', [], [], $hn)), 'ma'));
        // Biên tập, quản trị thấy cả hai bộ, Hán ngữ trước.
        self::assertSame(['hn3', 'hsk1'], array_column($c->index($this->req('editor')), 'ma'));
    }

    public function testGiaSachChiBaoCoNgheVaDapAnKhiSachCo(): void
    {
        $ds = array_column((new BookController())->index($this->req('admin')), null, 'ma');
        self::assertSame([0, false], [$ds['hsk1']['soNghe'], $ds['hsk1']['coDapAn']]);
        self::assertSame([1, 1, true, 'Hán ngữ 6 tập'], [$ds['hn3']['soNghe'], $ds['hn3']['phutNghe'], $ds['hn3']['coDapAn'], $ds['hn3']['tenBo']]);
    }

    public function testSachCuaBoKhacTraVe404(): void
    {
        $c = new BookController();
        foreach (['show', 'page', 'audio'] as $ham) {
            try {
                $c->$ham($this->req('learner', ['ma' => 'hn3', 'trang' => '1', 'so' => '1']));
                self::fail("$ham phải 404");
            } catch (HttpError $e) {
                self::assertSame(404, $e->status);
            }
        }
    }

    public function testTraAnhRiengTu(): void
    {
        $res = (new BookController())->page($this->req('learner', ['ma' => 'hsk1', 'trang' => '7']));
        self::assertSame(200, $res->status);
        self::assertSame('image/webp', $res->headers['Content-Type']);
        self::assertStringStartsWith('private', $res->headers['Cache-Control']);
        self::assertSame('RIFF-trang-7', file_get_contents((string) $res->filePath));
        $bia = (new BookController())->page($this->req('admin', ['ma' => 'hsk1', 'trang' => 'bia']));
        self::assertSame('RIFF-bia', file_get_contents((string) $bia->filePath));
    }

    public function testThieuTrangThi404(): void
    {
        $this->expectException(HttpError::class);
        (new BookController())->page($this->req('admin', ['ma' => 'hsk1', 'trang' => '8']));
    }

    public function testDapAnChiCoOSachCoDapAn(): void
    {
        $c = new BookController();
        self::assertSame('RIFF-dap-an', file_get_contents((string) $c->answer($this->req('admin', ['ma' => 'hn3', 'trang' => '1']))->filePath));
        $this->expectException(HttpError::class);
        $c->answer($this->req('admin', ['ma' => 'hsk1', 'trang' => '7']));
    }

    private function nghe(string $range): Response
    {
        return (new BookController())->audio($this->req('admin', ['ma' => 'hn3', 'so' => '1'], $range === '' ? [] : ['range' => $range]));
    }

    private function guiRa(Response $res): string
    {
        ob_start();
        $res->send();
        return (string) ob_get_clean();
    }

    public function testFileNgheTuaDuocBangRange(): void
    {
        $ca = $this->nghe('');
        self::assertSame([200, 'audio/mpeg', 'bytes', '10'], [$ca->status, $ca->headers['Content-Type'], $ca->headers['Accept-Ranges'], $ca->headers['Content-Length']]);
        self::assertSame('0123456789', $this->guiRa($ca));

        $doan = $this->nghe('bytes=2-5');
        self::assertSame([206, 'bytes 2-5/10', '4'], [$doan->status, $doan->headers['Content-Range'], $doan->headers['Content-Length']]);
        self::assertSame('2345', $this->guiRa($doan));

        self::assertSame('6789', $this->guiRa($this->nghe('bytes=6-')));
        self::assertSame('789', $this->guiRa($this->nghe('bytes=-3')));
        self::assertSame('89', $this->guiRa($this->nghe('bytes=8-99')));
        self::assertSame(416, $this->nghe('bytes=10-')->status);
        // Nhiều đoạn một lúc thì trả cả file, trình duyệt vẫn phát được.
        self::assertSame(200, $this->nghe('bytes=0-1,4-5')->status);
    }
}
