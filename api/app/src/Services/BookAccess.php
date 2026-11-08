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

    /**
     * Trạng thái mở của một bộ sách cho một người.
     *
     * @return array{tuDo:bool, mo:array<string,array<int,true>>, ke:?array{ma:string,bai:int}, toi:array<string,true>}
     *   tuDo: biên tập/quản trị đọc mọi trang · mo: bài đã mở theo mã sách · ke: bài tiếp theo được mở bằng điểm
     *   (null = hết bài) · toi: quyển đã học tới (đọc được trang đầu sách)
     */
    public static function trangThai(int $userId, string $bo, bool $tuDo = false): array
    {
        $ds = self::sachCuaBo($bo);
        $mo = [];
        $toi = [];
        if ($tuDo) {
            foreach ($ds as $s) {
                $toi[$s['ma']] = true;
            }
            return ['tuDo' => true, 'mo' => [], 'ke' => null, 'toi' => $toi];
        }
        $ma = array_column($ds, 'ma');
        if ($ma !== []) {
            foreach (Db::all('SELECT ma, bai FROM book_unlocks WHERE user_id = ? AND ma IN (' . Db::in($ma) . ')', [$userId, ...$ma]) as $u) {
                $mo[(string) $u['ma']][(int) $u['bai']] = true;
            }
        }
        $ke = null;
        foreach ($ds as $s) {
            foreach ($s['bai'] as $b) {
                if (!isset($mo[$s['ma']][(int) $b['so']])) {
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
        return ['tuDo' => false, 'mo' => $mo, 'ke' => $ke, 'toi' => $toi];
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

    public static function ve(int $userId, string $ma): string
    {
        $het = Clock::now()->getTimestamp() + self::VE_GIAY;
        return $het . '.' . self::ky("$userId|$ma|$het");
    }

    public static function veHopLe(string $ve, int $userId, string $ma): bool
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
