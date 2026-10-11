<?php
declare(strict_types=1);

namespace Zika\Core;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Gửi email qua hàng đợi mail_queue (cron gửi 50 thư / 5 phút).
 * Thư quan trọng (xác nhận, đặt lại mật khẩu) được thử gửi ngay; lỗi thì để cron gửi lại.
 * env=local hoặc chưa cấu hình SMTP: ghi thư vào storage/logs/mail-*.log thay vì gửi thật.
 */
final class Mailer
{
    public static function queue(string $to, string $subject, string $html, string $text, string $kind = 'misc', bool $sendNow = false): int
    {
        $id = Db::insert('mail_queue', [
            'to_email' => $to,
            'subject' => mb_substr($subject, 0, 200),
            'html' => $html,
            'text_body' => $text,
            'kind' => $kind,
        ]);
        if ($sendNow) {
            $row = Db::one('SELECT * FROM mail_queue WHERE id = ?', [$id]);
            if ($row !== null) {
                self::attempt($row);
            }
        }
        return $id;
    }

    /** Gửi các thư đang chờ. @return array{sent:int,failed:int} */
    public static function sendPending(int $limit = 50): array
    {
        $rows = Db::all(
            "SELECT * FROM mail_queue WHERE status = 'pending' AND attempts < 5 ORDER BY id LIMIT ?",
            [$limit],
        );
        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            self::attempt($row) ? $sent++ : $failed++;
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /** @param array<string,mixed> $row */
    private static function attempt(array $row): bool
    {
        try {
            self::deliver($row);
            Db::exec("UPDATE mail_queue SET status = 'sent', sent_at = NOW(), attempts = attempts + 1, last_error = NULL WHERE id = ?", [$row['id']]);
            return true;
        } catch (Throwable $e) {
            Db::exec(
                "UPDATE mail_queue SET attempts = attempts + 1, last_error = ?, status = IF(attempts + 1 >= 5, 'failed', 'pending') WHERE id = ?",
                [mb_substr($e->getMessage(), 0, 500), $row['id']],
            );
            Log::warn('mail.failed', ['id' => $row['id'], 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** @param array<string,mixed> $row */
    private static function deliver(array $row): void
    {
        $host = (string) Config::get('mail.host', '');
        if (Config::isLocal() || $host === '' || !class_exists(PHPMailer::class)) {
            Log::write('info', 'mail', [
                'to' => $row['to_email'], 'subject' => $row['subject'], 'kind' => $row['kind'], 'text' => $row['text_body'],
            ], 'mail');
            return;
        }
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host = $host;
        $m->Port = (int) Config::get('mail.port', 587);
        $m->SMTPAuth = Config::get('mail.user', '') !== '';
        $m->Username = (string) Config::get('mail.user', '');
        $m->Password = (string) Config::get('mail.pass', '');
        $secure = (string) Config::get('mail.secure', 'tls');
        $m->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
        $m->Timeout = 10;
        $m->CharSet = 'UTF-8';
        $m->setFrom((string) Config::get('mail.from'), (string) Config::get('mail.from_name', 'Zìkǎ'));
        $m->addAddress((string) $row['to_email']);
        $m->Subject = (string) $row['subject'];
        $m->isHTML(true);
        $m->Body = (string) $row['html'];
        $m->AltBody = (string) $row['text_body'];
        $m->send();
    }

    /**
     * Khung HTML đơn giản, đúng màu thương hiệu. $paragraphs là các đoạn văn (đã là văn bản thường).
     * @param list<string> $paragraphs
     * @return array{html:string,text:string}
     */
    public static function compose(string $title, array $paragraphs, ?string $buttonText = null, ?string $buttonUrl = null): array
    {
        $site = (string) Settings::value('site', 'name', 'Zìkǎ');
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = '';
        foreach ($paragraphs as $p) {
            $body .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#2E323C">' . nl2br($e($p)) . '</p>';
        }
        if ($buttonText !== null && $buttonUrl !== null) {
            $body .= '<p style="margin:22px 0"><a href="' . $e($buttonUrl) . '" style="display:inline-block;padding:13px 22px;background:#FF6B5E;color:#14161C;'
                . 'border:2px solid #14161C;border-radius:12px;font-weight:700;text-decoration:none">' . $e($buttonText) . '</a></p>'
                . '<p style="margin:0 0 14px;font-size:13px;color:#5F6573">Nếu nút không bấm được, mở link này: ' . $e($buttonUrl) . '</p>';
        }
        $html = '<!doctype html><html lang="vi"><body style="margin:0;background:#F2F5FA;font-family:Arial,Helvetica,sans-serif">'
            . '<div style="max-width:560px;margin:0 auto;padding:28px 16px">'
            . '<div style="font-size:20px;font-weight:800;color:#14161C;margin-bottom:16px">'
            . '<span style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FF6B5E;border:2px solid #14161C;border-radius:9px;margin-right:8px">字</span>'
            . $e($site) . '</div>'
            . '<div style="background:#fff;border:2px solid #14161C;border-radius:18px;padding:24px">'
            . '<h1 style="margin:0 0 14px;font-size:22px;color:#14161C">' . $e($title) . '</h1>' . $body . '</div>'
            . '<p style="font-size:12px;color:#5F6573;margin-top:14px">' . $e($site) . ' — học chữ Hán miễn phí.</p></div></body></html>';
        $text = $title . "\n\n" . implode("\n\n", $paragraphs) . ($buttonUrl !== null ? "\n\n" . $buttonText . ': ' . $buttonUrl : '');
        return ['html' => $html, 'text' => $text];
    }
}
