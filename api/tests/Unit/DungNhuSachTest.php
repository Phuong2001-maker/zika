<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\ContentController;
use Zika\Controllers\Admin\DialogueController;
use Zika\Controllers\Admin\WritingController;
use Zika\Core\Db;
use Zika\Core\Request;
use Zika\Services\ContentBuilder;
use Zika\Services\PartContent;
use Zika\Tests\DbTestCase;

/**
 * Giáo trình theo sách in (Hán ngữ 6 tập): web phải hiện đủ và đúng như sách, kể cả phần tiếng Anh sách in.
 * Trước đây nhiều chỗ bị API cắt hoặc bỏ âm thầm nên dữ liệu nạp đúng mà web vẫn thiếu.
 */
final class DungNhuSachTest extends DbTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->makeUser(['role' => 'admin']);
    }

    private function bai(int $no): int
    {
        return Db::insert('lessons', ['level_id' => 1, 'lesson_no' => $no, 'title' => "Bài $no", 'status' => 'published']);
    }

    /** @param array<string,mixed> $body */
    private function req(array $body, int $id = 0): Request
    {
        $r = new Request('POST', '/x', [], $body);
        $r->params = ['id' => (string) $id];
        $r->user = Db::one('SELECT * FROM users WHERE id = ?', [$this->userId]);
        return $r;
    }

    public function testSachInLaiMotTuOBaiSauVaHaiCachDocTrongMotBai(): void
    {
        $b8 = $this->bai(8);
        $b10 = $this->bai(10);
        $nap = fn (int $bai, array $items) => (new ContentController())->bulkWords($this->req(['levelId' => 1, 'lessonId' => $bai, 'mode' => 'replace', 'items' => $items]));

        self::assertSame(1, $nap($b8, [['hanzi' => '找', 'pinyin' => 'zhǎo', 'meaning' => 'tìm']])['created']);
        // Bài sau in lại 找: trước đây bị bỏ vì "cấp này đã có từ đó".
        self::assertSame(1, $nap($b10, [['hanzi' => '找', 'pinyin' => 'zhǎo', 'meaning' => 'thối (tiền)', 'meaningEn' => 'to give change']])['created']);

        // Một bài in 好 hai lần với hai cách đọc: giữ cả hai; lặp đúng cùng cách đọc thì mới là trùng.
        $out = $nap($b10, [
            ['hanzi' => '好', 'pinyin' => 'hào', 'meaning' => 'thích', 'meaningEn' => 'to like'],
            ['hanzi' => '好', 'pinyin' => 'hǎo', 'meaning' => 'dễ', 'meaningEn' => 'easy'],
            ['hanzi' => '好', 'pinyin' => 'hǎo', 'meaning' => 'lặp'],
        ]);
        self::assertSame(2, $out['created']);
        self::assertCount(1, $out['skipped']);

        $the = ContentBuilder::flashcards($b10, true)['words'];
        self::assertSame(['hào', 'hǎo'], array_column($the, 'pinyin'));
        self::assertSame(['to like', 'easy'], array_column($the, 'meaningEn'));
    }

    public function testSuaTuMaKhongGuiTiengAnhThiGiuNguyen(): void
    {
        $b = $this->bai(1);
        (new ContentController())->bulkWords($this->req(['levelId' => 1, 'lessonId' => $b, 'items' => [
            ['hanzi' => '国', 'pinyin' => 'guó', 'meaning' => 'nước', 'meaningEn' => 'country; nation'],
        ]]));
        $id = (int) Db::val('SELECT id FROM words WHERE lesson_id = ?', [$b]);

        (new ContentController())->updateWord($this->req(['levelId' => 1, 'lessonId' => $b, 'hanzi' => '国', 'pinyin' => 'guó', 'meaning' => 'quốc gia'], $id));
        self::assertSame('country; nation', Db::val('SELECT meaning_en FROM words WHERE id = ?', [$id]));

        (new ContentController())->updateWord($this->req(['levelId' => 1, 'lessonId' => $b, 'hanzi' => '国', 'pinyin' => 'guó', 'meaning' => 'quốc gia', 'meaningEn' => ''], $id));
        self::assertNull(Db::val('SELECT meaning_en FROM words WHERE id = ?', [$id]));
    }

    public function testNguPhapGiuDuCongThucViDuVaTiengAnh(): void
    {
        $b = $this->bai(2);
        $vd = array_map(static fn (int $i): array => ['kind' => 'Cách gọi của ngày', 'kindEn' => 'How to say the dates', 'zh' => "{$i}日（号）"], range(1, 31));
        $vd[0]['en'] = 'the first day';
        (new DialogueController())->bulkGrammar($this->req(['items' => [[
            'title' => str_repeat('年、月、日 — Ngày, tháng, năm ', 6),
            'titleEn' => 'Year, month and date',
            'subtitle' => '请问 + ，……？',
            'explain' => 'Cách đọc năm.',
            'explainEn' => 'How to read the year.',
            'blocks' => [['label' => '', 'hz' => '请问', 'color' => '#DCEBFF'], ['label' => '+', 'hz' => '，……？', 'color' => '#CFF5E1']],
            'examples' => $vd,
        ]]], $b));

        $p = PartContent::grammar($b, true)['points'][0];
        self::assertSame(['请问', '，……？'], array_column($p['blocks'], 'hz'), 'ô chỉ có chữ Hán không bị bỏ');
        self::assertCount(31, $p['examples'], 'không cắt ở 6 ví dụ');
        self::assertSame('the first day', $p['examples'][0]['en']);
        self::assertSame('How to say the dates', $p['examples'][30]['kindEn']);
        self::assertArrayNotHasKey('en', $p['examples'][1]);
        self::assertGreaterThan(120, mb_strlen($p['title']), 'tên mục dài không bị cắt ở 120 ký tự');
        self::assertSame('Year, month and date', $p['titleEn']);
        self::assertSame('How to read the year.', $p['explainEn']);
        self::assertNull($p['noteEn']);

        // Màn soạn chưa có ô tiếng Anh: lưu lại không được xoá phần tiếng Anh đã nạp.
        $id = (int) Db::val('SELECT id FROM grammar_points WHERE lesson_id = ?', [$b]);
        (new DialogueController())->updateGrammar($this->req(['title' => 'Ngày tháng', 'explain' => 'Cách đọc năm.'], $id));
        self::assertSame('How to read the year.', Db::val('SELECT explain_en FROM grammar_points WHERE id = ?', [$id]));
    }

    public function testLuyenVietHienCachDocSachInCuaTungBai(): void
    {
        Db::insert('characters', ['hanzi' => '行', 'pinyin' => 'xíng', 'han_viet' => 'HÀNH', 'meaning_vi' => 'đi']);
        $b = $this->bai(3);
        (new WritingController())->bulk($this->req(['items' => [['hanzi' => '行', 'pinyin' => 'háng'], ['hanzi' => '行']]], $b));

        $chu = PartContent::writing($b, true)['chars'];
        self::assertSame('háng', $chu[0]['pinyin']);
        self::assertSame(2, $chu[0]['tone']);
        self::assertSame('xíng', Db::val('SELECT pinyin FROM characters WHERE hanzi = ?', ['行']), 'không đổi cách đọc chung của chữ');

        $khac = $this->bai(4);
        (new WritingController())->bulk($this->req(['items' => [['hanzi' => '行']]], $khac));
        self::assertSame('xíng', PartContent::writing($khac, true)['chars'][0]['pinyin'], 'bài khác không có cách đọc riêng thì dùng cách đọc chung');
    }
}
