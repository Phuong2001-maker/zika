<?php
declare(strict_types=1);

namespace Zika;

/**
 * Gửi email.
 *
 * Driver 'log' ghi nội dung ra storage/mail.log — khi dev thì tiện vì không cần
 * SMTP, và link đặt lại mật khẩu vẫn lấy được từ file log để thử.
 * Driver 'mail' dùng hàm mail() của PHP (cần sendmail/SMTP cấu hình ở php.ini).
 */
final class Mail
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $driver = (string) Config::get('mail.driver', 'log');
        $from   = (string) Config::get('mail.from', 'no-reply@zika.local');

        if ($driver === 'log') {
            $line = sprintf(
                "[%s] To: %s\nSubject: %s\n%s\n%s\n",
                date('Y-m-d H:i:s'),
                $to,
                $subject,
                $body,
                str_repeat('-', 60)
            );
            @file_put_contents(Util::storagePath('mail.log'), $line, FILE_APPEND | LOCK_EX);
            return true;
        }

        $headers = implode("\r\n", [
            'From: Zika <' . $from . '>',
            'Content-Type: text/plain; charset=utf-8',
            'MIME-Version: 1.0',
        ]);

        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    public static function sendReset(string $to, string $token): bool
    {
        $base = (string) (Db::val('SELECT v FROM settings WHERE k = ?', ['site_url']) ?? 'http://localhost:5173');
        $link = rtrim($base, '/') . '/reset?token=' . $token;

        $body = "Chào bạn,\n\n"
            . "Bạn vừa yêu cầu đặt lại mật khẩu Zika. Bấm vào link dưới đây:\n\n"
            . $link . "\n\n"
            . 'Link có hiệu lực trong ' . (int) Config::get('auth.reset_minutes', 30) . " phút.\n"
            . "Nếu không phải bạn yêu cầu thì bỏ qua email này.\n\n"
            . "— Zika\n";

        return self::send($to, 'Đặt lại mật khẩu Zika', $body);
    }
}
