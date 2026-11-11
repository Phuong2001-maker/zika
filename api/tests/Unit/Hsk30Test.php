<?php
declare(strict_types=1);
namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\Hsk30Controller as AdminHsk30;
use Zika\Controllers\Admin\QuestionController;
use Zika\Controllers\Hsk30Controller;
use Zika\Controllers\TestController;
use Zika\Core\{Clock, Db, HttpError, Request, Response, Router};
use Zika\Services\{ContentVisibility, FeatureAccess, PartContent, QuestionPayload, TestService};
use Zika\Tests\DbTestCase;

/**
 * Thi thử HSK 3.0 (migration 027): kho đề chung không theo giáo trình, 4 phần nghe/đọc/viết/nói, câu nói ghi âm ở máy
 * người học (không chấm), câu chọn tranh, chế độ luyện tập xem đáp án từng câu và thi thật chấm khi nộp.
 */
final class Hsk30Test extends DbTestCase
{
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser(['role' => 'admin']);
    }

    private function req(string $method, string $path, array $body = [], ?int $uid = null, string $role = 'admin', array $params = []): Request
    {
        $r = new Request($method, $path, body: $body);
        $r->user = ['id' => $uid ?? $this->admin, 'role' => $role];
        $r->params = $params;
        return $r;
    }

    private function json(Response $res): array
    {
        return json_decode($res->body, true)['data'];
    }

    /** Tạo đề qua API quản trị, trả id. */
    private function taoDe(int $level = 1, string $title = 'HSK 1 · Đề 1', int $giay = 1920): int
    {
        return (int) $this->json((new AdminHsk30())->create($this->req('POST', '/admin/hsk30', ['levelId' => $level, 'title' => $title, 'durationSec' => $giay])))['id'];
    }

    private function themCau(int $de, string $type, array $payload, string $section, string $explain = 'Giải thích'): array
    {
        return $this->json((new QuestionController())->createMockQuestion(
            $this->req('POST', "/admin/mock-tests/$de/questions", ['type' => $type, 'payload' => $payload, 'explain' => $explain, 'score' => 1, 'section' => $section], params: ['id' => (string) $de]),
        ));
    }

    /** Đề mẫu: 1 câu nghe chọn tranh, 1 câu đọc, 1 câu viết sắp xếp, 1 câu nói. Thêm theo thứ tự lộn để kiểm sắp lại. */
    private function deMau(bool $congKhai = true): int
    {
        $de = $this->taoDe();
        $this->themCau($de, 'speak', ['task' => 'repeat', 'audioText' => '我喜欢喝茶。', 'prepSec' => 0, 'answerSec' => 15, 'sample' => '我喜欢喝茶。', 'sampleVi' => 'Tôi thích uống trà.'], 'speaking');
        $this->themCau($de, 'pic', ['audioText' => '我喝茶。', 'options' => ['cha', 'ca-phe', 'sua'], 'answer' => 0], 'listening');
        $this->themCau($de, 'blank', ['sentence' => '我___中国人。', 'vi' => 'Tôi là người Trung Quốc.', 'showMeaning' => false, 'options' => ['是', '在', '有'], 'answer' => 0], 'reading');
        $this->themCau($de, 'order', ['vi' => 'Tôi là học sinh.', 'answer' => '我是学生。', 'tiles' => ['我', '是', '学生']], 'writing');
        if ($congKhai) {
            Db::update('mock_tests', ['status' => 'published'], ['id' => $de]);
        }
        return $de;
    }

    private function batDau(int $uid, int $de, string $mode = 'exam'): array
    {
        return $this->json((new TestController())->start($this->req('POST', '/tests/start', ['mockTestId' => $de, 'mode' => $mode], $uid, 'learner')));
    }

    private function loi(callable $fn): int
    {
        try {
            $fn();
            return 0;
        } catch (HttpError $e) {
            return $e->status;
        }
    }

    // ------------------------------------------------------------------ quản trị

    public function testTaoDeLaNhapVaKhongLanVaoThiThuCu(): void
    {
        $de = $this->taoDe(2, 'HSK 2 · Đề 1', 3120);
        $m = Db::one('SELECT * FROM mock_tests WHERE id = ?', [$de]);
        self::assertSame(['hsk30', 'draft', 0, 0], [$m['kind'], $m['status'], (int) $m['pass_points'], (int) $m['for_contest']]);
        self::assertSame([$de], array_column((new AdminHsk30())->index($this->req('GET', '/admin/hsk30')), 'id'));
        // Danh sách thi thử cuối cấp cũ (admin và người học) không thấy đề HSK 3.0.
        Db::update('mock_tests', ['status' => 'published'], ['id' => $de]);
        self::assertSame([], (new QuestionController())->mockTests($this->req('GET', '/admin/levels/2/mock-tests', params: ['id' => '2'])));
        Db::exec('UPDATE levels SET is_visible = 1');
        self::assertSame([], PartContent::reviewKt(2, false, 1)['mockTests']);
        self::assertSame('E', Router::match('GET', '/admin/hsk30')['auth']);
        self::assertSame('E', Router::match('POST', '/admin/hsk30')['auth']);
        // Khách học thử làm được đề cấp 1 (TestService::startKhach), nên kho đề và lượt làm bài mở công khai.
        self::assertSame('P', Router::match('GET', '/learn/hsk30')['auth']);
        self::assertSame('P', Router::match('POST', '/tests/attempts/5/check')['auth']);
    }

    public function testCauNoiKhongMangDiemVaLuonOPhanNoi(): void
    {
        $de = $this->taoDe();
        $q = $this->themCau($de, 'speak', ['task' => 'topic', 'prompt' => '我的家', 'sample' => '我家有三口人。'], 'reading');
        self::assertSame(['speaking', 0.0], [$q['section'], $q['score']]);
        // Đề HSK 3.0 nhận phần viết; đề thi thử cũ chỉ có nghe/đọc như trước.
        self::assertSame('writing', $this->themCau($de, 'order', ['vi' => 'Tôi đi.', 'answer' => '我去。', 'tiles' => ['我', '去']], 'writing')['section']);
        $cu = Db::insert('mock_tests', ['level_id' => 1, 'title' => 'Thi thử cũ']);
        self::assertSame(422, $this->loi(fn () => $this->themCau($cu, 'order', ['vi' => 'Tôi đi.', 'answer' => '我去。', 'tiles' => ['我', '去']], 'writing')));
        self::assertSame(422, $this->loi(fn () => $this->themCau($cu, 'speak', ['task' => 'topic', 'prompt' => '我的家', 'sample' => 'x'], 'reading')));
    }

    public function testKiemNoiDungCauNoiVaCauChonTranh(): void
    {
        $bad = fn (string $t, array $p) => $this->loi(fn () => QuestionPayload::clean($t, $p));
        self::assertSame(422, $bad('speak', ['task' => 'repeat', 'prompt' => 'có chữ mà không có câu để nghe']));
        self::assertSame(422, $bad('speak', ['task' => 'picture']));
        self::assertSame(422, $bad('speak', ['task' => 'answer', 'prompt' => '你好吗？', 'answerSec' => 2]));
        self::assertSame(422, $bad('pic', ['audioText' => '我喝茶。', 'options' => ['cha', 'Trà xanh'], 'answer' => 0]));
        $ok = QuestionPayload::clean('speak', ['task' => 'picture', 'imageUrl' => '/hinh-hsk/an-com.svg', 'sample' => '他在吃饭。']);
        self::assertSame(['/hinh-hsk/an-com.svg', 10, 30], [$ok['imageUrl'], $ok['prepSec'], $ok['answerSec']]);
        // Đường dẫn hình ngoài kho bị bỏ, không nhúng được URL lạ.
        self::assertArrayNotHasKey('imageUrl', QuestionPayload::clean('tf', ['passage' => '我在家。', 'statement' => '他在家。', 'imageUrl' => 'https://x.test/a.svg']));
        self::assertArrayNotHasKey('imageUrl', QuestionPayload::clean('tf', ['passage' => '我在家。', 'statement' => '他在家。', 'imageUrl' => '/hinh-hsk/../x.svg']));
    }

    // ------------------------------------------------------------------ người học

    public function testDanhSachDeChungMoiGiaoTrinhChiDeCongKhai(): void
    {
        $de = $this->deMau();
        $this->taoDe(1, 'HSK 1 · Đề nháp');
        $hanNgu = Db::insert('curricula', ['name' => 'Hán ngữ 6 tập', 'level_label' => 'Quyển']);
        foreach ([$this->makeUser(), $this->makeUser(['curriculum_id' => $hanNgu])] as $uid) {
            $ds = (new Hsk30Controller())->index($this->req('GET', '/learn/hsk30', uid: $uid, role: 'learner'))['levels'];
            self::assertSame([1], array_column($ds, 'level'));
            self::assertSame([$de], array_column($ds[0]['exams'], 'id'));
            self::assertSame(['listening' => 1, 'reading' => 1, 'writing' => 1, 'speaking' => 1], $ds[0]['exams'][0]['sections']);
        }
    }

    public function testKhachHocThuLamDeCap1TheoCookieKhongGhiTienDo(): void
    {
        $de = $this->deMau();
        $deCap2 = $this->taoDe(2, 'HSK 2 · Đề 1');
        Db::update('mock_tests', ['status' => 'published'], ['id' => $deCap2]);
        $cookie = ['zk_khach' => str_repeat('a', 43)];
        $khach = function (string $method, string $path, array $body = [], array $params = [], ?array $cookies = null) use ($cookie): Request {
            $r = new Request($method, $path, body: $body, cookies: $cookies ?? $cookie, ip: '10.0.0.1');
            $r->params = $params;
            return $r;
        };
        $batDau = fn (int $deId, ?array $cookies = null) => $this->json((new TestController())->start($khach('POST', '/tests/start', ['mockTestId' => $deId, 'mode' => 'exam'], cookies: $cookies)));

        $a = $batDau($de);
        $id = (int) $a['attemptId'];
        $dong = Db::one('SELECT user_id, guest_key FROM test_attempts WHERE id = ?', [$id]);
        self::assertSame([null, hash('sha256', $cookie['zk_khach'])], [$dong['user_id'], $dong['guest_key']], 'DB chỉ giữ mã đã băm');
        // Tải lại trang thì làm tiếp đúng lượt cũ; khách khác (cookie khác) không chạm được lượt này.
        self::assertSame($id, (int) $batDau($de)['attemptId']);
        $nop = fn (array $cookies) => (new TestController())->submit($khach('POST', "/tests/attempts/$id/submit", ['answers' => [(string) $a['questions'][0]['id'] => 0]], ['id' => (string) $id], $cookies));
        self::assertSame(404, $this->loi(fn () => $nop(['zk_khach' => str_repeat('b', 43)])));
        self::assertSame(4, $nop($cookie)['questionCount']);
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM study_days'), 'Khách không có ngày học để ghi');
        self::assertSame(1, (new Hsk30Controller())->index($khach('GET', '/learn/hsk30'))['levels'][0]['exams'][0]['best']['attempts']);

        // Đề cấp 2 phải đăng nhập. Trình duyệt chưa có mã thì được cấp cookie mới.
        self::assertSame(401, $this->loi(fn () => $batDau($deCap2)));
        $batDau($de, []);
        self::assertArrayHasKey('zk_khach', \Zika\Core\Cookie::pending());
    }

    public function testThiThatGiauDapAnVaKhongChoXemTungCau(): void
    {
        $de = $this->deMau();
        $uid = $this->makeUser();
        $a = $this->batDau($uid, $de);
        self::assertSame('exam', $a['mode']);
        self::assertSame(['listening', 'reading', 'writing', 'speaking'], array_column($a['questions'], 'section'), 'Đề luôn xếp theo phần');
        $noi = $a['questions'][3]['payload'];
        self::assertArrayNotHasKey('sample', $noi);
        self::assertArrayNotHasKey('sampleVi', $noi);
        self::assertSame(3.0, (float) $a['maxScore'], 'Câu nói không cộng vào điểm tối đa');
        $kiem = $this->req('POST', "/tests/attempts/{$a['attemptId']}/check", ['qid' => $a['questions'][0]['id'], 'answer' => 0], $uid, 'learner', ['id' => (string) $a['attemptId']]);
        self::assertSame(403, $this->loi(fn () => (new TestController())->check($kiem)));
    }

    public function testLuyenTapXemDapAnTungCauVaTachLuotThiThat(): void
    {
        $de = $this->deMau();
        $uid = $this->makeUser();
        $thi = $this->batDau($uid, $de);
        $luyen = $this->batDau($uid, $de, 'practice');
        self::assertSame('practice', $luyen['mode']);
        self::assertNotSame($thi['attemptId'], $luyen['attemptId'], 'Luyện tập và thi thật là hai lượt riêng');
        self::assertGreaterThan(Clock::now()->getTimestamp() + 3600, strtotime($luyen['deadlineAt']), 'Luyện tập không tính giờ');

        $q = $luyen['questions'][0];
        $dung = array_search('cha', $q['payload']['options'], true);
        $kiem = fn (int $qid, mixed $ans) => (new TestController())->check(
            $this->req('POST', "/tests/attempts/{$luyen['attemptId']}/check", ['qid' => $qid, 'answer' => $ans], $uid, 'learner', ['id' => (string) $luyen['attemptId']]),
        );
        $r = $kiem($q['id'], $dung);
        self::assertSame([true, true, $dung, 'Tranh ' . chr(65 + 0)], [$r['graded'], $r['correct'], $r['rightIndex'], $r['rightAnswerText']]);
        $noi = $kiem($luyen['questions'][3]['id'], 'ghi');
        self::assertSame([false, false, '我喜欢喝茶。', 'Tôi thích uống trà.'], [$noi['graded'], $noi['correct'], $noi['sample'], $noi['sampleVi']]);
        self::assertSame(404, $this->loi(fn () => $kiem(999999, 0)));

        // Đề thi thử cũ: xin luyện tập vẫn ra thi thật.
        $cu = Db::insert('mock_tests', ['level_id' => 1, 'title' => 'Cũ', 'status' => 'published']);
        Db::insert('questions', ['part' => 'mock', 'mock_test_id' => $cu, 'type' => 'tf', 'payload' => '{"passage":"我在家。","statement":"我在家。","answer":true}', 'section' => 'reading']);
        self::assertSame('exam', $this->batDau($uid, $cu, 'practice')['mode']);
    }

    public function testChamKhongTinhCauNoiVaKetQuaTheoPhan(): void
    {
        $de = $this->deMau();
        $uid = $this->makeUser();
        $a = $this->batDau($uid, $de);
        [$pic, $blank, $order, $speak] = $a['questions'];
        $tra = [
            $pic['id'] => array_search('cha', $pic['payload']['options'], true),
            $blank['id'] => array_search('在', $blank['payload']['options'], true),
            $speak['id'] => 'ghi',
        ];
        $kq = (new TestController())->submit($this->req('POST', "/tests/attempts/{$a['attemptId']}/submit", ['answers' => $tra], $uid, 'learner', ['id' => (string) $a['attemptId']]));
        self::assertSame([1.0, 3.0, 33, 1], [(float) $kq['score'], (float) $kq['maxScore'], $kq['percent'], $kq['correctCount']]);
        self::assertSame(
            [['listening', true, 1, 1, 100], ['reading', true, 1, 0, 0], ['writing', true, 1, 0, 0], ['speaking', false, 1, 1, 100]],
            array_map(static fn ($s) => [$s['section'], $s['graded'], $s['total'], $s['done'], $s['percent']], $kq['sections']),
        );
        self::assertSame([$blank['id'], $order['id']], array_column($kq['results'], 'qid'), 'Câu nói không bị tính là câu sai');
        self::assertSame([[$speak['id'], 'Đã ghi âm', '我喜欢喝茶。']], array_map(static fn ($x) => [$x['qid'], $x['yourAnswerText'], $x['rightAnswerText']], $kq['speaking']));
    }

    public function testQuyenRiengKhongTheoGiaoTrinhVaLuotDangLamVanNopDuoc(): void
    {
        $de = $this->deMau();
        $uid = $this->makeUser();
        // Ẩn cấp 1 của giáo trình học viên không chặn đề HSK 3.0 cấp 1.
        Db::insert('curriculum_levels', ['curriculum_id' => 1, 'level_id' => 1, 'is_visible' => 0]);
        self::assertFalse(ContentVisibility::levelVisible(1, 1));
        ContentVisibility::enforce($this->req('POST', '/tests/start', ['mockTestId' => $de], $uid, 'learner'));
        ContentVisibility::enforce($this->req('GET', "/tests/mock/$de/summary", [], $uid, 'learner'));

        // Mặc định mở cho mọi vai trò; tắt riêng Thi thử HSK 3.0 thì chặn cả danh sách lẫn vào thi, Kiểm tra không liên quan.
        self::assertTrue(FeatureAccess::allows('learner', 'learn.hsk30'));
        $a = $this->batDau($uid, $de);
        Db::exec("INSERT INTO feature_access (feature_key, role, enabled) VALUES ('learn.hsk30', 'learner', 0)");
        FeatureAccess::clearCache();
        $khac = $this->makeUser();
        foreach ([$this->req('GET', '/learn/hsk30', [], $uid, 'learner'), $this->req('POST', '/tests/start', ['mockTestId' => $de, 'mode' => 'practice'], $khac, 'learner')] as $r) {
            self::assertSame(403, $this->loi(fn () => FeatureAccess::enforce($r)), $r->path);
        }
        self::assertTrue(FeatureAccess::allows('learner', 'learn.tests') === false && FeatureAccess::allows('admin', 'learn.hsk30'));
        // Lượt thi thật đang làm vẫn lưu và nộp được.
        $r = $this->req('POST', "/tests/attempts/{$a['attemptId']}/submit", [], $uid, 'learner', ['id' => (string) $a['attemptId']]);
        FeatureAccess::enforce($r);
        FeatureAccess::enforce($this->req('POST', '/tests/start', ['mockTestId' => $de], $uid, 'learner'));
    }

    public function testDeNhapKhongVaoDuoc(): void
    {
        $de = $this->deMau(false);
        self::assertSame(404, $this->loi(fn () => $this->batDau($this->makeUser(), $de)));
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM questions WHERE mock_test_id = ? AND section = ?', [$de, 'speaking']));
        self::assertSame([], TestService::sectionStats([]));
    }

    /** Đề cuộc thi (tab Đề thi, 10/11) soạn theo khung HSK 3.0: nhận phần viết/nói, câu nói không điểm, làm theo thứ tự phần. */
    public function testDeCuocThiSoanTheoKhungHsk30(): void
    {
        $tao = $this->req('POST', '/admin/exams', ['title' => 'Đề thi tháng 11']);
        $de = (int) $this->json((new \Zika\Controllers\Admin\ContestController())->createExam($tao))['id'];
        self::assertTrue(TestService::khungHsk30(Db::one('SELECT * FROM mock_tests WHERE id = ?', [$de])));
        self::assertFalse(TestService::khungHsk30(['kind' => 'mock', 'for_contest' => 0]));

        $noi = $this->themCau($de, 'speak', ['task' => 'repeat', 'audioText' => '我喜欢喝茶。', 'prepSec' => 0, 'answerSec' => 15, 'sample' => '我喜欢喝茶。', 'sampleVi' => 'Tôi thích uống trà.'], 'speaking');
        $viet = $this->themCau($de, 'order', ['vi' => 'Tôi là học sinh.', 'answer' => '我是学生。', 'tiles' => ['我', '是', '学生']], 'writing');
        $nghe = $this->themCau($de, 'pic', ['audioText' => '我喝茶。', 'options' => ['cha', 'ca-phe', 'sua'], 'answer' => 0], 'listening');
        self::assertSame('speaking', $noi['section']);
        self::assertSame(0.0, (float) $noi['score']);
        self::assertSame('writing', $viet['section']);
        self::assertSame('listening', $nghe['section']);

        // Cuộc thi đang mở: người học đã đăng ký vào làm ở màn thi HSK 3.0, câu theo thứ tự Nghe → Viết → Nói.
        $uid = $this->makeUser();
        $cid = Db::insert('contests', [
            'title' => 'Thi tháng 11', 'mock_test_id' => $de, 'duration_min' => 30,
            'starts_at' => Clock::sql(Clock::now()->modify('-5 minutes')), 'ends_at' => Clock::sql(Clock::now()->modify('+25 minutes')),
        ]);
        Db::insert('contest_registrations', ['contest_id' => $cid, 'user_id' => $uid, 'created_at' => Clock::sql(Clock::now()->modify('-1 day'))]);
        self::assertTrue(\Zika\Services\ContestService::current($uid)['hsk30']);
        $a = TestService::start($uid, null, $de, 'practice');
        self::assertSame('exam', $a['mode'], 'Cuộc thi luôn là thi thật, không có luyện tập');
        self::assertTrue($a['mock']['forContest']);
        self::assertSame(['listening', 'writing', 'speaking'], array_column($a['questions'], 'section'));
    }
}
