<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Controllers\Admin\OpsController;
use Zika\Controllers\SupportController;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Router;
use Zika\Core\Settings;
use Zika\Tests\DbTestCase;

final class SupportLinksTest extends DbTestCase
{
    private const URL = 'https://s.shopee.vn/abc';

    protected function setUp(): void
    {
        parent::setUp();
        Clock::freeze(new DateTimeImmutable('2026-11-06 10:00:00'));
        // site_settings được giữ giữa các test (DbTestCase::KEEP).
        Settings::set('fund', ['amount' => null, 'month' => null, 'updated_on' => null]);
        Settings::set('support', ['enabled' => false]);
    }

    private function nhapTienQuy(mixed $amount): array
    {
        $r = new Request('PUT', '/admin/support/fund', [], ['amount' => $amount]);
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        return (new OpsController())->saveFund($r);
    }

    /** @param array<string,mixed> $over */
    private function link(array $over = []): int
    {
        return Db::insert('affiliate_links', $over + ['name' => 'Vở luyện chữ', 'url' => self::URL, 'place_fund' => 1]);
    }

    private function bat(bool $on = true): void
    {
        Settings::set('support', ['enabled' => $on]);
    }

    private function nguoiHoc(string $method, string $path, array $query = [], array $body = []): Request
    {
        $r = new Request($method, $path, $query, $body);
        $r->user = ['id' => $this->makeUser(), 'role' => 'learner'];
        return $r;
    }

    private function go(int $id, array $query = [], string $method = 'GET', bool $dangNhap = true): \Zika\Core\Response
    {
        $r = $dangNhap ? $this->nguoiHoc($method, "/go/$id", $query) : new Request($method, "/go/$id", $query);
        $r->params = ['id' => (string) $id];
        return (new SupportController())->go($r);
    }

    private function luuLink(array $body, int $id = 0): int
    {
        $r = new Request($id > 0 ? 'PUT' : 'POST', '/admin/support/links', [], $body + ['name' => 'Link', 'url' => self::URL]);
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        if ($id > 0) {
            $r->params = ['id' => (string) $id];
        }
        $res = (new OpsController())->saveLink($r);
        return (int) json_decode($res->body, true)['data']['id'];
    }

    public function testRoute(): void
    {
        self::assertSame('P', Router::match('GET', '/support/links')['auth']);
        self::assertSame('L', Router::match('POST', '/support/view')['auth']);
        self::assertSame('P', Router::match('GET', '/go/12')['auth']);
        self::assertSame('A', Router::match('PUT', '/admin/support/fund')['auth']);
    }

    public function testUngHoTrucTiepDaBoHan(): void
    {
        // Người dùng bỏ 09/11: không còn API lưu, không còn trường donate ở phía người học lẫn trang quản trị.
        try {
            Router::match('PUT', '/admin/support/donate');
            self::fail('Route ủng hộ trực tiếp vẫn còn.');
        } catch (HttpError $e) {
            self::assertContains($e->status, [404, 405]);
        }
        $this->bat();
        self::assertArrayNotHasKey('donate', (new SupportController())->links($this->nguoiHoc('GET', '/support/links')));
        $r = new Request('GET', '/admin/support');
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        self::assertArrayNotHasKey('donate', (new OpsController())->support($r));
        self::assertArrayNotHasKey('donate', Settings::DEFAULTS);
    }

    public function testCongTacTatThiKhongCoLinkNao(): void
    {
        $this->link(['place_icon' => 1]);
        $this->bat(false);

        $out = (new SupportController())->links($this->nguoiHoc('GET', '/support/links'));

        self::assertNull($out['icon']);
        self::assertSame([], $out['fund']);
    }

    public function testBatThiTraDungViTri(): void
    {
        $a = $this->link(['name' => 'Chỉ quỹ', 'sort_order' => 2]);
        $b = $this->link(['name' => 'Cả hai', 'place_icon' => 1, 'sort_order' => 1]);
        $this->link(['name' => 'Chưa gắn', 'place_fund' => 0]);
        $this->bat();

        $out = (new SupportController())->links($this->nguoiHoc('GET', '/support/links'));

        self::assertSame(['id' => $b, 'name' => 'Cả hai', 'url' => "/go/$b?place=icon"], $out['icon']);
        self::assertSame([$b, $a], array_column($out['fund'], 'id'));
        self::assertStringNotContainsString('shopee', json_encode($out), 'Không lộ link thật ra giao diện');
    }

    public function testLinkTatKhongHien(): void
    {
        $this->link(['name' => 'Đã tắt', 'is_active' => 0]);
        $bat = $this->link(['name' => 'Đang bật']);
        $this->bat();

        $out = (new SupportController())->links($this->nguoiHoc('GET', '/support/links'));

        self::assertSame([$bat], array_column($out['fund'], 'id'));
    }

    public function testGoChuyenSangShopeeVaDemLuotBam(): void
    {
        $id = $this->link(['place_icon' => 1]);
        $this->bat();

        $res = $this->go($id, ['place' => 'icon']);

        self::assertSame(302, $res->status);
        self::assertSame(self::URL, $res->headers['Location']);
        $e = Db::one('SELECT * FROM affiliate_events');
        self::assertSame('click', $e['kind']);
        self::assertSame('icon', $e['place']);
        self::assertSame('2026-11-06 10:00:00', $e['created_at']);
    }

    public function testGoLinkDaTatVeBanHocVaKhongDem(): void
    {
        $tat = $this->link(['is_active' => 0]);
        $this->bat();
        self::assertSame('/learn/flashcards', $this->go($tat)->headers['Location']);
        self::assertSame('/learn/flashcards', $this->go(999)->headers['Location']);

        $bat = $this->link();
        $this->bat(false);
        self::assertSame('/learn/flashcards', $this->go($bat)->headers['Location']);

        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM affiliate_events'));
    }

    public function testGoKhongDemKhiChuaDangNhapHoacChiHoiHead(): void
    {
        $id = $this->link();
        $this->bat();

        self::assertSame(self::URL, $this->go($id, [], 'GET', false)->headers['Location']);
        self::assertSame(self::URL, $this->go($id, [], 'HEAD')->headers['Location']);

        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM affiliate_events'));
    }

    public function testViTriLaKhongLamHongCsdl(): void
    {
        $id = $this->link();
        $this->bat();

        $this->go($id, ['place' => "x'; DROP"]);

        self::assertSame('fund', Db::val('SELECT place FROM affiliate_events'));
    }

    public function testViewChiGhiLinkOViTriDo(): void
    {
        $icon = $this->link(['place_icon' => 1, 'place_fund' => 0]);
        $this->link();
        $this->bat();

        $res = (new SupportController())->view($this->nguoiHoc('POST', '/support/view', [], ['place' => 'icon']));

        self::assertSame(204, $res->status);
        $e = Db::all('SELECT link_id, kind, place FROM affiliate_events');
        self::assertCount(1, $e);
        self::assertSame($icon, (int) $e[0]['link_id']);
        self::assertSame('view', $e[0]['kind']);
    }

    public function testViewTuChoiViTriLa(): void
    {
        $this->expectException(HttpError::class);
        (new SupportController())->view($this->nguoiHoc('POST', '/support/view', [], ['place' => 'desk']));
    }

    public function testIconDaCoLinkThiTuChoiLinkKhac(): void
    {
        $this->luuLink(['placeIcon' => true]);

        try {
            $this->luuLink(['placeIcon' => true, 'name' => 'Link thứ hai']);
            self::fail('Phải từ chối link thứ hai ở Icon');
        } catch (HttpError) {
            self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM affiliate_links'));
        }
    }

    public function testIconDaCoLinkThiLinkKhacMacDinhOQuyThang(): void
    {
        $this->luuLink(['placeIcon' => true]);

        $moi = $this->luuLink(['placeFund' => false]);
        self::assertSame(1, (int) Db::val('SELECT place_fund FROM affiliate_links WHERE id = ?', [$moi]));

        $this->luuLink(['placeFund' => false, 'name' => 'Đổi tên'], $moi);
        self::assertSame(1, (int) Db::val('SELECT place_fund FROM affiliate_links WHERE id = ?', [$moi]));
    }

    public function testLinkGiuIconSuaDuocVaNhuongChoLinkKhac(): void
    {
        $giu = $this->luuLink(['placeIcon' => true]);

        // Tự sửa chính nó không bị coi là trùng.
        $this->luuLink(['placeIcon' => true, 'name' => 'Đổi tên'], $giu);
        self::assertSame(1, (int) Db::val('SELECT place_icon FROM affiliate_links WHERE id = ?', [$giu]));
        self::assertSame(0, (int) Db::val('SELECT place_fund FROM affiliate_links WHERE id = ?', [$giu]));

        $this->luuLink(['placeIcon' => false, 'placeFund' => true], $giu);
        $moi = $this->luuLink(['placeIcon' => true]);

        self::assertSame(1, (int) Db::val('SELECT place_icon FROM affiliate_links WHERE id = ?', [$moi]));
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM affiliate_links WHERE place_icon = 1'));
    }

    public function testNhapTienQuyGhiTheoThangHienTai(): void
    {
        $out = $this->nhapTienQuy(1250000);

        self::assertSame(['amount' => 1250000, 'month' => '11/2026', 'updatedOn' => '06/11/2026'], $out);

        // Sang tháng mới mà chưa nhập lại thì vẫn ghi đúng tháng của số tiền đó.
        Clock::freeze(new DateTimeImmutable('2026-12-02 08:00:00'));
        Settings::clearCache();
        $hoc = (new SupportController())->links($this->nguoiHoc('GET', '/support/links'));
        self::assertSame(['amount' => 1250000, 'month' => '11/2026', 'updatedOn' => '06/11/2026'], $hoc['monthlyFund']);

        self::assertSame('12/2026', $this->nhapTienQuy(2000000)['month']);
    }

    public function testChuaNhapHoacXoaTienQuyThiKhongCoSo(): void
    {
        $chua = (new SupportController())->links($this->nguoiHoc('GET', '/support/links'))['monthlyFund'];
        self::assertSame(['amount' => null, 'month' => '11/2026', 'updatedOn' => null], $chua);

        $this->nhapTienQuy(500000);
        self::assertNull($this->nhapTienQuy('')['amount']);
    }

    public function testTienQuyHienCaKhiCongTacLinkTat(): void
    {
        $this->nhapTienQuy(500000);
        $this->bat(false);

        $out = (new SupportController())->links($this->nguoiHoc('GET', '/support/links'));

        self::assertSame(500000, $out['monthlyFund']['amount']);
    }

    public function testTienQuyTuChoiSoAm(): void
    {
        $this->expectException(HttpError::class);
        $this->nhapTienQuy(-1);
    }

    public function testTienQuyTuChoiChu(): void
    {
        $this->expectException(HttpError::class);
        $this->nhapTienQuy('nhiều lắm');
    }

    public function testQuyThangGiuNhieuLink(): void
    {
        $this->luuLink(['placeFund' => true]);
        $this->luuLink(['placeFund' => true]);

        self::assertSame(2, (int) Db::val('SELECT COUNT(*) FROM affiliate_links WHERE place_fund = 1'));
    }

    public function testLinkMoiLuonBatVaSuaKhongDoiTrangThai(): void
    {
        $id = $this->luuLink(['placeFund' => true]);
        self::assertSame(1, (int) Db::val('SELECT is_active FROM affiliate_links WHERE id = ?', [$id]));

        Db::update('affiliate_links', ['is_active' => 0], ['id' => $id]);
        $this->luuLink(['placeFund' => true, 'name' => 'Đổi tên'], $id);

        self::assertSame(0, (int) Db::val('SELECT is_active FROM affiliate_links WHERE id = ?', [$id]));
    }

    public function testThongKeAdminCoSoHomNayVaBoNgayQuaXa(): void
    {
        $id = $this->link();
        Db::insert('affiliate_daily', ['link_id' => $id, 'd' => '2026-11-05', 'views' => 10, 'clicks' => 2]);
        Db::insert('affiliate_daily', ['link_id' => $id, 'd' => '2026-10-20', 'views' => 100, 'clicks' => 100]);
        Db::insert('affiliate_events', ['link_id' => $id, 'kind' => 'click', 'place' => 'fund', 'created_at' => '2026-11-06 09:00:00']);
        Db::insert('affiliate_events', ['link_id' => $id, 'kind' => 'view', 'place' => 'fund', 'created_at' => '2026-11-05 09:00:00']);

        $out = (new OpsController())->support(new Request('GET', '/admin/support'));

        self::assertSame(10, $out['kpi']['views']);
        self::assertSame(3, $out['kpi']['clicks']);
        self::assertSame(3, $out['links'][0]['clicks']);
        self::assertSame(30.0, $out['links'][0]['ctr']);
    }

    public function testBamLinkThiBeTuiNghiDenNgayHomSau(): void
    {
        $this->bat();
        $icon = $this->link(['place_icon' => 1, 'place_fund' => 0]);
        $quy = $this->link();
        $a = $this->makeUser();
        $b = $this->makeUser();
        $hoi = function (int $uid, string $method = 'GET', string $path = '/support/links', array $query = [], ?int $id = null) {
            $r = new Request($method, $path, $query);
            $r->user = ['id' => $uid, 'role' => 'learner'];
            if ($id !== null) {
                $r->params = ['id' => (string) $id];
                return (new SupportController())->go($r);
            }
            return (new SupportController())->links($r);
        };

        self::assertFalse($hoi($a)['clickedToday']);
        // Lượt hiển thị không tính là đã bấm.
        $v = new Request('POST', '/support/view', [], ['place' => 'icon']);
        $v->user = ['id' => $a, 'role' => 'learner'];
        (new SupportController())->view($v);
        self::assertFalse($hoi($a)['clickedToday']);

        $hoi($a, 'GET', "/go/$icon", ['place' => 'icon'], $icon);
        self::assertTrue($hoi($a)['clickedToday']);
        self::assertFalse($hoi($b)['clickedToday'], 'Người khác chưa bấm thì bé vẫn hiện');

        // Bấm link Quỹ tháng cũng tính: máy đã mang mã tiếp thị.
        $hoi($b, 'GET', "/go/$quy", ['place' => 'fund'], $quy);
        self::assertTrue($hoi($b)['clickedToday']);

        // 23:59 vẫn là hôm nay; 0h hôm sau bé hiện lại.
        Clock::freeze(new DateTimeImmutable('2026-11-06 23:59:59'));
        self::assertTrue($hoi($a)['clickedToday']);
        Clock::freeze(new DateTimeImmutable('2026-11-07 00:00:00'));
        self::assertFalse($hoi($a)['clickedToday']);

        // Chưa đăng nhập thì không có gì để nhớ.
        self::assertFalse((new SupportController())->links(new Request('GET', '/support/links'))['clickedToday']);
    }
}
