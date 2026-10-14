<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Core\Config;
use Zika\Core\FileCache;
use Zika\Services\ContentBuilder;

/**
 * Cache nội dung học không có hạn dùng: file còn là người học còn thấy bản cũ. Vì vậy khoá lúc ghi
 * (LearnController đặt theo slug route) và khoá lúc xoá (ContentBuilder::PART_SLUGS) phải trùng nhau.
 */
final class ContentCacheTest extends TestCase
{
    public function testSlugTrongRouteTrungVoiDanhSachDungDeXoaCache(): void
    {
        $slugs = null;
        foreach (require ZIKA_APP_DIR . '/src/routes.php' as $route) {
            if (preg_match('~^/learn/lessons/\{id\}/\{part:([a-z|]+)\}$~', (string) $route[1], $m) === 1) {
                $slugs = explode('|', $m[1]);
            }
        }

        self::assertNotNull($slugs, 'Không tìm thấy route /learn/lessons/{id}/{part}.');
        sort($slugs);
        $dungDeXoa = ContentBuilder::PART_SLUGS;
        sort($dungDeXoa);
        self::assertSame($slugs, $dungDeXoa, 'Lệch tên là admin sửa nội dung xong mà người học vẫn thấy bản cũ.');
    }

    public function testForgetXoaDungFileCacheCuaPhan(): void
    {
        $key = 'x9901-writing';
        FileCache::content($key, static fn (): array => ['chars' => []]);
        $file = Config::storageDir('cache/content') . '/' . $key . '.json';
        self::assertFileExists($file);

        FileCache::forget('content', $key);
        self::assertFileDoesNotExist($file);
    }
}
