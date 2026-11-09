<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use DateTimeImmutable;
use Zika\Controllers\Admin\ChatController as ChatAdmin;
use Zika\Controllers\ChatController;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Router;
use Zika\Core\Settings;
use Zika\Tests\DbTestCase;

/** Quán trà: gửi, trả lời, đọc theo mốc, xoá, báo cáo, chống gửi dồn, cấm chat, đóng phòng. */
final class QuanTraTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // site_settings giữ nguyên giữa các test (DbTestCase::KEEP).
        Settings::set('chat', Settings::DEFAULTS['chat']);
        // Bàn mặc định giữ giữa các test (DbTestCase::KEEP): bỏ bàn test tạo thêm, mở lại bàn bị đóng.
        Db::exec('DELETE FROM chat_rooms WHERE id > 5');
        Db::exec('UPDATE chat_rooms SET is_open = 1');
        $this->luc('2026-11-08 09:00:00');
    }

    private function luc(string $t): void
    {
        Clock::freeze(new DateTimeImmutable($t, Clock::tz()));
    }

    /** @param array<string,mixed> $body @param array<string,mixed> $query @param array<string,string> $params */
    private function req(int $uid, array $body = [], array $query = [], array $params = [], string $role = 'learner'): Request
    {
        $r = new Request('POST', '/chat', query: $query, body: $body);
        $r->params = $params;
        $r->user = ['id' => $uid, 'role' => $role];
        return $r;
    }

    private function loi(callable $fn): string
    {
        try {
            $fn();
            return '';
        } catch (HttpError $e) {
            return $e->status . ' ' . $e->errorCode;
        }
    }

    private function gui(int $uid, string $body, ?int $traLoi = null, string $ban = 'chung'): int
    {
        $res = (new ChatController())->create($this->req($uid, ['body' => $body, 'replyTo' => $traLoi], params: ['ban' => $ban]));
        return (int) json_decode($res->body, true)['data']['id'];
    }

    public function testRoute(): void
    {
        self::assertSame('L', Router::match('GET', '/chat')['auth']);
        self::assertSame('hoi-bai', Router::match('GET', '/chat/ban/hoi-bai')['params']['ban']);
        self::assertSame('L', Router::match('DELETE', '/chat/tin/5')['auth']);
        self::assertSame('A', Router::match('POST', '/admin/chat/mute')['auth']);
    }

    public function testGuiTraLoiVaDocTheoMoc(): void
    {
        $a = $this->makeUser(['role' => 'learner', 'display_name' => 'Minh Anh']);
        $b = $this->makeUser(['role' => 'learner', 'display_name' => 'Lan']);
        $c = new ChatController();
        $m1 = $this->gui($a, "Chào cả nhà!\n\n\n\nMình mới học HSK 1");
        $this->luc('2026-11-08 09:00:05');
        $m2 = $this->gui($b, 'Chào bạn 👋', $m1);

        $ds = $c->index($this->req($b, params: ['ban' => 'chung']));
        self::assertSame([$m1, $m2], array_column($ds['messages'], 'id'));
        // Gộp dòng trống thừa; không lộ email.
        self::assertSame("Chào cả nhà!\n\nMình mới học HSK 1", $ds['messages'][0]['body']);
        self::assertArrayNotHasKey('email', $ds['messages'][0]['user']);
        self::assertSame(['Minh Anh', false, true], [$ds['messages'][1]['replyTo']['name'], $ds['messages'][0]['mine'], $ds['messages'][1]['mine']]);
        // Hỏi tin mới sau m1 chỉ ra m2.
        self::assertSame([$m2], array_column($c->index($this->req($b, query: ['after' => $m1], params: ['ban' => 'chung']))['messages'], 'id'));
        self::assertSame([$m1], array_column($c->index($this->req($b, query: ['before' => $m2], params: ['ban' => 'chung']))['messages'], 'id'));
    }

    public function testChongGuiDonDap(): void
    {
        $a = $this->makeUser(['role' => 'learner']);
        $this->gui($a, 'một');
        self::assertSame('429 RATE_LIMITED', $this->loi(fn () => $this->gui($a, 'hai')));
        // Cách 2 giây thì gửi được; quá 20 tin trong 5 phút thì chặn.
        for ($i = 0; $i < 19; $i++) {
            $this->luc('2026-11-08 09:00:' . str_pad((string) (3 + $i * 3), 2, '0', STR_PAD_LEFT));
            $this->gui($a, "tin $i");
        }
        $this->luc('2026-11-08 09:01:30');
        self::assertSame('429 RATE_LIMITED', $this->loi(fn () => $this->gui($a, 'quá nhiều')));
        self::assertSame('422 VALIDATION', $this->loi(fn () => $this->gui($this->makeUser(), str_repeat('a', 1001))));
    }

    public function testXoaVaBaoCao(): void
    {
        $a = $this->makeUser(['role' => 'learner']);
        $b = $this->makeUser(['role' => 'learner']);
        $admin = $this->makeUser(['role' => 'admin']);
        $c = new ChatController();
        $m = $this->gui($a, 'Tin cần xoá');
        // Người khác không xoá được, quản trị xoá được.
        self::assertSame('403 FORBIDDEN', $this->loi(fn () => $c->delete($this->req($b, params: ['id' => (string) $m]))));
        $c->report($this->req($b, ['reason' => 'Spam'], params: ['id' => (string) $m]));
        $c->report($this->req($b, ['reason' => 'Spam lần 2'], params: ['id' => (string) $m]));
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM chat_reports'));
        self::assertSame('422 VALIDATION', $this->loi(fn () => $c->report($this->req($a, params: ['id' => (string) $m]))));
        $bc = (new ChatAdmin())->index($this->req($admin, role: 'admin'));
        self::assertSame([1, $m, 'Spam'], [$bc['counts']['reported'], $bc['messages'][0]['id'], $bc['messages'][0]['reasons']]);

        $this->luc('2026-11-08 09:10:00');
        $c->delete($this->req($admin, params: ['id' => (string) $m], role: 'admin'));
        $ds = $c->index($this->req($b, query: ['since' => '2026-11-08T09:05:00+07:00'], params: ['ban' => 'chung']));
        self::assertSame([true, ''], [$ds['messages'][0]['deleted'], $ds['messages'][0]['body']]);
        self::assertSame([$m], $ds['deleted']);
    }

    public function testMoiBanMotChuDeVaQuanTriKhongBiBaoCao(): void
    {
        $a = $this->makeUser(['role' => 'learner']);
        $admin = $this->makeUser(['role' => 'admin']);
        $c = new ChatController();
        $hoi = $this->gui($a, 'Chữ 了 dùng thế nào ạ?', null, 'hoi-bai');
        $this->luc('2026-11-08 09:00:05');
        $chung = $this->gui($a, 'Chào cả nhà');
        self::assertSame([$hoi], array_column($c->index($this->req($a, params: ['ban' => 'hoi-bai']))['messages'], 'id'));
        self::assertSame([$chung], array_column($c->index($this->req($a, params: ['ban' => 'chung']))['messages'], 'id'));
        // Không trả lời chéo bàn.
        $this->luc('2026-11-08 09:00:10');
        self::assertSame('422 VALIDATION', $this->loi(fn () => $this->gui($a, 'trả lời nhầm bàn', $hoi)));
        // Danh sách bàn có tin mới nhất.
        $ds = array_column($c->rooms($this->req($a))['rooms'], null, 'slug');
        self::assertSame([5, $hoi, 'Chữ 了 dùng thế nào ạ?', 1], [count($ds), $ds['hoi-bai']['lastId'], $ds['hoi-bai']['last']['body'], $ds['hoi-bai']['today']]);
        self::assertSame('404 NOT_FOUND', $this->loi(fn () => $c->index($this->req($a, params: ['ban' => 'khong-co']))));

        // Tin của quản trị không báo cáo được.
        $tinQt = $this->gui($admin, 'Nhắc mọi người giữ lịch sự nhé');
        self::assertSame('422 VALIDATION', $this->loi(fn () => $c->report($this->req($a, params: ['id' => (string) $tinQt]))));
        self::assertTrue($c->index($this->req($a, params: ['ban' => 'chung']))['messages'][1]['user']['staff']);
    }

    public function testQuanTriThemVaDongBan(): void
    {
        $a = $this->makeUser(['role' => 'learner']);
        $admin = $this->makeUser(['role' => 'admin']);
        $qt = new ChatAdmin();
        $res = $qt->saveRoom($this->req($admin, ['name' => 'Luyện thi HSK 3', 'description' => 'Ôn đề', 'icon' => '📝', 'sortOrder' => 6], role: 'admin'));
        $moi = json_decode($res->body, true)['data'];
        self::assertSame('luyen-thi-hsk-3', $moi['slug']);
        $this->gui($a, 'Ai thi HSK 3 tháng 12 không?', null, 'luyen-thi-hsk-3');
        // Đóng bàn: vẫn đọc được, không gửi được.
        $qt->saveRoom($this->req($admin, ['name' => 'Luyện thi HSK 3', 'open' => false], params: ['id' => (string) $moi['id']], role: 'admin'));
        $this->luc('2026-11-08 09:01:00');
        self::assertSame('403 FORBIDDEN', $this->loi(fn () => $this->gui($a, 'còn ai không', null, 'luyen-thi-hsk-3')));
        self::assertCount(1, (new ChatController())->index($this->req($a, params: ['ban' => 'luyen-thi-hsk-3']))['messages']);
    }

    public function testCamChatVaDongPhong(): void
    {
        $a = $this->makeUser(['role' => 'learner']);
        $admin = $this->makeUser(['role' => 'admin']);
        $qt = new ChatAdmin();
        $qt->mute($this->req($admin, ['userId' => $a, 'minutes' => 60, 'reason' => 'Nói tục'], role: 'admin'));
        self::assertSame('403 FORBIDDEN', $this->loi(fn () => $this->gui($a, 'xin chào')));
        self::assertNotNull((new ChatController())->index($this->req($a, params: ['ban' => 'chung']))['mutedUntil']);
        // Hết hạn cấm thì gửi lại được.
        $this->luc('2026-11-08 10:01:00');
        self::assertSame('', $this->loi(fn () => $this->gui($a, 'mình quay lại rồi')));

        $qt->toggle($this->req($admin, ['open' => false], role: 'admin'));
        $this->luc('2026-11-08 10:02:00');
        self::assertSame('403 FORBIDDEN', $this->loi(fn () => $this->gui($a, 'có ai không')));
        self::assertFalse((new ChatController())->index($this->req($a, params: ['ban' => 'chung']))['open']);
    }
}
