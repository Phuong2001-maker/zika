<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Services\ContentBuilder;
use Zika\Services\Curricula;

/**
 * Nội dung học (04 §4): đọc từ cache file, có ETag → 304.
 * Xem trước (?preview=1, chỉ E/A): dựng thẳng từ DB, Cache-Control: no-store.
 */
final class LearnController
{
    /**
     * Phần học → hàm dựng cho 1 bài. Các phần khác Lật thẻ được bổ sung ở Giai đoạn 2.
     * @var array<string,callable(int,bool):array<string,mixed>>
     */
    private static array $lessonBuilders = [];

    /** @var array<string,callable(int,bool,int):array<string,mixed>> */
    private static array $reviewBuilders = [];

    public static function registerLesson(string $slug, callable $fn): void
    {
        self::$lessonBuilders[$slug] = $fn;
    }

    public static function registerReview(string $part, callable $fn): void
    {
        self::$reviewBuilders[$part] = $fn;
    }

    /** GET /learn/levels */
    public function levels(Request $r): Response
    {
        $c = Curricula::forRequest($r);
        return $this->serve($r, "levels-c{$c}", static fn (bool $p) => ContentBuilder::levels($p, $c));
    }

    /** GET /learn/levels/{id}/lessons */
    public function levelLessons(Request $r): Response
    {
        $id = $r->id();
        if ($r->user === null && $id !== 1) {
            throw new HttpError(401, 'UNAUTHENTICATED', 'Đăng nhập để học nội dung này.');
        }
        $c = Curricula::forRequest($r);
        return $this->serve($r, "l{$id}-c{$c}-lessons", static fn (bool $p) => ContentBuilder::levelLessons($id, $p, $c));
    }

    /** GET /learn/lessons/{id}/{part:flashcards|exercises|test|writing|dialogue|grammar} */
    public function lesson(Request $r): Response
    {
        $id = $r->id();
        $slug = $r->param('part');
        self::boot();
        $fn = self::$lessonBuilders[$slug] ?? throw HttpError::notFound();
        if ($r->user === null) {
            // Khách học thử trọn cấp 1 của giáo trình mặc định ở cả 6 phần (Kiểm tra dùng lượt khách, TestService::startKhach).
            $lesson = Db::one('SELECT curriculum_id, level_id FROM lessons WHERE id = ?', [$id]);
            if ($lesson === null
                || (int) $lesson['curriculum_id'] !== Curricula::defaultId()
                || (int) $lesson['level_id'] !== 1) {
                throw new HttpError(401, 'UNAUTHENTICATED', 'Đăng nhập để học từ ' . Curricula::levelLabel(null) . ' 2.');
            }
        }
        // Biên tập và quản trị được mở bài của mọi giáo trình; cache theo bài nên phải chặn trước khi đọc cache.
        if ($r->user !== null && !$r->isStaff()) {
            Curricula::assertLesson($id, Curricula::ofUser($r->userId()));
        }
        return $this->serve($r, "x{$id}-{$slug}", static fn (bool $p) => $fn($id, $p));
    }

    /** GET /learn/levels/{id}/review/{part} */
    public function review(Request $r): Response
    {
        if ($r->user === null) {
            throw new HttpError(401, 'UNAUTHENTICATED', 'Đăng nhập để học bài tổng hợp.');
        }
        $id = $r->id();
        $part = $r->param('part');
        self::boot();
        $fn = self::$reviewBuilders[$part] ?? throw HttpError::notFound();
        $c = Curricula::forRequest($r);
        return $this->serve($r, "l{$id}-c{$c}-review-{$part}", static fn (bool $p) => $fn($id, $p, $c));
    }

    /** Đăng ký các hàm dựng nội dung (một lần cho mỗi request). */
    private static function boot(): void
    {
        if (self::$lessonBuilders !== []) {
            return;
        }
        self::registerLesson('flashcards', [ContentBuilder::class, 'flashcards']);
        self::registerReview('lt', [ContentBuilder::class, 'reviewLt']);
        if (class_exists(\Zika\Services\PartContent::class)) {
            \Zika\Services\PartContent::register();
        }
    }

    /** @param callable(bool):mixed $build */
    private function serve(Request $r, string $key, callable $build): Response
    {
        $preview = $r->isPreview();
        $cached = ContentBuilder::cached($key, $preview, static fn () => $build(false));
        if ($cached === null) {
            return Response::json($build(true))->withHeader('Cache-Control', 'no-store');
        }
        $etag = $cached['etag'];
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache'];
        $inm = $r->header('if-none-match');
        if ($inm !== '' && in_array($etag, array_map('trim', explode(',', $inm)), true)) {
            return new Response(304, '', $headers);
        }
        $res = Response::rawJson($cached['json']);
        foreach ($headers as $k => $v) {
            $res->withHeader($k, $v);
        }
        return $res;
    }
}
