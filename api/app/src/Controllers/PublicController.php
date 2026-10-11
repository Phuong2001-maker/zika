<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Config;
use Zika\Core\Csrf;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Settings;

/** Endpoint công khai: cấu hình web cho app, trang Chính sách / Điều khoản. */
final class PublicController
{
    /** GET /public/config — gọi đầu tiên khi mở app, đồng thời đặt cookie CSRF (02 §6.2). */
    public function config(Request $r): array
    {
        $site = Settings::get('site');
        $auth = Settings::get('auth');
        return [
            'siteName' => (string) $site['name'],
            'slogan' => (string) $site['slogan'],
            'logoUrl' => $site['logo_path'] ?: null,
            // Nút Google chỉ hiện khi admin bật VÀ đã cấu hình client_id (tránh nút bấm vào là lỗi).
            'googleEnabled' => (bool) $auth['google'] && (string) Config::get('google.client_id', '') !== '',
            'emailEnabled' => (bool) $auth['email'],
            'allowSignup' => (bool) $auth['allow_signup'],
            'contactEmail' => (string) $site['contact_email'],
            'showFeedbackButton' => (bool) Settings::value('content', 'show_feedback_button', true),
            'csrf' => Csrf::ensure($r),
        ];
    }

    /** GET /public/pages/{privacy|terms} */
    public function page(Request $r): array
    {
        $slug = $r->param('slug');
        $privacy = Settings::get('privacy');
        return match ($slug) {
            'privacy' => ['title' => 'Chính sách riêng tư', 'markdown' => (string) $privacy['privacy_page']],
            'terms' => ['title' => 'Điều khoản sử dụng', 'markdown' => (string) $privacy['terms_page']],
            default => throw HttpError::notFound(),
        };
    }
}
