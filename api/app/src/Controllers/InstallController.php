<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Throwable;
use Zika\Core\Config;
use Zika\Core\HttpError;
use Zika\Core\Log;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Services\Installer;

/**
 * Cài đặt qua trình duyệt khi hosting không có SSH (02 §8.3 bước 7).
 * Chỉ chạy khi config.install_token khác rỗng VÀ chưa có admin nào; xong thì xoá install_token.
 */
final class InstallController
{
    public function form(Request $r): Response
    {
        $this->guard((string) $r->q('token', ''));
        return Response::html($this->page('Cài đặt Zìkǎ', $this->formHtml((string) $r->q('token', ''), [], [])));
    }

    public function run(Request $r): Response
    {
        $token = (string) $r->input('token', '');
        $this->guard($token);
        $old = ['email' => (string) $r->input('email', ''), 'name' => (string) $r->input('name', '')];

        $problems = Installer::problems();
        if ($problems !== []) {
            return Response::html($this->page('Chưa cài được', '<p>Máy chủ còn thiếu:</p><ul><li>' . implode('</li><li>', array_map([$this, 'e'], $problems)) . '</li></ul>'), 500);
        }
        if ((string) $r->input('password') !== (string) $r->input('password2')) {
            return Response::html($this->page('Cài đặt Zìkǎ', $this->formHtml($token, $old, ['password2' => 'Hai mật khẩu chưa khớp.'])), 422);
        }
        try {
            Installer::migrate();
            Installer::createAdmin($old['email'], $old['name'], (string) $r->input('password', ''));
        } catch (HttpError $e) {
            return Response::html($this->page('Cài đặt Zìkǎ', $this->formHtml($token, $old, $e->fields)), 422);
        } catch (Throwable $e) {
            Log::exception($e);
            return Response::html($this->page('Lỗi khi cài', '<p>' . $this->e($e->getMessage()) . '</p>'), 500);
        }
        $login = $this->e(Config::appUrl() . '/login');
        return Response::html($this->page('Cài đặt xong', "<p>Cài đặt xong. Đăng nhập tại <a href=\"$login\">$login</a>.</p>"
            . '<p><b>Quan trọng:</b> hãy xoá giá trị <code>install_token</code> trong config.php ngay bây giờ.</p>'));
    }

    private function guard(string $token): void
    {
        $expected = (string) Config::get('install_token', '');
        if ($expected === '' || !hash_equals($expected, $token) || Installer::hasAdmin()) {
            throw HttpError::notFound();
        }
    }

    /** @param array<string,string> $old @param array<string,string> $errors */
    private function formHtml(string $token, array $old, array $errors): string
    {
        $err = fn (string $k) => isset($errors[$k]) ? '<div class="err">' . $this->e($errors[$k]) . '</div>' : '';
        return '<p>Tạo bảng dữ liệu và tài khoản quản trị đầu tiên.</p><form method="post" action="/api/install">'
            . '<input type="hidden" name="token" value="' . $this->e($token) . '">'
            . '<label>Email<input name="email" type="email" required value="' . $this->e($old['email'] ?? '') . '"></label>' . $err('email')
            . '<label>Tên<input name="name" required value="' . $this->e($old['name'] ?? '') . '"></label>' . $err('name')
            . '<label>Mật khẩu<input name="password" type="password" required minlength="8"></label>' . $err('password')
            . '<label>Nhập lại mật khẩu<input name="password2" type="password" required minlength="8"></label>' . $err('password2')
            . '<button type="submit">Cài đặt</button></form>';
    }

    private function page(string $title, string $body): string
    {
        return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $this->e($title) . '</title><style>body{font:15px/1.5 system-ui,sans-serif;background:#F2F5FA;color:#14161C;margin:0;padding:32px 16px}'
            . 'main{max-width:440px;margin:0 auto;background:#fff;border:2.5px solid #14161C;border-radius:22px;box-shadow:6px 6px 0 #14161C;padding:24px 28px}'
            . 'label{display:block;font-weight:700;font-size:13px;margin-top:12px}input{display:block;width:100%;box-sizing:border-box;height:46px;margin-top:6px;padding:0 12px;border:1.5px solid #C9D1DD;border-radius:12px;font:inherit}'
            . 'button{margin-top:18px;width:100%;height:50px;border:2px solid #14161C;border-radius:14px;background:#FF6B5E;font:700 16px system-ui;box-shadow:3px 3px 0 #14161C;cursor:pointer}'
            . '.err{color:#D0342A;font-weight:600;font-size:13px;margin-top:4px}</style></head><body><main><h1>' . $this->e($title) . '</h1>' . $body . '</main></body></html>';
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
