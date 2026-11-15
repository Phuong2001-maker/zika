<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Services\PinyinDe;
use Zika\Services\QuestionGrader;
use Zika\Services\QuestionPayload;
use Zika\Tests\DbTestCase;

/**
 * Đề mô phỏng HSK 3.0 chép từ sách (15/11/2026): tranh là ảnh cắt từ đề, nhóm câu A–F dùng chung (6 phương án),
 * câu viết chữ Hán theo pinyin (`go`), và pinyin in trên chữ ở đề cấp 1–2 (migration 030).
 */
final class DeMoPhongTest extends DbTestCase
{
    public function testChonTranhNhanAnhTaiLenVaSauPhuongAn(): void
    {
        $anh = array_map(static fn (string $c): string => "/uploads/hinh-de/hsk2-mp1/n06-$c.jpg", ['a', 'b', 'c', 'd', 'e', 'f']);
        $out = QuestionPayload::clean('pic', ['audioText' => '男：你喜欢什么运动？', 'options' => $anh, 'answer' => 5]);
        self::assertSame($anh, $out['options']);
        self::assertSame('Tranh F', QuestionGrader::rightAnswerText('pic', $out));
    }

    public function testChonTranhVanTuChoiDuongDanNgoai(): void
    {
        $this->expectException(HttpError::class);
        QuestionPayload::clean('pic', ['ask' => '我喝茶。', 'options' => ['https://x.com/a.jpg', 'cha'], 'answer' => 0]);
    }

    public function testChonDapAnSauPhuongAnVaDoanDai(): void
    {
        $doan = str_repeat('我每天早上七点起床，然后去学校。', 15) . "\n★ 问：他几点起床？";
        self::assertGreaterThan(200, mb_strlen($doan));
        $out = QuestionPayload::clean('pick', ['ask' => $doan, 'options' => ['A', 'B', 'C', 'D', 'E', 'F'], 'answer' => 4]);
        self::assertCount(6, $out['options']);
        self::assertSame($doan, $out['ask']);
    }

    public function testVietChuHanTheoPinyin(): void
    {
        $out = QuestionPayload::clean('go', ['sentence' => '你想喝___还是喝咖啡？', 'pinyin' => 'chá', 'answer' => '茶']);
        self::assertTrue(QuestionGrader::check('go', $out, ' 茶 '));
        self::assertFalse(QuestionGrader::check('go', $out, '查'));
        self::assertFalse(QuestionGrader::check('go', $out, ''));
        self::assertTrue(QuestionGrader::answered('go', $out, '茶'));
        self::assertFalse(QuestionGrader::answered('go', $out, ' '));
        self::assertSame('茶', QuestionGrader::rightAnswerText('go', $out));
        self::assertArrayNotHasKey('answer', QuestionGrader::hideAnswer($out), 'Đề gửi ra trình duyệt không được lộ chữ đúng');
        self::assertContains('go', QuestionGrader::HSK30_TYPES);
    }

    public function testVietChuHanBaoLoiKhiDapAnKhongPhaiChuHan(): void
    {
        $this->expectException(HttpError::class);
        QuestionPayload::clean('go', ['sentence' => '你想喝___吗？', 'pinyin' => 'chá', 'answer' => 'cha']);
    }

    public function testPinyinChiTraVeChoDeCap1Va2VaKhopDungChuoi(): void
    {
        $kq = PinyinDe::luu([
            ['text' => '我喝茶。', 'tokens' => [['我', 'Wǒ'], ['喝', 'hē'], ['茶', 'chá'], ['。', '']]],
            ['text' => '你好', 'tokens' => [['你', 'nǐ']]],
        ]);
        self::assertSame(1, $kq['saved']);
        self::assertCount(1, $kq['skipped'], 'Mảnh pinyin không ghép ra đúng câu thì bỏ');

        $cau = [['type' => 'pic', 'payload' => ['ask' => '我喝茶。', 'options' => ['cha', 'ca-phe']]]];
        self::assertSame(['我喝茶。' => [['我', 'Wǒ'], ['喝', 'hē'], ['茶', 'chá'], ['。', '']]], PinyinDe::choDe($cau, 2));
        self::assertSame([], PinyinDe::choDe($cau, 3), 'Đề giấy HSK 3 trở lên không in pinyin');

        // Admin sửa câu → chuỗi khác → không còn pinyin cũ.
        $sua = [['type' => 'pic', 'payload' => ['ask' => '我喝水。', 'options' => ['cha']]]];
        self::assertSame([], PinyinDe::choDe($sua, 1));
        self::assertSame(1, (int) Db::val('SELECT COUNT(*) FROM text_pinyin'));
    }

    public function testChuoiCanPinyinTachCauDienChoTrongVaBoLoiNghe(): void
    {
        self::assertSame(['今天', '冷。', '很', '太'], PinyinDe::chuoi('blank', ['sentence' => '今天___冷。', 'options' => ['很', '太']]));
        self::assertSame(['对'], PinyinDe::chuoi('listen', ['audioText' => '他在睡觉。', 'options' => ['对', 'Sai']]));
    }
}
