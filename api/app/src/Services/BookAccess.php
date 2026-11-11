<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\HttpError;

/**
 * Ai được đọc trang nào của giáo trình (migration 019).
 *
 * Học viên chỉ nhận được ảnh trang, trang đáp án, file nghe của bài đã mở bằng điểm. Mọi kiểm tra nằm ở server,
 * ngay chỗ trả file: giao diện có bị sửa, có ai gõ thẳng đường dẫn hay dùng công cụ tải thì server vẫn không gửi một
 * byte nào của bài chưa mở.
 *
 * Bài mở lần lượt theo thứ tự trong bộ sách (Quyển 1 bài 1 → … → Quyển 2 bài 16…). Không có phần bán sách: người
 * dùng bỏ 08/11/2026 vì bán bản scan sách có bản quyền rủi ro pháp lý cao hơn.
 * Trang trước bài đầu (bìa trong, lời nói đầu, mục lục) đọc tự do, nhưng chỉ ở quyển đang học tới — quyển sau
 * còn khoá cả trang đầu.
 *
 * Điểm: lần nhận đầu tiên được 3, từ hôm sau mỗi ngày bấm nút nhận được 1. Điểm chưa dùng giữ lại;
 * ngày không nhận thì không bù. Một điểm mở một bài. Link Shopee là luồng ủng hộ riêng, không cộng điểm.
 */
final class BookAccess
{
    public const BO = ['hanngu' => 'Hán ngữ 6 tập', 'hsk' => 'Giáo trình HSK 3.0'];
    public const DIEM_LAN_DAU = 3;
    /**
     * Mã ký trên đường dẫn ảnh/file nghe sống 6 giờ: gắn với tài khoản và quyển, nên link dán sang tài khoản khác
     * hay để dành hôm sau đều không mở được. Ngắn hơn thì người đang nghe dở, tạm dừng lâu rồi tua sẽ bị lỗi.
     */
    public const VE_GIAY = 21600;

    /**
     * Mọi quyển có sach.json của một bộ (null = cả hai bộ), Hán ngữ trước rồi HSK, mỗi bộ theo cấp.
     * @return list<array<string,mixed>>
     */
    public static function sachCuaBo(?string $bo): array
    {
        $ra = [];
        foreach (glob(Config::uploadsDir('giao-trinh') . '/*/sach.json') ?: [] as $f) {
            $s = json_decode((string) file_get_contents($f), true);
            if (is_array($s) && isset(self::BO[$s['bo'] ?? '']) && ($bo === null || $s['bo'] === $bo)) {
                $ra[] = $s;
            }
        }
        $thuTu = array_flip(array_keys(self::BO));
        usort($ra, static fn ($a, $b) => [$thuTu[$a['bo']], $a['cap']] <=> [$thuTu[$b['bo']], $b['cap']]);
        return $ra;
    }

    /** Bộ sách của giáo trình người học: giáo trình gọi cấp "Quyển" là Hán ngữ, còn lại là HSK. */
    public static function boCuaNguoi(int $userId): string
    {
        return Curricula::levelLabel(Curricula::ofUser($userId)) === 'Quyển' ? 'hanngu' : 'hsk';
    }

    /** Bộ sách khách học thử thấy: bộ của giáo trình mặc định, giống học viên chưa được phân giáo trình. */
    public static function boKhach(): string
    {
        return Curricula::levelLabel(Curricula::defaultId()) === 'Quyển' ? 'hanngu' : 'hsk';
    }

    /**
     * Trạng thái đọc của khách học thử: chỉ quyển đầu của bộ (giống phần mở sẵn cho học viên), không có điểm nên
     * không có bài nào "mở tiếp" được; quyển sau hiện khoá để khách đăng nhập. Ẩn/hiện nội dung theo giáo trình mặc định.
     * @return array{tuDo:bool, mo:array<string,array<int,true>>, ke:null, toi:array<string,true>, an:array<string,true|array<int,true>>}
     */
    public static function trangThaiKhach(string $bo): array
    {
        $ds = self::sachCuaBo($bo);
        $an = self::an($bo, Curricula::defaultId());
        $mo = self::moQuyenDau($ds, $an);
        return ['tuDo' => false, 'mo' => $mo, 'ke' => null, 'toi' => array_map(static fn (): bool => true, $mo), 'an' => $an];
    }

    /**
     * Quyển đầu tiên của bộ sách là phần nhập môn: ai cũng đọc trọn quyển, không cần dòng mở bài hay trừ điểm.
     * Điểm chỉ bắt đầu dùng từ quyển thứ hai; bài bị ẩn trong cây nội dung vẫn phải giữ kín.
     * @param list<array<string,mixed>> $ds @param array<string,true|array<int,true>> $an
     * @return array<string,array<int,true>>
     */
    private static function moQuyenDau(array $ds, array $an): array
    {
        if (!isset($ds[0]) || ($an[$ds[0]['ma']] ?? []) === true) {
            return [];
        }
        $anQuyenDau = $an[$ds[0]['ma']] ?? [];
        $mo = [];
        foreach ($ds[0]['bai'] as $b) {
            $so = (int) $b['so'];
            if (!isset($anQuyenDau[$so])) {
                $mo[(string) $ds[0]['ma']][$so] = true;
            }
        }
        return $mo;
    }

    /**
     * Giáo trình dùng để xét Ẩn/hiện nội dung cho một bộ sách. Học viên chỉ thấy bộ của giáo trình mình nên lấy
     * giáo trình của chính họ; biên tập/quản trị đọc cả hai bộ nên bộ khác lấy giáo trình gốc của bộ đó.
     */
    public static function giaoTrinhCuaBo(int $userId, string $bo): int
    {
        if (self::boCuaNguoi($userId) === $bo) {
            return Curricula::ofUser($userId);
        }
        if ($bo === 'hsk') {
            return Curricula::HSK_ID;
        }
        return (int) (Db::val("SELECT id FROM curricula WHERE level_label = 'Quyển' ORDER BY is_default DESC, id LIMIT 1") ?? 0);
    }

    /**
     * Bài sách đang ẩn theo cây Giáo trình → Ẩn/hiện nội dung (migration 026). Bài số `so` của quyển cấp `cap` chính là
     * bài lesson_no = so, level_id = cap của giáo trình: cùng cách đánh số với sách (dữ liệu nạp từ chính sách đó).
     * Ẩn giáo trình hay ẩn cấp thì cả quyển ẩn. Bài sách chưa có trong dữ liệu thì không chặn: cây chỉ ẩn được bài nó có.
     *
     * @return array<string, true|array<int,true>> mã quyển → true (cả quyển ẩn) hoặc các số bài đang ẩn
     */
    public static function an(string $bo, int $curriculumId): array
    {
        // Chưa có giáo trình nào cho bộ này (máy mới chưa nạp Hán ngữ): cây không có gì để ẩn.
        if ($curriculumId <= 0) {
            return [];
        }
        $baiAn = [];
        foreach (Db::all("SELECT level_id, lesson_no FROM lessons WHERE curriculum_id = ? AND status <> 'published'", [$curriculumId]) as $l) {
            $baiAn[(int) $l['level_id']][(int) $l['lesson_no']] = true;
        }
        $ra = [];
        foreach (self::sachCuaBo($bo) as $s) {
            $ma = (string) $s['ma'];
            $cap = (int) $s['cap'];
            if (!ContentVisibility::levelVisible($curriculumId, $cap)) {
                $ra[$ma] = true;
                continue;
            }
            foreach ($s['bai'] as $b) {
                if (isset($baiAn[$cap][(int) $b['so']])) {
                    $ra[$ma][(int) $b['so']] = true;
                }
            }
        }
        return $ra;
    }

    /** @param array<string,mixed> $tt trangThai() — cả quyển `ma` đang ẩn: không hiện trên kệ, mọi đường dẫn báo 404. */
    public static function quyenAn(array $tt, string $ma): bool
    {
        return ($tt['an'][$ma] ?? null) === true;
    }

    /** @param array<string,mixed> $tt trangThai() — các số bài đang ẩn của quyển `ma` (quyển ẩn cả thì là mọi bài). */
    public static function baiAn(array $tt, array $s): array
    {
        $an = $tt['an'][(string) $s['ma']] ?? [];
        return $an === true ? array_map('intval', array_column($s['bai'], 'so')) : array_keys($an);
    }

    /**
     * Trạng thái mở của một bộ sách cho một người.
     *
     * @return array{tuDo:bool, mo:array<string,array<int,true>>, ke:?array{ma:string,bai:int}, toi:array<string,true>, an:array<string,true|array<int,true>>}
     *   tuDo: biên tập/quản trị đọc mọi trang · mo: bài đã mở theo mã sách · ke: bài tiếp theo được mở bằng điểm
     *   (null = hết bài) · toi: quyển đã học tới (đọc được trang đầu sách) · an: bài/quyển đang ẩn, xem an().
     *   Bài ẩn chặn cả biên tập/quản trị, giống 6 phần học: muốn xem thì bật lại ở cây.
     */
    public static function trangThai(int $userId, string $bo, bool $tuDo = false): array
    {
        $ds = self::sachCuaBo($bo);
        $an = self::an($bo, self::giaoTrinhCuaBo($userId, $bo));
        $mo = [];
        $toi = [];
        if ($tuDo) {
            foreach ($ds as $s) {
                $toi[$s['ma']] = true;
            }
            return ['tuDo' => true, 'mo' => [], 'ke' => null, 'toi' => $toi, 'an' => $an];
        }
        $ma = array_column($ds, 'ma');
        if ($ma !== []) {
            foreach (Db::all('SELECT ma, bai FROM book_unlocks WHERE user_id = ? AND ma IN (' . Db::in($ma) . ')', [$userId, ...$ma]) as $u) {
                $mo[(string) $u['ma']][(int) $u['bai']] = true;
            }
        }
        foreach (self::moQuyenDau($ds, $an) as $maQuyen => $bai) {
            $mo[$maQuyen] = ($mo[$maQuyen] ?? []) + $bai;
        }
        // Bài ẩn bị bỏ qua khi tìm bài mở tiếp: không thì học viên kẹt trước một bài không ai mở được.
        $ke = null;
        foreach ($ds as $s) {
            $anQuyen = $an[$s['ma']] ?? [];
            if ($anQuyen === true) {
                continue;
            }
            foreach ($s['bai'] as $b) {
                if (!isset($mo[$s['ma']][(int) $b['so']]) && !isset($anQuyen[(int) $b['so']])) {
                    $ke = ['ma' => (string) $s['ma'], 'bai' => (int) $b['so']];
                    break 2;
                }
            }
        }
        foreach ($ds as $s) {
            if (isset($mo[$s['ma']]) || ($ke !== null && $ke['ma'] === $s['ma'])) {
                $toi[$s['ma']] = true;
            }
        }
        return ['tuDo' => false, 'mo' => $mo, 'ke' => $ke, 'toi' => $toi, 'an' => $an];
    }

    /** Số bài (theo sách) chứa trang `trang`: bài cuối cùng bắt đầu trước hoặc đúng trang đó; trang đầu sách → null. */
    public static function baiCuaTrang(array $ds, int $trang): ?int
    {
        $ra = null;
        foreach ($ds as $b) {
            if ((int) $b['trang'] <= $trang) {
                $ra = (int) $b['so'];
            }
        }
        return $ra;
    }

    /** @param array<string,mixed> $tt trangThai() @param array<string,mixed> $s sach.json */
    public static function moBaiSo(array $tt, array $s, ?int $bai): bool
    {
        $ma = (string) $s['ma'];
        $an = $tt['an'][$ma] ?? [];
        if ($an === true || ($bai !== null && isset($an[$bai]))) {
            return false;
        }
        if ($tt['tuDo']) {
            return true;
        }
        // Không thuộc bài nào (trang đầu sách, file nghe phần mở đầu): đọc được khi đã học tới quyển này.
        if ($bai === null || !in_array($bai, array_map('intval', array_column($s['bai'], 'so')), true)) {
            return isset($tt['toi'][$ma]);
        }
        return isset($tt['mo'][$ma][$bai]);
    }

    public static function moTrang(array $tt, array $s, int $trang): bool
    {
        return self::moBaiSo($tt, $s, self::baiCuaTrang($s['bai'], $trang));
    }

    public static function moDapAn(array $tt, array $s, int $trang): bool
    {
        return $s['dapAn'] !== null && self::moBaiSo($tt, $s, self::baiCuaTrang($s['dapAn']['bai'], $trang));
    }

    public static function moNghe(array $tt, array $s, int $so): bool
    {
        foreach ($s['nghe'] as $n) {
            if ((int) $n['so'] === $so) {
                return self::moBaiSo($tt, $s, (int) $n['bai']);
            }
        }
        return false;
    }

    // ---------------------------------------------------------------- điểm

    public static function soDiem(int $userId): int
    {
        return (int) Db::val('SELECT COALESCE(SUM(delta), 0) FROM book_points WHERE user_id = ?', [$userId]);
    }

    public static function daNhanLanDau(int $userId): bool
    {
        return Db::val("SELECT 1 FROM book_points WHERE user_id = ? AND khoa = 'first'", [$userId]) !== null;
    }

    public static function daNhanHomNay(int $userId): bool
    {
        return Db::val('SELECT 1 FROM book_points WHERE user_id = ? AND khoa = ?', [$userId, self::khoaHomNay()]) !== null;
    }

    /**
     * Cộng điểm khi bấm nút nhận. Trả số điểm vừa cộng: 3 lần đầu, 1 lần đầu trong ngày, 0 nếu hôm nay
     * đã nhận. Lần đầu cũng ghi dấu của ngày hôm đó (0 điểm) để cùng ngày không được thêm 1.
     *
     * Chạy trong transaction: hai yêu cầu cùng lúc (bấm đúp, hai tab) thì yêu cầu sau chờ khoá của dòng `first`
     * rồi mới đọc, nên không lọt được 3 + 1.
     */
    public static function nhanDiemNgay(int $userId): int
    {
        return Db::tx(static function () use ($userId): int {
            $luc = Clock::sql();
            $them = static fn (int $delta, string $kind, string $khoa): int => Db::exec(
                'INSERT IGNORE INTO book_points (user_id, delta, kind, khoa, created_at) VALUES (?, ?, ?, ?, ?)',
                [$userId, $delta, $kind, $khoa, $luc],
            );
            if ($them(self::DIEM_LAN_DAU, 'first', 'first') === 1) {
                $them(0, 'daily', self::khoaHomNay());
                return self::DIEM_LAN_DAU;
            }
            return $them(1, 'daily', self::khoaHomNay());
        });
    }

    /**
     * Mở bài `bai` của quyển `ma` bằng 1 điểm. Chỉ mở được đúng bài tiếp theo; bài đã mở thì thôi.
     * Khoá dòng users trong transaction để hai lần bấm cùng lúc không tiêu 2 điểm hay mở vượt lượt.
     */
    public static function moBai(int $userId, string $bo, string $ma, int $bai): void
    {
        Db::tx(static function () use ($userId, $bo, $ma, $bai): void {
            Db::val('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $tt = self::trangThai($userId, $bo);
            if (isset($tt['mo'][$ma][$bai])) {
                return;
            }
            $ke = $tt['ke'];
            if ($ke === null || $ke['ma'] !== $ma || $ke['bai'] !== $bai) {
                throw HttpError::invalid($ke === null
                    ? 'Bạn đã mở hết các bài của bộ sách này.'
                    : 'Bài mở lần lượt theo thứ tự: bài tiếp theo của bạn là Bài ' . $ke['bai'] . '.');
            }
            if (self::soDiem($userId) < 1) {
                throw new HttpError(409, 'NO_POINTS', 'Bạn đã hết điểm mở bài. Mỗi ngày bấm Nhận điểm để được thêm 1 điểm.');
            }
            $luc = Clock::sql();
            Db::insert('book_unlocks', ['user_id' => $userId, 'ma' => $ma, 'bai' => $bai, 'created_at' => $luc]);
            Db::insert('book_points', [
                'user_id' => $userId, 'delta' => -1, 'kind' => 'unlock', 'khoa' => "mo:$ma:$bai", 'created_at' => $luc,
            ]);
        });
    }

    /**
     * Quản trị mở mọi bài của quyển `ma` cho học viên, không trừ điểm (người dùng yêu cầu 09/11). Chỉ quyển thuộc
     * giáo trình học viên đang học: muốn đọc bộ khác thì đổi giáo trình ở Phân quyền học. Bài đã mở thì bỏ qua; mở
     * thêm được bài nào thì ghi một dòng 0 điểm vào sổ để tra lại ai mở, lúc nào. Trả số bài vừa mở thêm.
     *
     * Không đụng tới thứ tự mở bằng điểm: bài tiếp theo vẫn là bài đầu tiên chưa mở, nên mở Quyển 2 khi Quyển 1 còn dở
     * thì học viên vẫn dùng điểm mở nốt Quyển 1, xong thì nhảy qua Quyển 2 sang Quyển 3.
     */
    public static function moCaQuyen(int $userId, string $ma, int $boiAi): int
    {
        $s = null;
        foreach (self::sachCuaBo(self::boCuaNguoi($userId)) as $x) {
            if ($x['ma'] === $ma) {
                $s = $x;
            }
        }
        if ($s === null) {
            throw HttpError::invalid('Quyển này không thuộc giáo trình học viên đang học.');
        }
        return Db::tx(static function () use ($userId, $s, $ma, $boiAi): int {
            Db::val('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $luc = Clock::sql();
            $them = 0;
            foreach ($s['bai'] as $b) {
                $them += Db::exec(
                    'INSERT IGNORE INTO book_unlocks (user_id, ma, bai, created_at) VALUES (?, ?, ?, ?)',
                    [$userId, $ma, (int) $b['so'], $luc],
                );
            }
            if ($them > 0) {
                Db::insert('book_points', [
                    'user_id' => $userId, 'delta' => 0, 'kind' => 'admin', 'khoa' => "moquyen:$ma:" . bin2hex(random_bytes(6)),
                    'note' => "$them bài", 'by_user_id' => $boiAi, 'created_at' => $luc,
                ]);
            }
            return $them;
        });
    }

    /** Quản trị cộng/trừ tay; không cho trừ quá số đang có. */
    public static function congTay(int $userId, int $delta, string $note, int $boiAi): void
    {
        Db::tx(static function () use ($userId, $delta, $note, $boiAi): void {
            Db::val('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if ($delta < 0 && self::soDiem($userId) + $delta < 0) {
                throw HttpError::invalid('Học viên không đủ điểm để trừ.');
            }
            Db::insert('book_points', [
                'user_id' => $userId, 'delta' => $delta, 'kind' => 'admin', 'khoa' => 'admin:' . bin2hex(random_bytes(8)),
                'note' => $note === '' ? null : $note, 'by_user_id' => $boiAi, 'created_at' => Clock::sql(),
            ]);
        });
    }

    private static function khoaHomNay(): string
    {
        return 'daily:' . Clock::now()->format('Y-m-d');
    }

    // ---------------------------------------------------------------- mã ký đường dẫn

    /**
     * @param int|string $userId id tài khoản, hoặc KHACH cho khách học thử. Mã của khách không gắn với ai: khách nào
     * cũng đọc được đúng phần đó (trangThaiKhach), mã chỉ để đường dẫn ảnh hết hạn như của học viên.
     */
    public static function ve(int|string $userId, string $ma): string
    {
        $het = Clock::now()->getTimestamp() + self::VE_GIAY;
        return $het . '.' . self::ky("$userId|$ma|$het");
    }

    public const KHACH = 'khach';

    public static function veHopLe(string $ve, int|string $userId, string $ma): bool
    {
        [$het, $chuKy] = explode('.', $ve, 2) + ['', ''];
        if (!ctype_digit($het) || (int) $het < Clock::now()->getTimestamp()) {
            return false;
        }
        return hash_equals(self::ky("$userId|$ma|$het"), $chuKy);
    }

    private static function ky(string $noiDung): string
    {
        $khoa = (string) Config::get('app_key', '');
        // Máy chưa đặt app_key (Installer đã cảnh báo) vẫn phải có khoá bí mật: lấy từ thông tin DB, không lộ ra web.
        if (strlen($khoa) < 32) {
            $khoa = hash('sha256', 'zika-sach|' . json_encode(Config::get('db', [])));
        }
        return substr(hash_hmac('sha256', "sach|$noiDung", $khoa), 0, 32);
    }
}
