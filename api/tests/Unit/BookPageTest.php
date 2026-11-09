<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Controllers\BookController;
use Zika\Controllers\BookPointsController;
use Zika\Controllers\SupportController;
use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Router;
use Zika\Core\Settings;
use Zika\Services\BookAccess;
use Zika\Tests\DbTestCase;

/**
 * Giáo trình: ảnh trang, file nghe tua được, đáp án — đọc từ sach.json do dung-sach.py dựng — và luật chặn:
 * học viên chỉ nhận được file của bài đã mở bằng điểm, kèm mã ký của chính tài khoản.
 */
final class BookPageTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // site_settings giữ nguyên giữa các test (DbTestCase::KEEP): đặt lại khối test này có sửa.
        Settings::set('support', Settings::DEFAULTS['support']);
        // DbTestCase ẩn mọi cấp; màn đọc sách nay theo cây Ẩn/hiện nội dung nên phải bật lại, như dữ liệu thật.
        Db::exec('UPDATE levels SET is_visible = 1');
        $goc = Config::uploadsDir('giao-trinh');
        foreach (glob("$goc/*/sach.json") ?: [] as $f) {
            unlink($f);
        }
        // hsk1: 2 bài (trang 3–5, 6–9), 2 file nghe, không đáp án · hsk2: 1 bài · hn3: đủ file nghe và đáp án.
        $this->sach('hsk1', 'hsk', 1, [['so' => 1, 'trang' => 3], ['so' => 2, 'trang' => 6]],
            [['so' => 1, 'bai' => 1, 'ten' => 'Từ mới', 'giay' => 75, 'kb' => 1], ['so' => 2, 'bai' => 2, 'ten' => 'Từ mới', 'giay' => 60, 'kb' => 1]], null);
        $this->sach('hsk2', 'hsk', 2, [['so' => 1, 'trang' => 3]], [], null);
        $this->sach('hn3', 'hanngu', 3, [['so' => 1, 'trang' => 3]], [['so' => 1, 'bai' => 1, 'ten' => 'Từ mới', 'giay' => 75, 'kb' => 1]],
            ['soTrang' => 3, 'cao' => [1600, 1600, 1600], 'bai' => [['so' => 1, 'trang' => 2]]]);
        foreach (['hsk1', 'hsk2', 'hn3'] as $ma) {
            @mkdir("$goc/$ma/nghe");
            @mkdir("$goc/$ma/dap-an");
            file_put_contents("$goc/$ma/bia.webp", 'RIFF-bia');
            foreach ([2, 4, 7] as $n) {
                file_put_contents(sprintf('%s/%s/%03d.webp', $goc, $ma, $n), "RIFF-$ma-$n");
            }
        }
        file_put_contents("$goc/hsk1/nghe/01.mp3", '0123456789');
        file_put_contents("$goc/hsk1/nghe/02.mp3", 'abcdefghij');
        file_put_contents("$goc/hn3/nghe/01.mp3", '0123456789');
        file_put_contents("$goc/hn3/dap-an/001.webp", 'RIFF-dap-an-1');
        file_put_contents("$goc/hn3/dap-an/002.webp", 'RIFF-dap-an-2');
    }

    /** @param list<array<string,int>> $bai @param list<array<string,mixed>> $nghe @param array<string,mixed>|null $dapAn */
    private function sach(string $ma, string $bo, int $cap, array $bai, array $nghe, ?array $dapAn): void
    {
        $dir = Config::uploadsDir("giao-trinh/$ma");
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/sach.json", json_encode([
            'ma' => $ma, 'bo' => $bo, 'cap' => $cap, 'ten' => "Sách $ma", 'phu' => '', 'soTrang' => 9, 'rong' => 1200,
            'cao' => array_fill(0, 9, 1600),
            'bai' => array_map(static fn ($b) => $b + ['ten' => '你好', 'tenVi' => 'Xin chào'], $bai),
            'nghe' => $nghe, 'dapAn' => $dapAn,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function nguoi(string $role = 'learner', ?int $curriculum = null): int
    {
        return $this->makeUser(['role' => $role, 'curriculum_id' => $curriculum]);
    }

    /**
     * Request của người `uid`; `ve` = true thì tự gắn mã ký hợp lệ của quyển trong params.
     * @param array<string,string> $params @param array<string,string> $headers @param array<string,mixed> $body
     */
    private function req(int $uid, array $params = [], bool|string $ve = true, array $headers = [], array $body = []): Request
    {
        $role = (string) Db::val('SELECT role FROM users WHERE id = ?', [$uid]);
        $t = $ve === true ? BookAccess::ve($uid, $params['ma'] ?? '') : ($ve === false ? '' : $ve);
        $r = new Request('GET', '/learn/books', query: $t === '' ? [] : ['t' => $t], body: $body, headers: $headers);
        $r->params = $params;
        $r->user = ['id' => $uid, 'role' => $role];
        return $r;
    }

    private function hanNgu(): int
    {
        return Db::insert('curricula', ['name' => 'Hán ngữ 6 tập', 'level_label' => 'Quyển']);
    }

    /** Mã lỗi khi gọi, '' nếu chạy được. */
    private function loi(callable $fn): string
    {
        try {
            $fn();
            return '';
        } catch (HttpError $e) {
            return $e->status . ' ' . $e->errorCode;
        }
    }

    private function trang(int $uid, string $ma, string $trang, bool|string $ve = true): string
    {
        return $this->loi(fn () => (new BookController())->page($this->req($uid, ['ma' => $ma, 'trang' => $trang], $ve)));
    }

    private function moBai(int $uid, string $ma, int $bai): void
    {
        Db::insert('book_unlocks', ['user_id' => $uid, 'ma' => $ma, 'bai' => $bai, 'created_at' => Clock::sql()]);
    }

    private function ngay(string $ngay): void
    {
        Clock::freeze(new DateTimeImmutable("$ngay 09:00:00", Clock::tz()));
    }

    // ------------------------------------------------------------------ route

    public function testRouteDoiDangNhapVaKhongTinhGioiHan(): void
    {
        foreach (['/learn/books/hsk2/12', '/learn/books/hn6/bia', '/learn/books/hn3/nghe/4', '/learn/books/hn4/dap-an/2'] as $p) {
            $m = Router::match('GET', $p);
            self::assertSame('L', $m['auth'], $p);
            self::assertFalse($m['opts']['rate'] ?? true, $p);
        }
        self::assertSame('L', Router::match('GET', '/learn/books')['auth']);
        self::assertSame('hn1', Router::match('GET', '/learn/books/hn1')['params']['ma']);
        self::assertSame('L', Router::match('POST', '/learn/books/hn1/mo/5')['auth']);
        self::assertSame('L', Router::match('GET', '/learn/books/hn1/quyen')['auth']);
        self::assertSame('A', Router::match('POST', '/admin/book-points/adjust')['auth']);
    }

    public function testRouteKhongNhanDuongDanLa(): void
    {
        $this->expectException(HttpError::class);
        Router::match('GET', '/learn/books/..%2F/7');
    }

    // ------------------------------------------------------------------ kệ sách

    public function testHocVienChiThayBoSachCuaGiaoTrinhMinh(): void
    {
        $hn = $this->hanNgu();
        $c = new BookController();
        // Chưa phân quyền → giáo trình mặc định; trong test mặc định là HSK (id 1).
        self::assertSame(['hsk1', 'hsk2'], array_column($c->index($this->req($this->nguoi())), 'ma'));
        self::assertSame(['hn3'], array_column($c->index($this->req($this->nguoi('learner', $hn))), 'ma'));
        // Biên tập, quản trị thấy cả hai bộ, Hán ngữ trước.
        self::assertSame(['hn3', 'hsk1', 'hsk2'], array_column($c->index($this->req($this->nguoi('editor'))), 'ma'));
    }

    public function testKeSachKhoaQuyenChuaHocToi(): void
    {
        $uid = $this->nguoi();
        $ds = array_column((new BookController())->index($this->req($uid)), null, 'ma');
        self::assertSame([false, 0], [$ds['hsk1']['khoa'], $ds['hsk1']['baiMo']]);
        self::assertTrue($ds['hsk2']['khoa']);
        $this->moBai($uid, 'hsk1', 1);
        $this->moBai($uid, 'hsk1', 2);
        $ds = array_column((new BookController())->index($this->req($uid)), null, 'ma');
        self::assertSame([2, false], [$ds['hsk1']['baiMo'], $ds['hsk2']['khoa']]);
        // Quản trị không bị khoá quyển nào.
        foreach ((new BookController())->index($this->req($this->nguoi('admin'))) as $q) {
            self::assertFalse($q['khoa'], $q['ma']);
        }
    }

    public function testGiaSachChiBaoCoNgheVaDapAnKhiSachCo(): void
    {
        $ds = array_column((new BookController())->index($this->req($this->nguoi('admin'))), null, 'ma');
        self::assertSame([0, false], [$ds['hsk2']['soNghe'], $ds['hsk2']['coDapAn']]);
        self::assertSame([1, 1, true, 'Hán ngữ 6 tập'], [$ds['hn3']['soNghe'], $ds['hn3']['phutNghe'], $ds['hn3']['coDapAn'], $ds['hn3']['tenBo']]);
    }

    public function testSachCuaBoKhacTraVe404(): void
    {
        $uid = $this->nguoi();
        $c = new BookController();
        foreach (['show', 'page', 'audio', 'access'] as $ham) {
            self::assertSame('404 NOT_FOUND', $this->loi(fn () => $c->$ham($this->req($uid, ['ma' => 'hn3', 'trang' => '1', 'so' => '1']))), $ham);
        }
    }

    // ------------------------------------------------------------------ chặn file

    public function testBaiChuaMoKhongTraFileNao(): void
    {
        $uid = $this->nguoi();
        $c = new BookController();
        // Trang đầu sách của quyển đang học tới thì đọc được; bìa luôn xem được.
        self::assertSame('', $this->trang($uid, 'hsk1', '2'));
        self::assertSame('', $this->trang($uid, 'hsk2', 'bia', false));
        // Bài 1 chưa mở: ảnh trang, file nghe đều bị chặn.
        self::assertSame('403 LOCKED', $this->trang($uid, 'hsk1', '4'));
        self::assertSame('403 LOCKED', $this->loi(fn () => $c->audio($this->req($uid, ['ma' => 'hsk1', 'so' => '1']))));
        // Quyển sau còn khoá cả trang đầu.
        self::assertSame('403 LOCKED', $this->trang($uid, 'hsk2', '2'));

        $this->moBai($uid, 'hsk1', 1);
        self::assertSame('', $this->trang($uid, 'hsk1', '4'));
        self::assertSame('', $this->loi(fn () => $c->audio($this->req($uid, ['ma' => 'hsk1', 'so' => '1']))));
        self::assertSame('403 LOCKED', $this->trang($uid, 'hsk1', '7'));
        self::assertSame('403 LOCKED', $this->loi(fn () => $c->audio($this->req($uid, ['ma' => 'hsk1', 'so' => '2']))));
    }

    public function testDapAnTheoBaiDaMo(): void
    {
        $hn = $this->hanNgu();
        $uid = $this->nguoi('learner', $hn);
        $c = new BookController();
        $dapAn = fn (string $trang) => $this->loi(fn () => $c->answer($this->req($uid, ['ma' => 'hn3', 'trang' => $trang])));
        // Trang 1 của đáp án là trang đầu (trước Bài 1), trang 2 là đáp án Bài 1.
        self::assertSame('', $dapAn('1'));
        self::assertSame('403 LOCKED', $dapAn('2'));
        $this->moBai($uid, 'hn3', 1);
        self::assertSame('', $dapAn('2'));
        self::assertSame('RIFF-dap-an-2', file_get_contents((string) $c->answer($this->req($uid, ['ma' => 'hn3', 'trang' => '2']))->filePath));
        // Quyển không có đáp án.
        self::assertSame('404 NOT_FOUND', $this->loi(fn () => $c->answer($this->req($this->nguoi('admin'), ['ma' => 'hsk1', 'trang' => '7']))));
    }

    public function testDuongDanPhaiCoMaKyCuaChinhTaiKhoan(): void
    {
        $a = $this->nguoi();
        $b = $this->nguoi();
        $this->moBai($a, 'hsk1', 1);
        $this->moBai($b, 'hsk1', 1);
        self::assertSame('403 TOKEN_INVALID', $this->trang($a, 'hsk1', '4', false));
        // Link của người khác, của quyển khác, hay bị sửa đều không mở được.
        self::assertSame('403 TOKEN_INVALID', $this->trang($a, 'hsk1', '4', BookAccess::ve($b, 'hsk1')));
        self::assertSame('403 TOKEN_INVALID', $this->trang($a, 'hsk1', '4', BookAccess::ve($a, 'hsk2')));
        self::assertSame('403 TOKEN_INVALID', $this->trang($a, 'hsk1', '4', BookAccess::ve($a, 'hsk1') . 'x'));
        // Hết hạn sau 6 giờ.
        $ve = BookAccess::ve($a, 'hsk1');
        self::assertSame('', $this->trang($a, 'hsk1', '4', $ve));
        Clock::freeze(Clock::now()->modify('+' . (BookAccess::VE_GIAY + 1) . ' seconds'));
        self::assertSame('403 TOKEN_INVALID', $this->trang($a, 'hsk1', '4', $ve));
        // Kiểm cả file nghe (trình duyệt hỏi từng đoạn khi tua).
        self::assertSame('403 TOKEN_INVALID', $this->loi(fn () => (new BookController())->audio($this->req($a, ['ma' => 'hsk1', 'so' => '1'], false))));
    }

    public function testQuanTriDocTuDo(): void
    {
        $admin = $this->nguoi('admin');
        $res = (new BookController())->page($this->req($admin, ['ma' => 'hsk1', 'trang' => '7']));
        self::assertSame([200, 'image/webp'], [$res->status, $res->headers['Content-Type']]);
        self::assertStringStartsWith('private', $res->headers['Cache-Control']);
        self::assertSame('RIFF-hsk1-7', file_get_contents((string) $res->filePath));
        // Quản trị bấm mở bài không tốn điểm, không ghi gì.
        (new BookController())->unlock($this->req($admin, ['ma' => 'hsk2', 'bai' => '1']));
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM book_points'));
        // Vẫn phải có mã ký.
        self::assertSame('403 TOKEN_INVALID', $this->trang($admin, 'hsk1', '7', false));
    }

    public function testThieuTrangThi404(): void
    {
        self::assertSame('404 NOT_FOUND', $this->trang($this->nguoi('admin'), 'hsk1', '8'));
    }

    // ------------------------------------------------------------------ điểm

    public function testDiemNgayKhongBuNgayKhongBam(): void
    {
        $uid = $this->nguoi();
        $this->ngay('2026-11-09');
        self::assertFalse(BookAccess::daNhanHomNay($uid));
        self::assertSame(3, BookAccess::nhanDiemNgay($uid));
        self::assertSame(0, BookAccess::nhanDiemNgay($uid));
        self::assertTrue(BookAccess::daNhanHomNay($uid));
        self::assertSame(3, BookAccess::soDiem($uid));
        $this->ngay('2026-11-10');
        self::assertSame(1, BookAccess::nhanDiemNgay($uid));
        self::assertSame(0, BookAccess::nhanDiemNgay($uid));
        // 11/11 không bấm; 12/11 bấm vẫn chỉ +1.
        $this->ngay('2026-11-12');
        self::assertSame(1, BookAccess::nhanDiemNgay($uid));
        self::assertSame(5, BookAccess::soDiem($uid));
    }

    public function testMoBaiLanLuotVaTruDiem(): void
    {
        $uid = $this->nguoi();
        $c = new BookController();
        $mo = fn (string $ma, string $bai) => $this->loi(fn () => $c->unlock($this->req($uid, ['ma' => $ma, 'bai' => $bai])));
        self::assertSame('409 NO_POINTS', $mo('hsk1', '1'));
        BookAccess::nhanDiemNgay($uid);
        // Phải mở lần lượt.
        self::assertSame('422 VALIDATION', $mo('hsk1', '2'));
        self::assertSame('422 VALIDATION', $mo('hsk2', '1'));
        self::assertSame('', $mo('hsk1', '1'));
        // Bấm lại bài đã mở không trừ thêm.
        self::assertSame('', $mo('hsk1', '1'));
        self::assertSame(2, BookAccess::soDiem($uid));
        $q = $c->access($this->req($uid, ['ma' => 'hsk1']));
        self::assertSame([[1], ['ma' => 'hsk1', 'bai' => 2, 'tenSach' => 'Sách hsk1'], 2, false], [$q['mo'], $q['ke'], $q['diem'], $q['khoa']]);
        self::assertTrue(BookAccess::veHopLe($q['ve'], $uid, 'hsk1'));
        self::assertSame('', $mo('hsk1', '2'));
        // Hết quyển 1 thì quyển 2 mở trang đầu và mở bài được.
        self::assertSame('', $this->trang($uid, 'hsk2', '2'));
        self::assertSame('', $mo('hsk2', '1'));
        self::assertSame(0, BookAccess::soDiem($uid));
        self::assertNull($c->access($this->req($uid, ['ma' => 'hsk2']))['ke']);
        self::assertSame(['first', 'daily', 'unlock', 'unlock', 'unlock'], Db::col('SELECT kind FROM book_points WHERE user_id = ? ORDER BY id', [$uid]));
    }

    public function testNhanDiemRiengKhongPhuThuocLinkShopee(): void
    {
        Settings::set('support', ['enabled' => true]);
        $link = Db::insert('affiliate_links', ['name' => 'Túi', 'url' => 'https://s.shopee.vn/abc', 'place_icon' => 1, 'place_fund' => 0]);
        $uid = $this->nguoi();
        $s = new SupportController();
        $go = new Request('GET', "/go/$link", query: ['place' => 'icon']);
        $go->params = ['id' => (string) $link];
        $go->user = ['id' => $uid, 'role' => 'learner'];
        self::assertSame(302, $s->go($go)->status);
        self::assertSame(0, BookAccess::soDiem($uid), 'Bấm Shopee chỉ ghi ủng hộ, không cộng điểm mở bài');
        $p = (new BookPointsController())->daily($this->req($uid));
        self::assertSame([3, true, false], [$p['diem'], $p['homNay'], $p['lanDau']]);
        self::assertSame(1, (int) Db::val("SELECT COUNT(*) FROM affiliate_events WHERE kind = 'click' AND user_id = ?", [$uid]));
    }

    public function testNutNhanDiemCongThangKhiKhongCoLinkShopee(): void
    {
        $uid = $this->nguoi();
        $p = (new BookPointsController())->daily($this->req($uid));
        self::assertSame([3, true, false], [$p['diem'], $p['homNay'], $p['lanDau']]);
    }

    public function testQuanTriXemDanhSachDiemHocVien(): void
    {
        $a = $this->nguoi();
        $b = $this->nguoi();
        $this->nguoi('editor');
        BookAccess::nhanDiemNgay($a);
        $this->moBai($a, 'hsk1', 1);
        $res = (new \Zika\Controllers\Admin\BookPointsController())->users($this->req($this->nguoi('admin')));
        $j = json_decode($res->body, true);
        // Chỉ học viên, xếp theo điểm giảm dần; người chưa nhận lần nào có 0 điểm, chưa có lần nhận.
        self::assertSame([2, [$a, $b], [3, 0], [1, 0]], [
            $j['meta']['total'], array_column($j['data'], 'id'), array_column($j['data'], 'diem'), array_column($j['data'], 'baiMo'),
        ]);
        self::assertNotNull($j['data'][0]['lastClaim']);
        self::assertNull($j['data'][1]['lastClaim']);
    }

    public function testQuanTriCongTruTayKhongAmDiem(): void
    {
        $uid = $this->nguoi();
        $admin = $this->nguoi('admin');
        BookAccess::congTay($uid, 2, 'Tặng', $admin);
        self::assertSame(2, BookAccess::soDiem($uid));
        $this->expectException(HttpError::class);
        BookAccess::congTay($uid, -3, '', $admin);
    }

    public function testQuanTriMoCaQuyenKhongTruDiem(): void
    {
        $hv = $this->nguoi();
        $admin = $this->nguoi('admin');
        $qt = new \Zika\Controllers\Admin\BookPointsController();
        $mo = fn (int $uid, string $ma) => $qt->openBook($this->req($admin, ve: false, body: ['userId' => $uid, 'ma' => $ma]));
        BookAccess::nhanDiemNgay($hv);

        // hsk2 chưa tới lượt (hsk1 còn khoá) vẫn mở được cả quyển, không mất điểm nào.
        self::assertSame(['moThem' => 1], $mo($hv, 'hsk2'));
        self::assertSame(3, BookAccess::soDiem($hv));
        self::assertSame('', $this->trang($hv, 'hsk2', '4'));
        // Thứ tự mở bằng điểm không đổi: bài tiếp theo vẫn là hsk1 bài 1.
        self::assertSame(['ma' => 'hsk1', 'bai' => 1], BookAccess::trangThai($hv, 'hsk')['ke']);

        // Mở lại quyển đã mở hết thì không ghi thêm gì; sổ điểm có đúng một dòng 0 điểm.
        self::assertSame(['moThem' => 0], $mo($hv, 'hsk2'));
        self::assertSame([[0, 'admin']], array_map(static fn ($x) => [(int) $x['delta'], $x['kind']],
            Db::all("SELECT delta, kind FROM book_points WHERE user_id = ? AND khoa LIKE 'moquyen:hsk2:%'", [$hv])));

        // Quyển của bộ khác (học viên HSK, quyển Hán ngữ) và tài khoản biên tập: từ chối.
        self::assertNotSame('', $this->loi(fn () => $mo($hv, 'hn3')));
        self::assertSame(0, (int) Db::val("SELECT COUNT(*) FROM book_unlocks WHERE user_id = ? AND ma = 'hn3'", [$hv]));
        self::assertNotSame('', $this->loi(fn () => $mo($this->nguoi('editor'), 'hsk1')));

        // Học viên Hán ngữ mở được quyển Hán ngữ.
        $hn = $this->nguoi('learner', $this->hanNgu());
        self::assertSame(['moThem' => 1], $mo($hn, 'hn3'));

        // Hộp Chi tiết liệt kê các quyển của giáo trình học viên kèm số bài đã mở.
        $q = new Request('GET', '/admin/book-points/user', ['q' => (string) $hv]);
        $q->user = ['id' => $admin, 'role' => 'admin'];
        self::assertSame([['hsk1', 2, 0], ['hsk2', 1, 1]],
            array_map(static fn ($b) => [$b['ma'], $b['tong'], $b['daMo']], $qt->user($q)['books']));
    }

    // ------------------------------------------------------------------ cây Ẩn/hiện nội dung

    /** Bài trong dữ liệu ứng với bài sách: cùng giáo trình, level_id = cấp của quyển, lesson_no = số bài sách. */
    private function baiDuLieu(int $cap, int $so, string $status, int $curriculum = 1): void
    {
        Db::insert('lessons', ['curriculum_id' => $curriculum, 'level_id' => $cap, 'lesson_no' => $so, 'title' => "Bài $so", 'status' => $status]);
    }

    public function testBaiAnTrongCayChanCaManDocSach(): void
    {
        // hsk1: bài 1 đang hiện, bài 2 ẩn. hsk2 bài 1 chưa có trong dữ liệu: không bị chặn.
        $this->baiDuLieu(1, 1, 'published');
        $this->baiDuLieu(1, 2, 'draft');
        $uid = $this->nguoi();
        $c = new BookController();

        $ke = array_column($c->index($this->req($uid)), null, 'ma');
        self::assertSame([1, 1], [$ke['hsk1']['soBai'], $ke['hsk1']['soNghe']], 'Kệ sách chỉ đếm bài và file nghe đang hiện');

        $s = $c->show($this->req($uid, ['ma' => 'hsk1']));
        self::assertSame([2], $s['quyen']['an']);
        self::assertSame(['', ''], [$s['bai'][1]['ten'], $s['bai'][1]['tenVi']], 'Không lộ tên bài ẩn');
        self::assertSame('你好', $s['bai'][0]['ten']);
        self::assertSame([1], array_column($s['nghe'], 'bai'), 'Không gửi file nghe của bài ẩn');

        // Đã mở bằng điểm từ trước vẫn không đọc được: ảnh trang, file nghe, mở bài đều 404.
        $this->moBai($uid, 'hsk1', 1);
        $this->moBai($uid, 'hsk1', 2);
        self::assertSame('', $this->trang($uid, 'hsk1', '4'));
        self::assertSame('404 NOT_FOUND', $this->trang($uid, 'hsk1', '7'));
        self::assertSame('404 NOT_FOUND', $this->loi(fn () => $c->audio($this->req($uid, ['ma' => 'hsk1', 'so' => '2']))));
        self::assertSame([1], $c->access($this->req($uid, ['ma' => 'hsk1']))['mo']);
        self::assertSame('404 NOT_FOUND', $this->loi(fn () => $c->unlock($this->req($uid, ['ma' => 'hsk1', 'bai' => '2']))));

        // Quản trị cũng bị chặn như ở 6 phần học; bài khác vẫn đọc tự do.
        $admin = $this->nguoi('admin');
        self::assertSame('404 NOT_FOUND', $this->trang($admin, 'hsk1', '7'));
        self::assertSame('', $this->trang($admin, 'hsk1', '4'));

        // Bật lại bài thì đọc được ngay, bài đã mở bằng điểm vẫn còn.
        Db::exec("UPDATE lessons SET status = 'published' WHERE level_id = 1 AND lesson_no = 2");
        self::assertSame('', $this->trang($uid, 'hsk1', '7'));
        self::assertSame([1, 2], $c->access($this->req($uid, ['ma' => 'hsk1']))['mo']);
    }

    public function testBaiAnBiBoQuaKhiTinhBaiMoTiep(): void
    {
        $this->baiDuLieu(1, 2, 'draft');
        $uid = $this->nguoi();
        $this->moBai($uid, 'hsk1', 1);
        // Bài 2 ẩn: bài mở tiếp nhảy sang hsk2 bài 1, không kẹt trước bài không ai mở được.
        self::assertSame(['ma' => 'hsk2', 'bai' => 1], BookAccess::trangThai($uid, 'hsk')['ke']);
        BookAccess::nhanDiemNgay($uid);
        self::assertSame('', $this->loi(fn () => (new BookController())->unlock($this->req($uid, ['ma' => 'hsk2', 'bai' => '1']))));
        self::assertSame('', $this->trang($uid, 'hsk2', '4'));
    }

    public function testAnCapHoacGiaoTrinhThiQuyenBienMatKhoiKe(): void
    {
        $uid = $this->nguoi();
        $admin = $this->nguoi('admin');
        $c = new BookController();
        // Ẩn cấp 2 của giáo trình HSK: hsk2 biến khỏi kệ, mọi đường dẫn kể cả bìa báo 404, với cả quản trị.
        Db::insert('curriculum_levels', ['curriculum_id' => 1, 'level_id' => 2, 'is_visible' => 0]);
        self::assertSame(['hsk1'], array_column($c->index($this->req($uid)), 'ma'));
        self::assertNotContains('hsk2', array_column($c->index($this->req($admin)), 'ma'));
        foreach (['show', 'access'] as $ham) {
            self::assertSame('404 NOT_FOUND', $this->loi(fn () => $c->$ham($this->req($uid, ['ma' => 'hsk2']))), $ham);
        }
        self::assertSame('404 NOT_FOUND', $this->trang($uid, 'hsk2', 'bia', false));
        self::assertSame('404 NOT_FOUND', $this->trang($admin, 'hsk2', '4'));

        // Ẩn cả giáo trình: học viên không còn quyển nào. Bật lại thì cấp 2 vẫn giữ lựa chọn ẩn riêng của nó.
        Db::exec('UPDATE curricula SET is_visible = 0 WHERE id = 1');
        self::assertSame([], $c->index($this->req($uid)));
        Db::exec('UPDATE curricula SET is_visible = 1 WHERE id = 1');
        self::assertSame(['hsk1'], array_column($c->index($this->req($uid)), 'ma'));
    }

    public function testCayCuaGiaoTrinhKhacKhongAnhHuongSach(): void
    {
        // Ẩn bài 1 cấp 3 của giáo trình HSK không đụng tới hn3 của học viên Hán ngữ (cùng số cấp, cùng số bài).
        $hn = $this->hanNgu();
        $this->baiDuLieu(3, 1, 'draft');
        $this->baiDuLieu(3, 1, 'published', $hn);
        $uid = $this->nguoi('learner', $hn);
        $this->moBai($uid, 'hn3', 1);
        self::assertSame('', $this->trang($uid, 'hn3', '4'));
        self::assertSame([], (new BookController())->access($this->req($uid, ['ma' => 'hn3']))['an']);
    }

    // ------------------------------------------------------------------ file nghe

    private function nghe(string $range): Response
    {
        return (new BookController())->audio($this->req($this->nguoi('admin'), ['ma' => 'hn3', 'so' => '1'], true, $range === '' ? [] : ['range' => $range]));
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
