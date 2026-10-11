<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Str;

/** Token xác minh email (24 giờ) và đặt lại mật khẩu (30 phút). DB chỉ lưu sha256, dùng 1 lần. */
final class EmailTokenService
{
    public const TTL = ['verify' => 86400, 'reset' => 1800];

    public static function create(int $userId, string $kind): string
    {
        $token = Str::token();
        // Token cũ cùng loại chưa dùng thì vô hiệu, chỉ link mới nhất còn hiệu lực.
        Db::exec('UPDATE email_tokens SET used_at = NOW() WHERE user_id = ? AND kind = ? AND used_at IS NULL', [$userId, $kind]);
        Db::insert('email_tokens', [
            'user_id' => $userId,
            'kind' => $kind,
            'token_hash' => hash('sha256', $token),
            'expires_at' => Clock::sql(Clock::addSeconds(self::TTL[$kind])),
        ]);
        return $token;
    }

    /** Dùng token; trả id người dùng hoặc null nếu hỏng/hết hạn/đã dùng. */
    public static function consume(string $token, string $kind): ?int
    {
        if ($token === '' || strlen($token) > 100) {
            return null;
        }
        return Db::tx(static function () use ($token, $kind): ?int {
            $row = Db::one(
                'SELECT id, user_id FROM email_tokens WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE',
                [hash('sha256', $token), $kind],
            );
            if ($row === null) {
                return null;
            }
            Db::exec('UPDATE email_tokens SET used_at = NOW() WHERE id = ?', [$row['id']]);
            return (int) $row['user_id'];
        });
    }
}
