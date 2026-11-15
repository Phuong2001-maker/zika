<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Core\HttpError;
use Zika\Services\QuestionGrader;
use Zika\Services\QuestionPayload;

/**
 * Bài tập Hán ngữ lấy đúng như phần 练习 của sách (15/11/2026): khung từ đủ 8–15 từ, chọn vị trí A–D ở Bài tập,
 * bài đọc hiểu đúng/sai dài, 连句成段 xếp cả câu.
 */
final class BaiTapSachTest extends TestCase
{
    public function testDienChoTrongGiuNguyenKhungTuCuaSach(): void
    {
        $khung = ['终于', '充裕', '从来', '一向', '尽管', '何况', '难免', '连忙', '居然', '赶紧', '格外', '顺便', '至今', '再三', '未必'];
        $out = QuestionPayload::clean('blank', ['sentence' => '他___来了。', 'options' => $khung, 'answer' => 14]);
        self::assertSame($khung, $out['options'], 'Không được cắt khung từ còn 4 phương án');
        self::assertSame(14, $out['answer']);
    }

    public function testCacDangTracNghiemKhacVanToiDa4PhuongAn(): void
    {
        // Chọn đáp án / chọn tranh / nghe chọn được tới 6 (nhóm A–F của đề mô phỏng); chọn nghĩa, chọn câu tiếng Trung vẫn 4.
        $out = QuestionPayload::clean('toZh', ['vi' => 'Q', 'options' => ['甲', '乙', '丙', '丁', '戊'], 'answer' => 0]);
        self::assertCount(4, $out['options']);
    }

    public function testBaiTapNhanDangChonDapAn(): void
    {
        self::assertContains('pick', QuestionGrader::BT_TYPES);
        $out = QuestionPayload::clean('pick', ['ask' => '（了）我A已经B学C两年D汉语。', 'options' => ['A', 'B', 'C', 'D'], 'answer' => 2]);
        self::assertSame(2, $out['answer']);
        self::assertTrue(QuestionGrader::check('pick', $out, 2));
    }

    public function testDungSaiNhanBaiDocDai(): void
    {
        $doan = str_repeat('我们学校的图书馆很大，里边有很多中文书。', 40);
        self::assertGreaterThan(400, mb_strlen($doan));
        $out = QuestionPayload::clean('tf', ['passage' => $doan, 'statement' => '图书馆很大。', 'answer' => true]);
        self::assertSame($doan, $out['passage']);
    }

    public function testDungSaiQuaGioiHanThiBaoLoi(): void
    {
        $this->expectException(HttpError::class);
        QuestionPayload::clean('tf', ['passage' => str_repeat('字', QuestionPayload::MAX_TF_PASSAGE + 1), 'statement' => 'x', 'answer' => true]);
    }

    public function testSapXepCauThanhDoan(): void
    {
        $cau = ['他小时候家里很穷，', '可是他一直很努力，', '后来考上了北京大学，', '现在是一名医生。'];
        $out = QuestionPayload::clean('order', ['answer' => implode('', $cau), 'tiles' => $cau]);
        self::assertSame($cau, $out['tiles']);
        self::assertTrue(QuestionGrader::check('order', $out, [0, 1, 2, 3]));
        self::assertFalse(QuestionGrader::check('order', $out, [1, 0, 2, 3]));
    }

    public function testSapXepVanBaoLoiKhiOChuKhongGhepRaDapAn(): void
    {
        $this->expectException(HttpError::class);
        QuestionPayload::clean('order', ['answer' => '我是学生。', 'tiles' => ['我', '是', '老师']]);
    }
}
