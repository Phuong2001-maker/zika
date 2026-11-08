<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Settings;
use Zika\Core\Str;

/**
 * Quán trà — phòng trò chuyện của người học, chia "bàn" theo chủ đề (migration 020, 021).
 *
 * Không có WebSocket trên hosting dùng chung: web hỏi tin mới (after = id tin cuối) vài giây một lần, kèm `since` để
 * biết tin nào vừa bị xoá mà gỡ khỏi màn. Chỉ hiện tên hiển thị, không bao giờ lộ email.
 * Chống phá: mỗi người cách nhau ít nhất 2 giây và tối đa 20 tin mỗi 5 phút (tính chung mọi bàn); quản trị xoá tin,
 * cấm chat, đóng bàn hoặc đóng cả quán.
 */
final class QuanTra
{
    public const DAI_TOI_DA = 1000;
    private const CACH_GIAY = 2;
    private const TOI_DA_5_PHUT = 20;

    public static function moCua(): bool
    {
        return (bool) Settings::value('chat', 'enabled', true);
    }

    /**
     * Các bàn, kèm tin mới nhất và số tin 24 giờ qua — để danh sách bàn cho thấy bàn nào đang sôi nổi.
     * @param bool $caBanDong quản trị thấy cả bàn đã ẩn
     * @return list<array<string,mixed>>
     */
    public static function cacBan(bool $caBanDong = true): array
    {
        $rows = Db::all(
            'SELECT r.*,
                    (SELECT MAX(m.id) FROM chat_messages m WHERE m.room_id = r.id AND m.deleted_at IS NULL) cuoi_id,
                    (SELECT COUNT(*) FROM chat_messages m WHERE m.room_id = r.id AND m.deleted_at IS NULL AND m.created_at >= ?) hom_nay
               FROM chat_rooms r ORDER BY r.sort_order, r.id',
            [Clock::sql(Clock::now()->modify('-24 hours'))],
        );
        $ra = [];
        foreach ($rows as $r) {
            if (!$caBanDong && !(int) $r['is_open'] && $r['cuoi_id'] === null) {
                continue;
            }
            $cuoi = $r['cuoi_id'] === null ? null : Db::one(
                'SELECT m.body, m.created_at, u.display_name FROM chat_messages m JOIN users u ON u.id = m.user_id WHERE m.id = ?',
                [(int) $r['cuoi_id']],
            );
            $ra[] = [
                'id' => (int) $r['id'],
                'slug' => (string) $r['slug'],
                'name' => (string) $r['name'],
                'description' => (string) $r['description'],
                'icon' => (string) $r['icon'],
                'open' => (bool) $r['is_open'],
                'sortOrder' => (int) $r['sort_order'],
                'lastId' => $r['cuoi_id'] === null ? 0 : (int) $r['cuoi_id'],
                'last' => $cuoi === null ? null : [
                    'name' => (string) $cuoi['display_name'],
                    'body' => mb_substr((string) $cuoi['body'], 0, 80),
                    'at' => Clock::iso((string) $cuoi['created_at']),
                ],
                'today' => (int) $r['hom_nay'],
            ];
        }
        return $ra;
    }

    /** @return array<string,mixed> dòng chat_rooms theo slug */
    public static function ban(string $slug): array
    {
        $r = Db::one('SELECT * FROM chat_rooms WHERE slug = ?', [$slug]);
        if ($r === null) {
            throw HttpError::notFound('Không có bàn này ở Quán trà.');
        }
        return $r;
    }

    /**
     * Tin của một bàn. after: tin mới hơn id này (hỏi định kỳ) · before: tin cũ hơn (cuộn lên xem lại) · không có cả
     * hai: `soTin` tin mới nhất. Luôn trả theo thứ tự cũ → mới.
     * @return list<array<string,mixed>>
     */
    public static function tin(int $banId, int $nguoiXem, bool $quanTri, ?int $after, ?int $before, int $soTin = 50): array
    {
        $where = 'm.room_id = ?';
        $args = [$banId];
        if ($after !== null) {
            $where .= ' AND m.id > ?';
            $args[] = $after;
        } elseif ($before !== null) {
            $where .= ' AND m.id < ?';
            $args[] = $before;
        }
        $rows = Db::all(
            "SELECT m.*, u.display_name, u.avatar_path, u.role,
                    r.id r_id, r.body r_body, r.deleted_at r_deleted, ru.display_name r_name,
                    (SELECT COUNT(*) FROM chat_reports cr WHERE cr.message_id = m.id) bao_cao
               FROM chat_messages m
               JOIN users u ON u.id = m.user_id
               LEFT JOIN chat_messages r ON r.id = m.reply_to
               LEFT JOIN users ru ON ru.id = r.user_id
              WHERE $where
              ORDER BY m.id " . ($after !== null ? 'ASC' : 'DESC') . '
              LIMIT ' . max(1, min(100, $soTin)),
            $args,
        );
        if ($after === null) {
            $rows = array_reverse($rows);
        }
        return array_map(static fn (array $m): array => self::dinhDang($m, $nguoiXem, $quanTri), $rows);
    }

    /** @return list<int> id các tin của bàn bị xoá từ mốc `since` — web gỡ khỏi màn khi đang mở. */
    public static function daXoaTu(int $banId, string $since): array
    {
        return array_map('intval', Db::col('SELECT id FROM chat_messages WHERE room_id = ? AND deleted_at >= ?', [$banId, $since]));
    }

    /** @param array<string,mixed> $m @return array<string,mixed> */
    private static function dinhDang(array $m, int $nguoiXem, bool $quanTri): array
    {
        $xoa = $m['deleted_at'] !== null;
        $ten = (string) $m['display_name'];
        $ra = [
            'id' => (int) $m['id'],
            'user' => [
                'id' => (int) $m['user_id'],
                'name' => $ten,
                'initials' => Str::initials($ten),
                'avatarUrl' => $m['avatar_path'] ?: null,
                'staff' => self::laQuanTri((string) $m['role']),
            ],
            'body' => $xoa ? '' : (string) $m['body'],
            'deleted' => $xoa,
            'mine' => (int) $m['user_id'] === $nguoiXem,
            'createdAt' => Clock::iso((string) $m['created_at']),
            'replyTo' => $m['r_id'] === null ? null : [
                'id' => (int) $m['r_id'],
                'name' => (string) $m['r_name'],
                'body' => $m['r_deleted'] !== null ? '' : mb_substr((string) $m['r_body'], 0, 120),
                'deleted' => $m['r_deleted'] !== null,
            ],
        ];
        if ($quanTri) {
            $ra['reports'] = (int) $m['bao_cao'];
        }
        return $ra;
    }

    private static function laQuanTri(string $role): bool
    {
        return in_array($role, ['editor', 'admin'], true);
    }

    /** Mốc hết cấm (ISO) nếu đang bị cấm; '' nếu cấm vĩnh viễn; null nếu không bị cấm. */
    public static function dangBiCam(int $userId): ?string
    {
        $c = Db::one('SELECT until FROM chat_mutes WHERE user_id = ?', [$userId]);
        if ($c === null) {
            return null;
        }
        if ($c['until'] === null) {
            return '';
        }
        return $c['until'] > Clock::sql() ? (string) Clock::iso((string) $c['until']) : null;
    }

    /**
     * Gửi tin vào bàn. @param array<string,mixed> $ban dòng chat_rooms
     * @return int id tin mới
     */
    public static function gui(array $ban, int $userId, string $noiDung, ?int $traLoi): int
    {
        if (!self::moCua()) {
            throw HttpError::forbidden('Quán trà đang tạm đóng cửa.');
        }
        if (!(int) $ban['is_open']) {
            throw HttpError::forbidden('Bàn này đang đóng, chỉ đọc được tin cũ.');
        }
        $cam = self::dangBiCam($userId);
        if ($cam !== null) {
            throw HttpError::forbidden($cam === '' ? 'Bạn đã bị cấm trò chuyện ở Quán trà.' : 'Bạn đang bị tạm cấm trò chuyện đến ' . Clock::parse($cam)->format('H:i d/m/Y') . '.');
        }
        // Gộp khoảng trắng thừa, tối đa hai dòng trống liền nhau: tin dài toàn xuống dòng là kiểu phá phòng.
        $noiDung = trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $noiDung)));
        if ($noiDung === '') {
            throw HttpError::invalid('Bạn chưa viết gì.');
        }
        if (mb_strlen($noiDung) > self::DAI_TOI_DA) {
            throw HttpError::invalid('Tin nhắn dài tối đa ' . self::DAI_TOI_DA . ' ký tự.');
        }
        $bay = Clock::now();
        $cuoi = Db::val('SELECT MAX(created_at) FROM chat_messages WHERE user_id = ?', [$userId]);
        if ($cuoi !== null && Clock::parse((string) $cuoi)->getTimestamp() > $bay->getTimestamp() - self::CACH_GIAY) {
            throw new HttpError(429, 'RATE_LIMITED', 'Bạn gửi nhanh quá, đợi một chút nhé.');
        }
        $dem = (int) Db::val('SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND created_at >= ?', [$userId, Clock::sql($bay->modify('-5 minutes'))]);
        if ($dem >= self::TOI_DA_5_PHUT) {
            throw new HttpError(429, 'RATE_LIMITED', 'Bạn đã gửi nhiều tin trong 5 phút. Nghỉ chút rồi trò chuyện tiếp nhé.');
        }
        if ($traLoi !== null && Db::val('SELECT 1 FROM chat_messages WHERE id = ? AND room_id = ? AND deleted_at IS NULL', [$traLoi, $ban['id']]) === null) {
            throw HttpError::invalid('Tin bạn trả lời không còn nữa.');
        }
        return Db::insert('chat_messages', [
            'room_id' => (int) $ban['id'], 'user_id' => $userId, 'body' => $noiDung, 'reply_to' => $traLoi, 'created_at' => Clock::sql($bay),
        ]);
    }

    /** Xoá tin: người gửi xoá tin mình, biên tập/quản trị xoá mọi tin. */
    public static function xoa(int $id, int $userId, bool $quanTri): void
    {
        $m = Db::one('SELECT user_id, deleted_at FROM chat_messages WHERE id = ?', [$id]);
        if ($m === null) {
            throw HttpError::notFound('Không có tin nhắn này.');
        }
        if (!$quanTri && (int) $m['user_id'] !== $userId) {
            throw HttpError::forbidden('Bạn chỉ xoá được tin của mình.');
        }
        if ($m['deleted_at'] === null) {
            Db::update('chat_messages', ['deleted_at' => Clock::sql(), 'deleted_by' => $userId], ['id' => $id]);
        }
    }

    /**
     * Báo cáo tin xấu. Tin của biên tập/quản trị không báo cáo được: chính họ là người xử lý báo cáo — có ý kiến về
     * quản trị thì gửi qua Góp ý.
     */
    public static function baoCao(int $id, int $userId, string $lyDo): void
    {
        $m = Db::one('SELECT m.user_id, u.role FROM chat_messages m JOIN users u ON u.id = m.user_id WHERE m.id = ? AND m.deleted_at IS NULL', [$id]);
        if ($m === null) {
            throw HttpError::notFound('Tin này không còn nữa.');
        }
        if ((int) $m['user_id'] === $userId) {
            throw HttpError::invalid('Không báo cáo tin của chính mình.');
        }
        if (self::laQuanTri((string) $m['role'])) {
            throw HttpError::invalid('Tin của quản trị không báo cáo được. Có ý kiến, bạn gửi qua Góp ý nhé.');
        }
        Db::exec('INSERT IGNORE INTO chat_reports (message_id, user_id, reason, created_at) VALUES (?, ?, ?, ?)', [$id, $userId, mb_substr(trim($lyDo), 0, 200), Clock::sql()]);
    }

    /** Cấm chat `phut` phút (null = vĩnh viễn). Gọi lại là ghi đè mốc cũ. */
    public static function cam(int $userId, ?int $phut, string $lyDo, int $boiAi): void
    {
        $den = $phut === null ? null : Clock::sql(Clock::now()->modify("+$phut minutes"));
        Db::exec(
            'INSERT INTO chat_mutes (user_id, until, reason, by_user_id, created_at) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE until = VALUES(until), reason = VALUES(reason), by_user_id = VALUES(by_user_id), created_at = VALUES(created_at)',
            [$userId, $den, mb_substr(trim($lyDo), 0, 200), $boiAi, Clock::sql()],
        );
    }

    public static function boCam(int $userId): void
    {
        Db::exec('DELETE FROM chat_mutes WHERE user_id = ?', [$userId]);
    }
}
