<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Services\ContentBuilder;

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

    /** @var array<string,callable(int,bool):array<string,mixed>> */
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
        return $this->serve($r, 'levels', static fn (bool $p) => ContentBuilder::levels($p));
    }

    /** GET /learn/levels/{id}/lessons */
    public function levelLessons(Request $r): Response
    {
        $id = $r->id();
        return $this->serve($r, "l{$id}-lessons", static fn (bool $p) => ContentBuilder::levelLessons($id, $p));
    }

    /** GET /learn/lessons/{id}/{part:flashcards|exercises|test|writing|dialogue|grammar} */
    public function lesson(Request $r): Response
    {
        $id = $r->id();
        $slug = $r->param('part');
        self::boot();
        $fn = self::$lessonBuilders[$slug] ?? throw HttpError::notFound();
        return $this->serve($r, "x{$id}-{$slug}", static fn (bool $p) => $fn($id, $p));
    }

    /** GET /learn/levels/{id}/review/{part} */
    public function review(Request $r): Response
    {
        $id = $r->id();
        $part = $r->param('part');
        self::boot();
        $fn = self::$reviewBuilders[$part] ?? throw HttpError::notFound();
        return $this->serve($r, "l{$id}-review-{$part}", static fn (bool $p) => $fn($id, $p));
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
