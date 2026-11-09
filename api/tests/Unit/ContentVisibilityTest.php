<?php
declare(strict_types=1);
namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\ContentVisibilityController;
use Zika\Core\{Db, FileCache, HttpError, Request, Router};
use Zika\Services\{ContentBuilder, ContentVisibility, PartContent, ProgressService, TestService};
use Zika\Tests\DbTestCase;

final class ContentVisibilityTest extends DbTestCase
{
    private int $lesson;
    private int $other;
    protected function setUp(): void
    {
        parent::setUp();
        FileCache::forgetAllContent();
        Db::exec('UPDATE levels SET is_visible = 1 WHERE id IN (1,2)');
        $this->lesson = Db::insert('lessons', ['curriculum_id' => 1, 'level_id' => 1, 'lesson_no' => 1, 'title' => 'Bài một', 'status' => 'published']);
        $this->other = Db::insert('curricula', ['name' => 'Giáo trình khác']);
    }
    private function change(string $kind, bool $visible, int $level = 1, ?int $lesson = null): void
    {
        $r = new Request('PATCH', '/admin/content-visibility', body: ['kind' => $kind, 'curriculumId' => 1, 'levelId' => $level, 'lessonId' => $lesson, 'visible' => $visible]);
        $r->user = ['id' => $this->makeUser(['role' => 'admin']), 'role' => 'admin'];
        (new ContentVisibilityController())->save($r);
    }
    private function missing(callable $fn): void
    {
        try { $fn(); self::fail('Nội dung đã ẩn phải bị chặn.'); }
        catch (HttpError $e) { self::assertSame(404, $e->status); }
    }
    public function testChaConDocLapVaKhoiPhucLuaChonCu(): void
    {
        $this->change('lesson', false, lesson: $this->lesson);
        $this->change('level', false);
        $this->change('curriculum', false);
        self::assertFalse(ContentVisibility::levelVisible(1, 2));
        self::assertTrue(ContentVisibility::levelVisible($this->other, 1));
        $this->change('curriculum', true);
        self::assertFalse(ContentVisibility::levelVisible(1, 1));
        self::assertTrue(ContentVisibility::levelVisible(1, 2));
        $this->change('level', true);
        $this->missing(fn () => ContentBuilder::lesson($this->lesson, false));
        self::assertSame('draft', ContentBuilder::lesson($this->lesson, true)['status']);
        self::assertSame('A', Router::match('PATCH', '/admin/content-visibility')['auth']);
    }
    public function testAnCapChanCaSauPhanVaTongHopKhongLoBaiAn(): void
    {
        $lv = Db::one('SELECT * FROM levels WHERE id = 1');
        Db::exec('UPDATE levels SET review_published_only = 0 WHERE id = 1');
        $this->change('lesson', false, lesson: $this->lesson);
        self::assertSame([], ContentBuilder::reviewLessonIds($lv, false, 1));
        self::assertSame([$this->lesson], ContentBuilder::reviewLessonIds($lv, true, 1));
        $this->change('lesson', true, lesson: $this->lesson);
        $this->change('level', false);
        foreach ([ContentBuilder::class => ['flashcards'], PartContent::class => ['exercises', 'test', 'writing', 'dialogue', 'grammar']] as $class => $methods) {
            foreach ($methods as $method) $this->missing(fn () => $class::$method($this->lesson, false));
        }
        $this->missing(fn () => ContentBuilder::levelLessons(1, false, 1));
        $this->missing(fn () => ProgressService::publishedLesson($this->lesson));
        $levels = ContentBuilder::levels(false, 1);
        self::assertFalse($levels[0]['visible']);
        self::assertSame(0, $levels[0]['lessonCount']);
    }
    public function testChanTruocCacheVaPreviewKhongGhiTienDo(): void
    {
        FileCache::content("x{$this->lesson}-flashcards", fn () => ['old' => true]);
        $this->change('curriculum', false);
        self::assertFileDoesNotExist(\Zika\Core\Config::storageDir('cache/content') . "/x{$this->lesson}-flashcards.json");
        $r = new Request('GET', "/learn/lessons/{$this->lesson}/flashcards");
        $r->user = ['id' => $this->makeUser(), 'role' => 'learner'];
        $this->missing(fn () => ContentVisibility::enforce($r));
        self::assertSame($this->lesson, (int) ContentBuilder::lesson($this->lesson, true)['id']);
        self::assertSame(0, (int) Db::val('SELECT COUNT(*) FROM lesson_progress'));
    }

    public function testAnChaKhongCatLuotDangThiNhungChanLuotMoi(): void
    {
        Db::insert('questions', ['part' => 'kt', 'lesson_id' => $this->lesson, 'type' => 'pick', 'payload' => json_encode(['prompt' => 'Chọn chữ', 'options' => ['一', '二'], 'answer' => 0])]);
        $uid = $this->makeUser();
        $attempt = TestService::start($uid, $this->lesson, null);
        $this->change('curriculum', false);
        self::assertSame($attempt['attemptId'], TestService::start($uid, $this->lesson, null)['attemptId']);
        self::assertSame($attempt['attemptId'], TestService::summary($uid, $this->lesson, null)['openAttemptId']);
        $this->missing(fn () => TestService::start($this->makeUser(), $this->lesson, null));
        $r = new Request('POST', '/tests/start', body: ['lessonId' => $this->lesson]);
        $r->user = ['id' => $uid, 'role' => 'learner'];
        ContentVisibility::enforce($r);
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM test_attempts'));
    }
}
