<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use ReflectionProperty;
use Zika\Controllers\Admin\StatsController;
use Zika\Core\Auth;
use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Services\UserService;
use Zika\Tests\DbTestCase;

/**
 * Khoá quyền quản trị bằng `admin_emails` trong config.php: DB ghi role = admin mà email không có trong danh sách thì
 * vẫn chỉ là người học (người dùng yêu cầu 08/11/2026 — sửa được DB cũng không tự lên quyền được).
 */
final class AdminEmailsTest extends DbTestCase
{
    /** @var array<string,mixed> */
    private array $cauHinhGoc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $p = new ReflectionProperty(Config::class, 'items');
        $this->cauHinhGoc = $p->getValue();
    }

    protected function tearDown(): void
    {
        Config::set($this->cauHinhGoc);
        parent::tearDown();
    }

    /** @param list<string> $ds */
    private function danhSach(array $ds): void
    {
        Config::set(['admin_emails' => $ds] + $this->cauHinhGoc);
    }

    /** Gắn phiên như trình duyệt gửi cookie, để đi đúng đường Auth::attach của mọi request thật. */
    private function vaiTroKhiDangNhap(int $uid): ?string
    {
        $token = bin2hex(random_bytes(16));
        Db::insert('auth_sessions', [
            'user_id' => $uid, 'token_hash' => hash('sha256', $token), 'remember' => 1,
            'device_type' => 'desktop', 'os' => '', 'browser' => '', 'ip' => '',
            'created_at' => Clock::sql(), 'last_used_at' => Clock::sql(), 'expires_at' => Clock::sql(Clock::addSeconds(3600)),
        ]);
        $r = new Request('GET', '/me', cookies: [Auth::COOKIE => $token]);
        Auth::attach($r);
        return $r->user['role'] ?? null;
    }

    public function testChuaDienDanhSachThiKhongGioiHan(): void
    {
        $this->danhSach([]);
        $uid = $this->makeUser(['email' => 'ai@zika.test', 'role' => 'admin']);

        self::assertSame('admin', $this->vaiTroKhiDangNhap($uid));
        self::assertSame(1, UserService::activeAdminCount());
    }

    public function testAdminNgoaiDanhSachChiLaNguoiHoc(): void
    {
        $this->danhSach([' Boss@Zika.TEST ']);
        $that = $this->makeUser(['email' => 'boss@zika.test', 'role' => 'admin']);
        $gia = $this->makeUser(['email' => 'ke-gian@zika.test', 'role' => 'admin']);
        $bienTap = $this->makeUser(['email' => 'bt@zika.test', 'role' => 'editor']);

        self::assertSame('admin', $this->vaiTroKhiDangNhap($that));
        self::assertSame('learner', $this->vaiTroKhiDangNhap($gia));
        self::assertSame('editor', $this->vaiTroKhiDangNhap($bienTap));
        self::assertSame('learner', UserService::toApi(UserService::find($gia))['role']);
        self::assertSame(1, UserService::activeAdminCount());
        self::assertSame(0, UserService::activeAdminCount([$that]));
    }

    public function testKhongCapQuyenQuanTriChoEmailNgoaiDanhSach(): void
    {
        $this->danhSach(['boss@zika.test']);
        $boss = $this->makeUser(['email' => 'boss@zika.test', 'role' => 'admin']);
        $ban = $this->makeUser(['email' => 'ban@zika.test']);

        $r = new Request('PATCH', "/admin/users/$ban/role", [], ['role' => 'admin']);
        $r->user = ['id' => $boss, 'role' => 'admin'];
        $r->params = ['id' => (string) $ban];
        try {
            (new StatsController())->role($r);
            self::fail('Phải chặn');
        } catch (HttpError $e) {
            self::assertStringContainsString('danh sách quản trị', $e->getMessage());
        }
        self::assertSame('learner', Db::val('SELECT role FROM users WHERE id = ?', [$ban]));

        $r2 = new Request('PATCH', "/admin/users/$ban/role", [], ['role' => 'editor']);
        $r2->user = ['id' => $boss, 'role' => 'admin'];
        $r2->params = ['id' => (string) $ban];
        (new StatsController())->role($r2);
        self::assertSame('editor', Db::val('SELECT role FROM users WHERE id = ?', [$ban]));
    }
}
