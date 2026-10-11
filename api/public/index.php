<?php
declare(strict_types=1);

/**
 * Điểm vào duy nhất của API Zika.
 *
 * Chạy khi dev:
 *   php -S localhost:8000 -t api/public
 * Khi deploy: trỏ DocumentRoot vào thư mục này (Apache dùng .htaccess kèm theo,
 * Nginx thì try_files $uri /index.php?$query_string).
 */

use Zika\Config;
use Zika\Ctrl\AdminContentCtrl;
use Zika\Ctrl\AdminCtrl;
use Zika\Ctrl\AuthCtrl;
use Zika\Ctrl\ContentCtrl;
use Zika\Ctrl\MiscCtrl;
use Zika\Ctrl\ProgressCtrl;
use Zika\Http\ApiError;
use Zika\Http\Req;
use Zika\Http\Res;
use Zika\Router;

// ── Autoload: Zika\Foo\Bar -> src/Foo/Bar.php (không cần Composer) ──
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Zika\\')) {
        return;
    }
    $rel  = str_replace('\\', '/', substr($class, 5));
    $file = dirname(__DIR__) . '/src/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

Config::load(dirname(__DIR__) . '/config.php');

// Lỗi PHP không bao giờ in ra giữa JSON; ghi vào log của web server.
ini_set('display_errors', '0');
error_reporting(E_ALL);

Res::cors();

$req = new Req();

// Preflight CORS: trả rỗng, header đã do Res::cors() đặt.
if ($req->method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$r = new Router();

// ───────────────────────── Công khai ─────────────────────────
$r->get('/api/health', static fn() => Res::ok(['ok' => true, 'time' => date('c')]));
$r->get('/api/config', [MiscCtrl::class, 'config']);

$r->post('/api/auth/register', [AuthCtrl::class, 'register']);
$r->post('/api/auth/login',    [AuthCtrl::class, 'login']);
$r->post('/api/auth/google',   [AuthCtrl::class, 'google']);
$r->post('/api/auth/logout',   [AuthCtrl::class, 'logout']);
$r->post('/api/auth/forgot',   [AuthCtrl::class, 'forgot']);
$r->post('/api/auth/reset',    [AuthCtrl::class, 'reset']);

// ───────────────────────── Tài khoản ─────────────────────────
$r->get('/api/me',                    [AuthCtrl::class, 'me']);
$r->patch('/api/me',                  [AuthCtrl::class, 'updateMe']);
$r->delete('/api/me',                 [AuthCtrl::class, 'deleteMe']);
$r->post('/api/me/password',          [AuthCtrl::class, 'setPassword']);
$r->get('/api/me/settings',           [AuthCtrl::class, 'getSettings']);
$r->put('/api/me/settings',           [AuthCtrl::class, 'putSettings']);
$r->get('/api/me/devices',            [AuthCtrl::class, 'devices']);
$r->delete('/api/me/devices/{id}',    [AuthCtrl::class, 'revokeDevice']);

// ───────────────────────── Nội dung học ─────────────────────────
$r->get('/api/levels',                    [ContentCtrl::class, 'levels']);
$r->get('/api/levels/{hsk}/lessons',      [ContentCtrl::class, 'lessons']);
$r->get('/api/levels/{hsk}/words',        [ContentCtrl::class, 'levelWords']);
$r->get('/api/lessons/{id}/words',        [ContentCtrl::class, 'words']);
$r->get('/api/lessons/{id}/exercises',    [ContentCtrl::class, 'exercises']);
$r->get('/api/lessons/{id}/test',         [ContentCtrl::class, 'test']);
$r->get('/api/lessons/{id}/hanzi',        [ContentCtrl::class, 'hanzi']);
$r->get('/api/lessons/{id}/dialogue',     [ContentCtrl::class, 'dialogue']);
$r->get('/api/lessons/{id}/grammar',      [ContentCtrl::class, 'grammar']);

// ───────────────────────── Tiến độ ─────────────────────────
$r->get('/api/progress/continue',   [ProgressCtrl::class, 'continueCard']);
$r->post('/api/progress/study',     [ProgressCtrl::class, 'study']);
$r->post('/api/progress/exercise',  [ProgressCtrl::class, 'exercise']);
$r->post('/api/progress/part',      [ProgressCtrl::class, 'part']);
$r->post('/api/progress/word',      [ProgressCtrl::class, 'word']);
$r->post('/api/attempts/test',      [ProgressCtrl::class, 'submitTest']);
$r->post('/api/session/ping',       [ProgressCtrl::class, 'ping']);
$r->post('/api/session/end',        [ProgressCtrl::class, 'endSession']);

// ───────────────────────── Thông báo, góp ý, ủng hộ ─────────────────────────
$r->get('/api/news',                      [MiscCtrl::class, 'news']);
$r->post('/api/feedback',                 [MiscCtrl::class, 'sendFeedback']);
$r->get('/api/support-links',             [MiscCtrl::class, 'supportLinks']);
$r->get('/api/support-links/{id}/go',     [MiscCtrl::class, 'supportGo']);

// ───────────────────────── Quản trị ─────────────────────────
$r->get('/api/admin/overview',        [AdminCtrl::class, 'overview']);
$r->get('/api/admin/users',           [AdminCtrl::class, 'users']);
$r->get('/api/admin/users/{id}',      [AdminCtrl::class, 'userDetail']);
$r->patch('/api/admin/users/{id}',    [AdminCtrl::class, 'updateUser']);
$r->get('/api/admin/sessions',        [AdminCtrl::class, 'sessions']);

$r->get('/api/admin/news',            [AdminCtrl::class, 'newsList']);
$r->post('/api/admin/news',           [AdminCtrl::class, 'saveNews']);
$r->patch('/api/admin/news/{id}',     [AdminCtrl::class, 'saveNews']);
$r->delete('/api/admin/news/{id}',    [AdminCtrl::class, 'deleteNews']);

$r->get('/api/admin/feedback',        [AdminCtrl::class, 'feedbackList']);
$r->patch('/api/admin/feedback/{id}', [AdminCtrl::class, 'updateFeedback']);

$r->get('/api/admin/support-links',         [AdminCtrl::class, 'supportList']);
$r->post('/api/admin/support-links',        [AdminCtrl::class, 'saveSupport']);
$r->patch('/api/admin/support-links/{id}',  [AdminCtrl::class, 'saveSupport']);
$r->delete('/api/admin/support-links/{id}', [AdminCtrl::class, 'deleteSupport']);

$r->get('/api/admin/settings',        [AdminCtrl::class, 'settingsGet']);
$r->put('/api/admin/settings',        [AdminCtrl::class, 'settingsPut']);
$r->post('/api/admin/staff',          [AdminCtrl::class, 'inviteStaff']);

// Soạn nội dung
$r->get('/api/admin/levels/{hsk}/lessons',      [AdminContentCtrl::class, 'lessons']);
$r->put('/api/admin/levels/{hsk}/lesson-order', [AdminContentCtrl::class, 'reorderLessons']);
$r->post('/api/admin/lessons',                  [AdminContentCtrl::class, 'saveLesson']);
$r->patch('/api/admin/lessons/{id}',            [AdminContentCtrl::class, 'saveLesson']);
$r->delete('/api/admin/lessons/{id}',           [AdminContentCtrl::class, 'deleteLesson']);

$r->get('/api/admin/words',                     [AdminContentCtrl::class, 'wordsOverview']);
$r->get('/api/admin/lessons/{id}/words',        [AdminContentCtrl::class, 'lessonWords']);
$r->put('/api/admin/lessons/{id}/words',        [AdminContentCtrl::class, 'saveWords']);
$r->get('/api/admin/lessons/{id}/questions',    [AdminContentCtrl::class, 'questions']);
$r->put('/api/admin/lessons/{id}/questions',    [AdminContentCtrl::class, 'saveQuestions']);
$r->get('/api/admin/lessons/{id}/hanzi',        [AdminContentCtrl::class, 'hanziList']);
$r->put('/api/admin/lessons/{id}/hanzi',        [AdminContentCtrl::class, 'saveHanzi']);
$r->get('/api/admin/lessons/{id}/dialogue',     [AdminContentCtrl::class, 'dialogueGet']);
$r->put('/api/admin/lessons/{id}/dialogue',     [AdminContentCtrl::class, 'dialogueSave']);
$r->get('/api/admin/lessons/{id}/grammar',      [AdminContentCtrl::class, 'grammarGet']);
$r->put('/api/admin/lessons/{id}/grammar',      [AdminContentCtrl::class, 'grammarSave']);

// ───────────────────────── Chạy ─────────────────────────
try {
    $r->dispatch($req);
} catch (ApiError $e) {
    Res::error($e->getMessage(), $e->status, $e->code, $e->fields);
} catch (Throwable $e) {
    error_log('[zika] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (Config::get('debug', false)) {
        Res::json([
            'error' => [
                'code'    => 'server_error',
                'message' => $e->getMessage(),
                'at'      => $e->getFile() . ':' . $e->getLine(),
                'trace'   => explode("\n", $e->getTraceAsString()),
            ],
        ], 500);
    }
    Res::error('Có lỗi ở phía server. Bạn thử lại sau nhé.', 500, 'server_error');
}
