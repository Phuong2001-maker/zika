<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Controllers\Admin\ContestController as AdminContest;
use Zika\Controllers\Admin\QuestionController;
use Zika\Controllers\ContestController;
use Zika\Controllers\TestController;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Router;
use Zika\Core\Settings;
use Zika\Services\ContestService;
use Zika\Services\TestService;
use Zika\Tests\DbTestCase;

final class ContestTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Clock::freeze(new DateTimeImmutable('2026-11-20 10:00:00'));
        // site_settings được giữ giữa các test (DbTestCase::KEEP).
        Settings::set('fund', ['amount' => null, 'month' => null, 'updated_on' => null]);
    }

    private function de(int $soCau = 2, string $status = 'draft'): int
    {
        $id = Db::insert('mock_tests', ['level_id' => 1, 'title' => 'Đề thi tháng', 'duration_sec' => 1800, 'status' => $status]);
        for ($i = 1; $i <= $soCau; $i++) {
            Db::insert('questions', [
                'part' => 'mock',
                'mock_test_id' => $id,
                'type' => 'pick',
                'payload' => json_encode(['prompt' => "Câu $i", 'options' => ['a', 'b'], 'answer' => 0]),
                'sort_order' => $i,
            ]);
        }
        return $id;
    }

    private function cuocThi(int $mock, string $from, string $to): int
    {
        return Db::insert('contests', ['title' => 'Thi tháng 11', 'mock_test_id' => $mock, 'starts_at' => $from, 'ends_at' => $to]);
    }

    private function luu(array $body, int $id = 0): int
    {
        $r = new Request($id > 0 ? 'PUT' : 'POST', '/admin/contests', [], $body);
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        if ($id > 0) {
            $r->params = ['id' => (string) $id];
        }
        return (int) json_decode((new AdminContest())->save($r)->body, true)['data']['id'];
    }

    private function hienTai(?int $userId = null): ?array
    {
        $r = new Request('GET', '/contest/current');
        $r->user = ['id' => $userId ?? $this->makeUser(), 'role' => 'learner'];
        return (new ContestController())->current($r)['contest'];
    }

    /** Đăng ký sẵn mọi cuộc thi dùng đề này rồi vào thi: đa số test không xoay quanh việc đăng ký. */
    private function vaoThi(int $uid, int $mock): array
    {
        foreach (Db::col('SELECT id FROM contests WHERE mock_test_id = ?', [$mock]) as $c) {
            Db::exec('INSERT IGNORE INTO contest_registrations (contest_id, user_id, created_at) VALUES (?, ?, ?)', [(int) $c, $uid, Clock::sql()]);
        }
        return TestService::start($uid, null, $mock);
    }

    private function xepHang(?int $userId = null): array
    {
        $r = new Request('GET', '/contest/ranking');
        $r->user = ['id' => $userId ?? $this->makeUser(), 'role' => 'learner'];
        return (new ContestController())->ranking($r);
    }

    /** Lượt đã nộp, ghi thẳng để thử thứ tự xếp hạng mà không phải làm bài. */
    private function luotDaNop(int $contest, int $mock, int $user, int $dung, int $giay, string $nopLuc): void
    {
        Db::insert('test_attempts', [
            'user_id' => $user,
            'mock_test_id' => $mock,
            'contest_id' => $contest,
            'question_ids' => '[1,2]',
            'option_maps' => '{}',
            'answers' => '{}',
            'flags' => '[]',
            'listen_counts' => '[]',
            'started_at' => '2026-11-20 09:00:00',
            'deadline_at' => '2026-11-20 09:30:00',
            'submitted_at' => $nopLuc,
            'correct_count' => $dung,
            'used_sec' => $giay,
        ]);
    }

    /** Đáp án đúng theo vị trí hiển thị của lượt này, vì đáp án bị xáo khác nhau giữa các lượt. */
    private function dapAnDung(int $attemptId): array
    {
        $a = Db::one('SELECT * FROM test_attempts WHERE id = ?', [$attemptId]);
        $maps = json_decode((string) $a['option_maps'], true);
        $out = [];
        foreach (Db::all('SELECT id, payload FROM questions WHERE mock_test_id = ?', [(int) $a['mock_test_id']]) as $q) {
            $dung = (int) json_decode((string) $q['payload'], true)['answer'];
            $out[(int) $q['id']] = array_search($dung, $maps[(int) $q['id']], true);
        }
        return $out;
    }

    public function testRoute(): void
    {
        self::assertSame('L', Router::match('GET', '/contest/current')['auth']);
        self::assertSame('A', Router::match('GET', '/admin/contests')['auth']);
        self::assertSame('A', Router::match('POST', '/admin/contests')['auth']);
        self::assertSame('A', Router::match('DELETE', '/admin/contests/3')['auth']);
    }

    public function testChuaCoCuocThi(): void
    {
        self::assertNull($this->hienTai());
    }

    public function testSapDienRaCoSoGiayVaGiauMaDe(): void
    {
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 11:30:15', '2026-11-20 13:00:00');

        $c = $this->hienTai();

        self::assertSame('upcoming', $c['status']);
        self::assertSame(5415, $c['startsInSec']);
        self::assertSame('11:30, 20/11/2026', $c['startsLabel']);
        self::assertSame(60, $c['durationMin']);
        self::assertSame(2, $c['questionCount']);
        self::assertNull($c['mockTestId'], 'Chưa tới giờ thì không lộ mã đề');
    }

    public function testDangThiCoMaDe(): void
    {
        $mock = $this->de();
        $id = $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 11:00:00');
        $uid = $this->makeUser();
        Db::exec('INSERT INTO contest_registrations (contest_id, user_id, created_at) VALUES (?, ?, ?)', [$id, $uid, Clock::sql()]);

        $c = $this->hienTai($uid);

        self::assertSame('open', $c['status']);
        self::assertSame(0, $c['startsInSec']);
        self::assertSame(3600, $c['endsInSec']);
        self::assertSame($mock, $c['mockTestId']);
    }

    public function testDaKetThucThiChuyenSangCuocTiepTheo(): void
    {
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-01 09:00:00', '2026-11-01 11:00:00');
        self::assertNull($this->hienTai());

        $sau = $this->cuocThi($mock, '2026-12-01 09:00:00', '2026-12-01 11:00:00');
        $truoc = $this->cuocThi($mock, '2026-11-25 09:00:00', '2026-11-25 11:00:00');

        self::assertSame($truoc, $this->hienTai()['id']);
        self::assertNotSame($sau, $truoc);
    }

    public function testDeNhapChiLamDuocTrongGioThi(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 11:00:00', '2026-11-20 12:00:00');

        try {
            $this->vaoThi($uid, $mock);
            self::fail('Chưa tới giờ thì không vào được đề nháp');
        } catch (HttpError $e) {
            self::assertSame(404, $e->status);
        }

        Clock::freeze(new DateTimeImmutable('2026-11-20 11:00:00'));
        self::assertTrue(ContestService::isOpen($mock));
        $attempt = $this->vaoThi($uid, $mock);
        self::assertNotEmpty($attempt);

        Clock::freeze(new DateTimeImmutable('2026-11-20 12:00:00'));
        self::assertFalse(ContestService::isOpen($mock), 'Đúng giờ kết thúc là đóng');
        $this->expectException(HttpError::class);
        $this->vaoThi($this->makeUser(), $mock);
    }

    public function testVaoThiRoiHetGioVanChamDuoc(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:30:00');
        $this->vaoThi($uid, $mock);
        $attemptId = (int) Db::val('SELECT id FROM test_attempts WHERE user_id = ?', [$uid]);

        Clock::freeze(new DateTimeImmutable('2026-11-20 12:00:00'));
        $out = TestService::grade($attemptId);

        self::assertNotEmpty($out);
        self::assertNotNull(Db::val('SELECT submitted_at FROM test_attempts WHERE id = ?', [$attemptId]));
    }

    public function testVaoMuonThiHanNopLaGioDongCuocThi(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:10:00');

        $p = $this->vaoThi($uid, $mock);

        self::assertSame('2026-11-20 10:10:00.000', Db::val('SELECT deadline_at FROM test_attempts WHERE user_id = ?', [$uid]));
        self::assertStringStartsWith('2026-11-20T10:10:00', $p['deadlineAt']);
        self::assertTrue($p['autoSubmit'], 'Hết giờ màn làm bài tự nộp');
        self::assertSame('Thi tháng 11', $p['contestTitle']);
    }

    public function testMoiNguoiCungDungLucHetGioThiKhongTheoThoiLuongCuaDe(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();   // thời lượng riêng của đề là 30 phút
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');

        $p = $this->vaoThi($uid, $mock);

        self::assertSame('2026-11-20 18:00:00.000', Db::val('SELECT deadline_at FROM test_attempts WHERE user_id = ?', [$uid]));
        self::assertSame(3600, $p['durationSec'], 'Đồng hồ theo thời gian thi của cuộc thi');
    }

    public function testDaDangKyVaoMuonVanThayNutVaVaoDuocTruocGioDong(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $contest = $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:10:00');
        Db::exec('INSERT INTO contest_registrations (contest_id, user_id, created_at) VALUES (?, ?, ?)', [$contest, $uid, '2026-11-20 08:30:00']);

        Clock::freeze(new DateTimeImmutable('2026-11-20 10:09:30'));
        $current = $this->hienTai($uid);
        self::assertSame('open', $current['status']);
        self::assertSame(30, $current['endsInSec']);
        self::assertSame($mock, $current['mockTestId'], 'Còn trong giờ thì giao diện phải có đường vào thi');

        $attempt = TestService::start($uid, null, $mock);
        self::assertSame('2026-11-20 10:10:00.000', Db::val('SELECT deadline_at FROM test_attempts WHERE id = ?', [$attempt['attemptId']]));
    }

    public function testChuaDangKyThiKhongDuocVao(): void
    {
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');

        try {
            TestService::start($this->makeUser(), null, $mock);
            self::fail('Chưa đăng ký phải bị chặn');
        } catch (HttpError $e) {
            self::assertSame(403, $e->status);
        }
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM test_attempts'));
    }

    public function testChiDangKyHoacHuyDuocTruocGioBatDau(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $c = $this->cuocThi($mock, '2026-11-20 11:00:00', '2026-11-20 12:00:00');

        ContestService::setRegistered($c, $uid, true);
        ContestService::setRegistered($c, $uid, true);   // bấm hai lần không sinh hai dòng
        self::assertTrue($this->hienTai($uid)['registered']);
        self::assertSame(1, $this->hienTai($uid)['registrantCount']);
        self::assertNull($this->hienTai($uid)['mockTestId']);

        ContestService::setRegistered($c, $uid, false);
        self::assertFalse($this->hienTai($uid)['registered']);
        ContestService::setRegistered($c, $uid, true);

        Clock::freeze(new DateTimeImmutable('2026-11-20 11:00:00'));
        foreach ([true, false] as $on) {
            try {
                ContestService::setRegistered($c, $this->makeUser(), $on);
                self::fail('Đã bắt đầu thì không đổi được');
            } catch (HttpError $e) {
                self::assertSame(409, $e->status);
            }
        }
        self::assertSame($mock, $this->hienTai($uid)['mockTestId'], 'Đã đăng ký thì tới giờ có mã đề');
        self::assertNull($this->hienTai()['mockTestId'], 'Chưa đăng ký thì không có');
    }

    public function testDanhSachDangKyTheoThuTuDangKy(): void
    {
        $mock = $this->de();
        $c = $this->cuocThi($mock, '2026-11-20 11:00:00', '2026-11-20 12:00:00');
        $a = $this->makeUser(['display_name' => 'An']);
        $b = $this->makeUser(['display_name' => 'Bình']);
        ContestService::setRegistered($c, $a, true);
        Clock::freeze(new DateTimeImmutable('2026-11-20 10:05:00'));
        ContestService::setRegistered($c, $b, true);

        $ds = ContestService::registrants($b);

        self::assertSame(2, $ds['count']);
        self::assertSame(['An', 'Bình'], array_column($ds['rows'], 'name'));
        self::assertSame([false, true], array_column($ds['rows'], 'isMe'));
        self::assertSame('10:05, 20/11', $ds['rows'][1]['at']);
    }

    public function testGiaiThuongTinhTheoQuyLamTronNghin(): void
    {
        Settings::set('prizes', ['rows' => [['label' => 'Nhất', 'percent' => 50], ['label' => 'Nhì', 'percent' => 33.3]]]);
        Settings::set('fund', ['amount' => 1250500]);

        self::assertSame(
            [['label' => 'Nhất', 'percent' => 50.0, 'amount' => 625000], ['label' => 'Nhì', 'percent' => 33.3, 'amount' => 416000]],
            ContestService::prizes(),
        );

        Settings::set('fund', ['amount' => null]);
        self::assertNull(ContestService::prizes()[0]['amount']);
    }

    public function testAdminLuuCoCauGiai(): void
    {
        $luu = function (array $rows): array {
            $r = new Request('PUT', '/admin/contests/prizes', [], ['rows' => $rows]);
            $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
            return (new AdminContest())->savePrizes($r);
        };

        self::assertSame('Giải đặc biệt', $luu([['label' => 'Giải đặc biệt', 'percent' => 100]])[0]['label']);

        foreach ([[['label' => 'A', 'percent' => 70], ['label' => 'B', 'percent' => 40]], [['label' => '', 'percent' => 10]], [['label' => 'A', 'percent' => 0]]] as $sai) {
            try {
                $luu($sai);
                self::fail('Phải từ chối');
            } catch (HttpError $e) {
                self::assertSame(422, $e->status);
            }
        }
    }

    public function testNapDeTuExcelBaoDongSai(): void
    {
        $mock = $this->de(0);
        $r = new Request('POST', "/admin/mock-tests/$mock/questions/bulk", [], ['mode' => 'append', 'items' => [
            ['type' => 'listen', 'score' => 5, 'payload' => ['audioText' => '你好', 'options' => ['Xin chào', 'Tạm biệt'], 'answer' => 0]],
            ['type' => 'pick', 'payload' => ['ask' => 'Câu nào đúng?', 'options' => ['甲', '乙'], 'answer' => 1]],
            ['type' => 'pick', 'payload' => ['ask' => 'Thiếu đáp án', 'options' => ['a']]],
            ['type' => 'lạ'],
        ]]);
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        $r->params = ['id' => (string) $mock];

        $out = (new QuestionController())->bulkMock($r);

        self::assertSame(2, $out['created']);
        self::assertSame([3, 4], array_column($out['skipped'], 'row'));
        $rows = Db::all('SELECT section, score FROM questions WHERE mock_test_id = ? ORDER BY sort_order', [$mock]);
        self::assertSame(['listening', 'reading'], array_column($rows, 'section'));
        self::assertSame('5.0', (string) $rows[0]['score']);
    }

    public function testMoiNguoiMotLuot(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');

        $p = $this->vaoThi($uid, $mock);
        self::assertSame($p['attemptId'], $this->vaoThi($uid, $mock)['attemptId'], 'Đang làm thì vào lại đúng lượt đó');

        TestService::grade($p['attemptId']);
        self::assertSame(['resultAttemptId' => $p['attemptId']], $this->vaoThi($uid, $mock));
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM test_attempts WHERE user_id = ?', [$uid]));
    }

    public function testHetGioTuChamTheoCauDaLuuDuChuaBamNop(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:10:00');
        $p = $this->vaoThi($uid, $mock);
        $a = Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]);
        $dung = $this->dapAnDung($p['attemptId']);
        $motCau = array_slice($dung, 0, 1, true);
        TestService::patch($a, ['answers' => $motCau]);

        // Người học đóng tab, chưa bấm nộp; quá giờ đóng cả 5 phút mới có người mở bảng xếp hạng.
        Clock::freeze(new DateTimeImmutable('2026-11-20 10:15:00'));
        $bang = $this->xepHang();

        $sau = Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]);
        self::assertNotNull($sau['submitted_at']);
        self::assertSame(1, (int) $sau['auto_submitted']);
        self::assertSame(1, (int) $sau['correct_count']);
        self::assertSame(600, (int) $sau['used_sec'], 'Thời gian tính tới hạn nộp, không tính tới lúc chấm');
        self::assertSame(1, $bang['participants']);
        self::assertSame(2, $bang['rows'][0]['total']);
    }

    public function testQuaHanThiKhongLuuThemCauTraLoi(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:10:00');
        $p = $this->vaoThi($uid, $mock);
        $a = Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]);

        Clock::freeze(new DateTimeImmutable('2026-11-20 10:10:11'));
        try {
            TestService::patch($a, ['answers' => $this->dapAnDung($p['attemptId'])]);
            self::fail('Quá hạn phải từ chối');
        } catch (HttpError $e) {
            self::assertSame(409, $e->status);
        }
        self::assertSame(0, (int) Db::val('SELECT correct_count FROM test_attempts WHERE id = ?', [$p['attemptId']]));
    }

    public function testBamNopVanTinhDungSoCauVaThoiGian(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');
        $p = $this->vaoThi($uid, $mock);
        TestService::patch(Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]), ['answers' => $this->dapAnDung($p['attemptId'])]);

        Clock::freeze(new DateTimeImmutable('2026-11-20 10:05:30'));
        TestService::grade($p['attemptId']);

        $sau = Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]);
        self::assertSame(2, (int) $sau['correct_count']);
        self::assertSame(330, (int) $sau['used_sec']);
    }

    public function testXepTheoCauDungRoiThoiGianRoiNopSom(): void
    {
        $mock = $this->de();
        $c = $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');
        $it = $this->makeUser(['display_name' => 'Đúng ít nhanh']);
        $cham = $this->makeUser(['display_name' => 'Đúng nhiều chậm']);
        $nhanh = $this->makeUser(['display_name' => 'Đúng nhiều nhanh']);
        $sau = $this->makeUser(['display_name' => 'Bằng hết nộp sau']);
        $this->luotDaNop($c, $mock, $it, 1, 10, '2026-11-20 09:00:10');
        $this->luotDaNop($c, $mock, $cham, 2, 900, '2026-11-20 09:15:00');
        $this->luotDaNop($c, $mock, $sau, 2, 300, '2026-11-20 09:30:00');
        $this->luotDaNop($c, $mock, $nhanh, 2, 300, '2026-11-20 09:05:00');

        $bang = $this->xepHang($cham);

        self::assertSame(
            ['Đúng nhiều nhanh', 'Bằng hết nộp sau', 'Đúng nhiều chậm', 'Đúng ít nhanh'],
            array_column($bang['rows'], 'name'),
        );
        self::assertSame([1, 2, 3, 4], array_column($bang['rows'], 'rank'));
        self::assertSame(4, $bang['participants']);
        self::assertSame(3, $bang['me']['rank']);
        self::assertTrue($bang['rows'][2]['isMe']);
        self::assertFalse($bang['rows'][0]['isMe']);
    }

    public function testNgoaiTopVanBietHangCuaMinh(): void
    {
        $mock = $this->de();
        $c = $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->luotDaNop($c, $mock, $this->makeUser(), 2, 100 + $i, '2026-11-20 09:05:00');
        }
        $toi = $this->makeUser();
        $this->luotDaNop($c, $mock, $toi, 0, 500, '2026-11-20 09:10:00');

        $bang = \Zika\Services\ContestService::ranking($toi, 2);

        self::assertCount(2, $bang['rows']);
        self::assertSame(4, $bang['me']['rank']);
    }

    public function testChuaBatDauThiChuaCoBangXepHang(): void
    {
        $this->cuocThi($this->de(), '2026-11-20 11:00:00', '2026-11-20 12:00:00');

        self::assertSame(['contest' => null, 'rows' => [], 'me' => null, 'participants' => 0], $this->xepHang());
    }

    public function testKetThucRoiVanXemDuocBangVuaThi(): void
    {
        $mock = $this->de();
        $c = $this->cuocThi($mock, '2026-11-20 08:00:00', '2026-11-20 09:00:00');
        $this->cuocThi($mock, '2026-12-01 08:00:00', '2026-12-01 09:00:00');

        $bang = $this->xepHang();

        self::assertSame($c, $bang['contest']['id']);
        self::assertSame('ended', $bang['contest']['status']);
    }

    public function testHaiBaiTrungGioThiHopQuyVaBangXepHangCungMotBai(): void
    {
        $mock = $this->de();
        $truoc = $this->cuocThi($mock, '2026-11-20 08:00:00', '2026-11-20 18:00:00');
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');

        self::assertSame($truoc, $this->hienTai()['id']);
        self::assertSame($truoc, $this->xepHang()['contest']['id']);
    }

    public function testDangKyNhungKhongThiKetThucDuocTinhKhongDiem(): void
    {
        $mock = $this->de(2);
        $contest = $this->cuocThi($mock, '2026-11-20 08:00:00', '2026-11-20 09:00:00');
        $coThi = $this->makeUser(['display_name' => 'Có thi']);
        $vang = $this->makeUser(['display_name' => 'Vắng thi']);
        Db::exec('INSERT INTO contest_registrations (contest_id, user_id, created_at) VALUES (?, ?, ?), (?, ?, ?)', [
            $contest, $coThi, '2026-11-20 07:30:00', $contest, $vang, '2026-11-20 07:31:00',
        ]);
        $this->luotDaNop($contest, $mock, $coThi, 0, 500, '2026-11-20 08:10:00');

        $bang = ContestService::ranking($vang);

        self::assertSame(2, $bang['participants']);
        self::assertSame(['Có thi', 'Vắng thi'], array_column($bang['rows'], 'name'));
        self::assertTrue($bang['rows'][0]['participated']);
        self::assertFalse($bang['rows'][1]['participated']);
        self::assertSame(0, $bang['rows'][1]['correct']);
        self::assertSame(2, $bang['rows'][1]['total']);
        self::assertSame(2, $bang['me']['rank']);
    }

    public function testConMoThiGiauDapAnHetGioMoiHien(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:30:00');
        $p = $this->vaoThi($uid, $mock);

        $trong = TestService::grade($p['attemptId']);
        self::assertSame([], $trong['results'], 'Không lộ đáp án cho người chưa thi');
        self::assertTrue($trong['answersHidden']);

        Clock::freeze(new DateTimeImmutable('2026-11-20 10:30:00'));
        $sau = TestService::result(Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]));
        self::assertCount(2, $sau['results']);
        self::assertFalse($sau['answersHidden']);
    }

    public function testBietDaVaoThiHayChua(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');
        self::assertSame('none', $this->hienTai($uid)['myAttempt']);

        $p = $this->vaoThi($uid, $mock);
        self::assertSame('doing', $this->hienTai($uid)['myAttempt']);

        TestService::grade($p['attemptId']);
        self::assertSame('submitted', $this->hienTai($uid)['myAttempt']);
    }

    public function testThiThuThuongKhongBiGioiHanLuot(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de(2, 'published');

        TestService::grade($this->vaoThi($uid, $mock)['attemptId']);
        $this->vaoThi($uid, $mock);

        self::assertSame(2, (int) Db::val('SELECT COUNT(*) FROM test_attempts WHERE user_id = ?', [$uid]));
    }

    public function testDeCongKhaiVanLamDuocNgoaiGioThi(): void
    {
        $mock = $this->de(2, 'published');
        self::assertNotEmpty($this->vaoThi($this->makeUser(), $mock));
    }

    public function testTaoVaSua(): void
    {
        $mock = $this->de();
        $id = $this->luu(['title' => 'Thi tháng 11', 'mockTestId' => $mock, 'startsAt' => '2026-11-30T20:00', 'durationMin' => 90]);

        self::assertSame('2026-11-30 20:00:00', Db::val('SELECT starts_at FROM contests WHERE id = ?', [$id]));
        self::assertSame('2026-11-30 21:30:00', Db::val('SELECT ends_at FROM contests WHERE id = ?', [$id]), 'Giờ kết thúc do server tính');

        $this->luu(['title' => 'Đổi tên', 'mockTestId' => $mock, 'startsAt' => '2026-11-30T21:00', 'durationMin' => 45], $id);
        self::assertSame('2026-11-30 21:45:00', Db::val('SELECT ends_at FROM contests WHERE id = ?', [$id]));

        $list = (new AdminContest())->index(new Request('GET', '/admin/contests'));
        self::assertSame('Đổi tên', $list['contests'][0]['title']);
        self::assertSame('upcoming', $list['contests'][0]['status']);
        self::assertSame(2, $list['exams'][0]['questionCount']);
    }

    public function testThoiGianThiPhaiHopLe(): void
    {
        $this->expectException(HttpError::class);
        $this->luu(['title' => 'Thi', 'mockTestId' => $this->de(), 'startsAt' => '2026-11-30T20:00', 'durationMin' => 0]);
    }

    public function testNhanDeChuaCoCauVaBaoSoCauChoAdmin(): void
    {
        $this->luu(['title' => 'Thi', 'mockTestId' => $this->de(0), 'startsAt' => '2026-11-30T20:00', 'durationMin' => 60]);

        $ds = (new AdminContest())->index(new Request('GET', '/admin/contests'));
        self::assertSame(0, $ds['contests'][0]['questionCount']);
    }

    public function testDeTaoTuTabDeThiChiCanTenVaKhongLotVaoThiThu(): void
    {
        $r = new Request('POST', '/admin/exams', [], ['title' => 'Đề tháng 12']);
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        (new AdminContest())->createExam($r);

        $ds = (new AdminContest())->exams(new Request('GET', '/admin/exams'));
        self::assertSame('Đề tháng 12', $ds[0]['title']);
        self::assertSame(0, $ds[0]['questionCount']);

        // Mục Thi thử cuối cấp và người học đều không thấy đề này.
        $mockReq = new Request('GET', '/admin/levels/1/mock-tests');
        $mockReq->params = ['id' => '1'];
        self::assertSame([], (new QuestionController())->mockTests($mockReq));
        self::assertSame('draft', Db::val('SELECT status FROM mock_tests WHERE id = ?', [$ds[0]['id']]));
    }

    public function testDeThiThuDangGanBaiThiVanHienOTabDeThi(): void
    {
        $mock = $this->de();
        self::assertSame([], (new AdminContest())->exams(new Request('GET', '/admin/exams')));
        $this->cuocThi($mock, '2026-11-30 20:00:00', '2026-11-30 21:00:00');
        self::assertSame($mock, (new AdminContest())->exams(new Request('GET', '/admin/exams'))[0]['id']);
    }

    public function testKhongXoaDeDangGanCuocThi(): void
    {
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-30 20:00:00', '2026-11-30 21:00:00');
        $r = new Request('DELETE', "/admin/mock-tests/$mock");
        $r->params = ['id' => (string) $mock];
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];

        try {
            (new QuestionController())->deleteMock($r);
            self::fail('Phải chặn xoá');
        } catch (HttpError $e) {
            self::assertSame(409, $e->status);
        }
        self::assertNotNull(Db::val('SELECT id FROM mock_tests WHERE id = ?', [$mock]));
    }

    /* ---------------- Sửa lỗi sau rà soát ---------------- */

    private function batDau(int $uid, array $body): int
    {
        $r = new Request('POST', '/tests/start', [], $body);
        $r->user = ['id' => $uid, 'role' => 'learner'];
        try {
            (new TestController())->start($r);
            return 201;
        } catch (HttpError $e) {
            return $e->status;
        }
    }

    public function testGuiCaBaiVaDeThiThiBiTuChoi(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');
        $lesson = Db::insert('lessons', ['level_id' => 1, 'lesson_no' => 1, 'title' => 'B1', 'status' => 'published',
            'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00']);

        self::assertSame(422, $this->batDau($uid, ['lessonId' => $lesson, 'mockTestId' => $mock]));
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM test_attempts'));
    }

    public function testThiDaiHonMuoiTamGioVanChamVaXepHangDuoc(): void
    {
        $uid = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-22 09:00:00');
        $p = $this->vaoThi($uid, $mock);

        Clock::freeze(new DateTimeImmutable('2026-11-21 20:00:00'));   // 34 giờ sau
        $out = TestService::grade($p['attemptId']);

        self::assertSame(34 * 3600, $out['usedSec']);
        self::assertSame(34 * 3600, (int) Db::val('SELECT used_sec FROM test_attempts WHERE id = ?', [$p['attemptId']]));
        self::assertSame(1, $this->xepHang($uid)['participants']);
    }

    public function testBaiBiAnGiuaChungVanChamDuocVaKhongGhiTienDo(): void
    {
        $uid = $this->makeUser();
        $lesson = Db::insert('lessons', ['level_id' => 1, 'lesson_no' => 1, 'title' => 'B1', 'status' => 'published', 'cnt_kt' => 1,
            'created_at' => '2026-11-01 08:00:00', 'updated_at' => '2026-11-01 08:00:00']);
        Db::insert('questions', ['part' => 'kt', 'lesson_id' => $lesson, 'type' => 'pick',
            'payload' => json_encode(['prompt' => 'Q', 'options' => ['a', 'b'], 'answer' => 0]), 'sort_order' => 1]);
        Db::exec('UPDATE levels SET is_visible = 1 WHERE id = 1');
        $p = TestService::start($uid, $lesson, null);

        Db::exec("UPDATE lessons SET status = 'draft' WHERE id = ?", [$lesson]);
        $out = TestService::grade($p['attemptId']);

        self::assertNotNull(Db::val('SELECT submitted_at FROM test_attempts WHERE id = ?', [$p['attemptId']]));
        self::assertSame(1, $out['questionCount']);
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM lesson_progress WHERE user_id = ?', [$uid]));
        // Xem lại kết quả vẫn được.
        self::assertSame($p['attemptId'], TestService::result(Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]))['attemptId']);
    }

    public function testMotLuotHongKhongChanCronVaBangXepHang(): void
    {
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:10:00');
        $hong = $this->makeUser();
        $tot = $this->makeUser();
        $this->vaoThi($hong, $mock);
        $this->vaoThi($tot, $mock);
        // Lượt “hỏng”: đề mà lượt trỏ tới đã biến mất nên chấm sẽ ném 404.
        $hongId = (int) Db::val('SELECT id FROM test_attempts WHERE user_id = ?', [$hong]);
        Db::exec('SET FOREIGN_KEY_CHECKS = 0');
        Db::exec('UPDATE test_attempts SET mock_test_id = 999999 WHERE id = ?', [$hongId]);
        Db::exec('SET FOREIGN_KEY_CHECKS = 1');

        Clock::freeze(new DateTimeImmutable('2026-11-20 10:20:00'));
        $chamDuoc = TestService::gradeOverdue();
        self::assertNotNull(Db::val('SELECT submitted_at FROM test_attempts WHERE user_id = ?', [$tot]), 'Lượt tốt vẫn được chấm');
        self::assertSame(1, $chamDuoc);
        self::assertNull(Db::val('SELECT submitted_at FROM test_attempts WHERE id = ?', [$hongId]));

        // Bảng xếp hạng không 500 dù còn lượt hỏng quá hạn.
        self::assertSame(1, $this->xepHang($tot)['participants']);
    }

    public function testThiXongKhongTinhTruot(): void
    {
        $uid = $this->makeUser();
        $r = new Request('POST', '/admin/exams', [], ['title' => 'Đề']);
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        $mock = (int) json_decode((new AdminContest())->createExam($r)->body, true)['data']['id'];
        self::assertSame(0, (int) Db::val('SELECT pass_points FROM mock_tests WHERE id = ?', [$mock]));
        Db::insert('questions', ['part' => 'mock', 'mock_test_id' => $mock, 'type' => 'pick',
            'payload' => json_encode(['prompt' => 'Q', 'options' => ['a', 'b'], 'answer' => 0]), 'sort_order' => 1]);
        // Đề cũ còn pass_points mặc định cao cũng không được báo trượt.
        Db::exec('UPDATE mock_tests SET pass_points = 120 WHERE id = ?', [$mock]);
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 18:00:00');
        $p = $this->vaoThi($uid, $mock);
        TestService::patch(Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]), ['answers' => $this->dapAnDung($p['attemptId'])]);

        $out = TestService::grade($p['attemptId']);
        self::assertTrue($out['passed']);
        self::assertTrue(TestService::result(Db::one('SELECT * FROM test_attempts WHERE id = ?', [$p['attemptId']]))['passed']);
    }

    public function testDongThiRoiDeNhapVanMoDuocKetQuaCuaMinh(): void
    {
        $nguoiThi = $this->makeUser();
        $nguoiLa = $this->makeUser();
        $mock = $this->de();
        $this->cuocThi($mock, '2026-11-20 09:00:00', '2026-11-20 10:30:00');
        $p = $this->vaoThi($nguoiThi, $mock);
        TestService::grade($p['attemptId']);

        Clock::freeze(new DateTimeImmutable('2026-11-20 11:00:00'));
        self::assertSame(['resultAttemptId' => $p['attemptId']], TestService::start($nguoiThi, null, $mock));
        try {
            TestService::start($nguoiLa, null, $mock);
            self::fail('Người không thi vẫn 404');
        } catch (HttpError $e) {
            self::assertSame(404, $e->status);
        }
    }

    public function testBienTapKhongDuocDungDeCuaCuocThi(): void
    {
        $mock = $this->de();
        Db::exec('UPDATE mock_tests SET for_contest = 1 WHERE id = ?', [$mock]);
        $thuong = $this->de();
        $cauId = (int) Db::val('SELECT id FROM questions WHERE mock_test_id = ? LIMIT 1', [$mock]);

        $goi = function (string $role, string $method, int $id, array $body = []): int {
            $r = new Request('GET', '/x', [], $body);
            $r->params = ['id' => (string) $id];
            $r->user = ['id' => $this->makeUser(['role' => $role]), 'role' => $role];
            try {
                (new QuestionController())->$method($r);
                return 200;
            } catch (HttpError $e) {
                return $e->status;
            }
        };

        foreach (['mockQuestions', 'updateMock', 'deleteMock', 'createMockQuestion', 'bulkMock', 'reorderMockQuestions', 'copyIntoMockTest'] as $m) {
            self::assertSame(403, $goi('editor', $m, $mock), "$m phải chặn editor");
        }
        // Gắn vào cuộc thi thì cũng khoá, dù không đánh dấu for_contest.
        $this->cuocThi($thuong, '2026-11-30 20:00:00', '2026-11-30 21:00:00');
        self::assertSame(403, $goi('editor', 'mockQuestions', $thuong));
        // Sửa từng câu qua /admin/questions/{id} cũng bị chặn.
        self::assertSame(403, $goi('editor', 'delete', $cauId));
        self::assertNotNull(Db::val('SELECT id FROM questions WHERE id = ?', [$cauId]));

        self::assertSame(200, $goi('admin', 'mockQuestions', $mock));
        $binhThuong = $this->de();
        self::assertSame(200, $goi('editor', 'mockQuestions', $binhThuong));
    }

    public function testNoiTuLuonXaoCotPhai(): void
    {
        $q = ['id' => 1, 'type' => 'match', 'payload' => json_encode(['pairs' => [['a', '1'], ['b', '2'], ['c', '3'], ['d', '4']]])];
        $bien = new \ReflectionMethod(TestService::class, 'buildMap');
        for ($i = 0; $i < 30; $i++) {
            $map = $bien->invoke(null, $q, false);
            self::assertNotSame([0, 1, 2, 3], $map, 'Không được để nguyên thứ tự gốc');
            self::assertCount(4, array_unique($map));
        }
    }
}
