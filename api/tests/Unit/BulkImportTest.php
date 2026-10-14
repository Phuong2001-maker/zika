<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\ContentController;
use Zika\Controllers\Admin\DialogueController;
use Zika\Controllers\Admin\QuestionController;
use Zika\Controllers\Admin\WritingController;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Router;
use Zika\Tests\DbTestCase;

/**
 * Nhập hàng loạt từ Excel (08 §7–§12).
 * Trình duyệt đã lọc trước, nhưng server vẫn phải tự kiểm: dòng hỏng chỉ được bỏ qua kèm lý do,
 * không làm hỏng cả mẻ, và không bao giờ ghi được payload sai vào bảng questions.
 */
final class BulkImportTest extends DbTestCase
{
    private int $lessonId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->makeUser(['role' => 'admin']);
        $this->lessonId = Db::insert('lessons', [
            'level_id' => 2,
            'lesson_no' => 6,
            'title' => 'Ăn uống',
            'created_at' => '2026-10-01 08:00:00',
            'updated_at' => '2026-10-01 08:00:00',
        ]);
    }

    /** @param array<string,mixed> $body */
    private function req(array $body, array $params = []): Request
    {
        $r = new Request('POST', '/x', [], $body);
        $r->params = $params + ['id' => (string) $this->lessonId];
        $r->user = Db::one('SELECT * FROM users WHERE id = ?', [$this->userId]);
        return $r;
    }

    /* ---------------- Câu hỏi ---------------- */

    public function testBulkQuestionsKeepsGoodRowsAndReportsBadOnes(): void
    {
        $out = (new QuestionController())->bulk($this->req([
            'part' => 'bt',
            'items' => [
                ['type' => 'meaning', 'payload' => ['word' => '鸡蛋', 'ask' => 'Nghĩa là gì?', 'options' => ['trứng gà', 'sữa bò'], 'answer' => 0], 'score' => 2],
                // Thiếu đáp án đúng → bỏ qua, báo đúng dòng.
                ['type' => 'meaning', 'payload' => ['word' => '牛奶', 'options' => ['sữa', 'trứng'], 'answer' => 9]],
                // Dạng của phần Kiểm tra, không dùng ở Bài tập.
                ['type' => 'tone', 'payload' => ['word' => '妈', 'options' => ['1', '2'], 'answer' => 0]],
                ['type' => 'order', 'payload' => ['answer' => '我喜欢吃鸡蛋', 'vi' => 'Tôi thích ăn trứng.', 'tiles' => ['我', '喜欢', '吃', '鸡蛋']]],
            ],
        ]));

        self::assertSame(2, $out['created']);
        self::assertCount(2, $out['skipped']);
        self::assertSame(2, $out['skipped'][0]['row']);
        self::assertStringContainsString('đáp án', $out['skipped'][0]['reason']);
        self::assertSame(3, $out['skipped'][1]['row']);

        $rows = Db::all("SELECT type, score, sort_order FROM questions WHERE lesson_id = ? AND part = 'bt' ORDER BY sort_order", [$this->lessonId]);
        self::assertSame(['meaning', 'order'], array_column($rows, 'type'));
        self::assertSame([1, 2], array_map('intval', array_column($rows, 'sort_order')));
        self::assertSame(2.0, (float) $rows[0]['score']);

        // cnt_bt trên bảng lessons phải được đếm lại.
        self::assertSame(2, (int) Db::val('SELECT cnt_bt FROM lessons WHERE id = ?', [$this->lessonId]));
    }

    public function testBulkQuestionsAppendContinuesNumberingAndReplaceClearsFirst(): void
    {
        $item = ['type' => 'meaning', 'payload' => ['word' => '鸡蛋', 'options' => ['a', 'b'], 'answer' => 0]];

        (new QuestionController())->bulk($this->req(['part' => 'bt', 'items' => [$item]]));
        (new QuestionController())->bulk($this->req(['part' => 'bt', 'mode' => 'append', 'items' => [$item]]));
        self::assertSame([1, 2], array_map('intval', Db::col(
            "SELECT sort_order FROM questions WHERE lesson_id = ? AND part = 'bt' ORDER BY sort_order",
            [$this->lessonId],
        )));

        (new QuestionController())->bulk($this->req(['part' => 'bt', 'mode' => 'replace', 'items' => [$item]]));
        self::assertSame(1, (int) Db::val("SELECT COUNT(*) FROM questions WHERE lesson_id = ? AND part = 'bt'", [$this->lessonId]));
    }

    public function testBulkQuestionsDoesNotTouchTheOtherPart(): void
    {
        $item = ['type' => 'meaning', 'payload' => ['word' => '鸡蛋', 'options' => ['a', 'b'], 'answer' => 0]];
        (new QuestionController())->bulk($this->req(['part' => 'kt', 'items' => [$item]]));
        (new QuestionController())->bulk($this->req(['part' => 'bt', 'mode' => 'replace', 'items' => [$item]]));

        self::assertSame(1, (int) Db::val("SELECT COUNT(*) FROM questions WHERE lesson_id = ? AND part = 'kt'", [$this->lessonId]));
    }

    public function testBulkQuestionsRefusesOversizedBatch(): void
    {
        $this->expectException(HttpError::class);
        (new QuestionController())->bulk($this->req([
            'part' => 'bt',
            'items' => array_fill(0, 501, ['type' => 'meaning', 'payload' => ['word' => '鸡', 'options' => ['a', 'b'], 'answer' => 0]]),
        ]));
    }

    /* ---------------- Từ vựng ---------------- */

    public function testBulkWordsSavesExamplesAndSkipsDuplicates(): void
    {
        $out = (new ContentController())->bulkWords($this->req([
            'levelId' => 2,
            'lessonId' => $this->lessonId,
            'items' => [
                [
                    'hanzi' => '鸡蛋', 'pinyin' => 'jīdàn', 'hanViet' => 'Kê đản', 'meaning' => 'trứng gà', 'pos' => 'n',
                    'examples' => [
                        ['zh' => '我吃鸡蛋。', 'pinyin' => 'Wǒ chī jīdàn.', 'vi' => 'Tôi ăn trứng.'],
                        // Không chứa từ → highlight lùi về chính từ đó, câu vẫn được giữ.
                        ['zh' => '他喝牛奶。', 'pinyin' => '', 'vi' => ''],
                    ],
                ],
                ['hanzi' => '鸡蛋', 'meaning' => 'trứng'],
                ['hanzi' => 'abc', 'meaning' => 'sai'],
            ],
        ]));

        self::assertSame(1, $out['created']);
        self::assertCount(2, $out['skipped']);

        $wordId = (int) Db::val('SELECT id FROM words WHERE hanzi = ?', ['鸡蛋']);
        $ex = Db::all('SELECT zh, highlight FROM word_examples WHERE word_id = ? ORDER BY sort_order', [$wordId]);
        self::assertCount(2, $ex);
        self::assertSame('我吃鸡蛋。', $ex[0]['zh']);
        self::assertNull($ex[0]['highlight']);
        self::assertSame(1, (int) Db::val('SELECT cnt_words FROM lessons WHERE id = ?', [$this->lessonId]));
    }

    public function testBulkWordsReplaceOnlyClearsTheChosenLesson(): void
    {
        $other = Db::insert('lessons', [
            'level_id' => 2, 'lesson_no' => 7, 'title' => 'Bài khác',
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00',
        ]);
        Db::insert('words', ['level_id' => 2, 'lesson_id' => $other, 'hanzi' => '牛奶', 'pinyin' => '', 'meaning_vi' => 'sữa']);
        Db::insert('words', ['level_id' => 2, 'lesson_id' => $this->lessonId, 'hanzi' => '面包', 'pinyin' => '', 'meaning_vi' => 'bánh mì']);

        (new ContentController())->bulkWords($this->req([
            'levelId' => 2,
            'lessonId' => $this->lessonId,
            'mode' => 'replace',
            'items' => [['hanzi' => '鸡蛋', 'meaning' => 'trứng gà']],
        ]));

        self::assertSame(['鸡蛋'], array_map('strval', Db::col('SELECT hanzi FROM words WHERE lesson_id = ?', [$this->lessonId])));
        self::assertSame(['牛奶'], array_map('strval', Db::col('SELECT hanzi FROM words WHERE lesson_id = ?', [$other])));
    }

    /* ---------------- Luyện viết ---------------- */

    public function testBulkCharsAddsVisibleCharsAndFillsOnlyEmptyFields(): void
    {
        // Chữ có sẵn trong kho (đã có pinyin) → không gọi mạng tải nét, và không bị ghi đè.
        Db::insert('characters', ['hanzi' => '鸡', 'pinyin' => 'jī', 'han_viet' => '', 'meaning_vi' => '']);
        Db::insert('characters', ['hanzi' => '蛋', 'pinyin' => 'dàn', 'han_viet' => 'ĐẢN', 'meaning_vi' => '']);

        $res = (new WritingController())->bulk($this->req([
            'items' => [
                ['hanzi' => '鸡', 'pinyin' => 'XX', 'hanViet' => 'KÊ', 'meaning' => 'con gà', 'structure' => 'Trái phải', 'visible' => true],
                ['hanzi' => '蛋', 'visible' => false],
                ['hanzi' => '鸡', 'visible' => true],
                ['hanzi' => 'x', 'visible' => true],
            ],
        ]));

        self::assertInstanceOf(Response::class, $res);
        $out = json_decode($res->body, true)['data'];
        self::assertSame(2, $out['created']);
        self::assertCount(2, $out['skipped']);

        $c = Db::one('SELECT pinyin, han_viet, meaning_vi, structure FROM characters WHERE hanzi = ?', ['鸡']);
        self::assertSame('jī', $c['pinyin'], 'pinyin đã có thì không ghi đè');
        self::assertSame('KÊ', $c['han_viet']);
        self::assertSame('Trái phải', $c['structure']);

        // cnt_lv chỉ đếm chữ đang hiện.
        self::assertSame(1, (int) Db::val('SELECT cnt_lv FROM lessons WHERE id = ?', [$this->lessonId]));
        self::assertSame(2, (int) Db::val('SELECT COUNT(*) FROM lesson_chars WHERE lesson_id = ?', [$this->lessonId]));
    }

    /* ---------------- Ngữ pháp ---------------- */

    public function testBulkGrammarStoresBlocksExamplesAndPractice(): void
    {
        $out = (new DialogueController())->bulkGrammar($this->req([
            'items' => [
                [
                    'title' => '喜欢 + danh từ',
                    'subtitle' => 'Sở thích',
                    'explain' => 'Dùng để nói thích gì.',
                    'blocks' => [['label' => 'Chủ ngữ', 'hz' => '我', 'color' => '#DCEBFF']],
                    'examples' => [['kind' => 'Khẳng định', 'zh' => '我喜欢鸡蛋。', 'highlight' => '喜欢', 'pinyin' => '', 'vi' => 'Tôi thích trứng.']],
                    'practice' => ['answer' => '我喜欢鸡蛋', 'vi' => 'Tôi thích trứng gà.', 'tiles' => ['我', '喜欢', '鸡蛋']],
                ],
                // Thiếu Giải thích → bỏ qua kèm lý do.
                ['title' => 'Thiếu phần giải thích', 'explain' => ''],
                // Ô chữ ghép lại không ra câu đúng.
                ['title' => 'Sai ô chữ', 'explain' => 'x', 'practice' => ['answer' => '我喜欢', 'tiles' => ['我', '喝']]],
            ],
        ]));

        self::assertSame(1, $out['created']);
        self::assertCount(2, $out['skipped']);
        self::assertSame(2, $out['skipped'][0]['row']);

        $g = Db::one('SELECT * FROM grammar_points WHERE lesson_id = ?', [$this->lessonId]);
        self::assertSame('喜欢 + danh từ', $g['title']);
        self::assertStringContainsString('Chủ ngữ', (string) $g['blocks']);
        self::assertStringContainsString('我喜欢鸡蛋。', (string) $g['examples']);
        self::assertStringContainsString('我喜欢鸡蛋', (string) $g['practice']);
        self::assertSame(1, (int) Db::val('SELECT cnt_np FROM lessons WHERE id = ?', [$this->lessonId]));
    }

    /* ---------------- Định tuyến ---------------- */

    public function testBulkRoutesAreRegisteredForEditors(): void
    {
        foreach ([
            '/admin/words/bulk',
            '/admin/lessons/5/questions/bulk',
            '/admin/lessons/5/writing/bulk',
            '/admin/lessons/5/grammar/bulk',
        ] as $path) {
            $m = Router::match('POST', $path);
            self::assertSame('E', $m['auth'], $path . ' phải là quyền E');
        }

        // Không được nuốt mất các route cũ cùng tiền tố.
        self::assertSame('createLesson', Router::match('POST', '/admin/levels/2/lessons')['handler'][1]);
        self::assertSame('create', Router::match('POST', '/admin/lessons/5/questions')['handler'][1]);
        self::assertSame('addChar', Router::match('POST', '/admin/lessons/5/writing/chars')['handler'][1]);
        self::assertSame('createGrammar', Router::match('POST', '/admin/lessons/5/grammar')['handler'][1]);
    }
}
