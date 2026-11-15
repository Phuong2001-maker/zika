<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use Zika\Controllers\Admin\WritingController;
use Zika\Core\Db;
use Zika\Core\Request;
use Zika\Services\PartContent;
use Zika\Tests\DbTestCase;

/** Màn Luyện viết mới (15/11/2026): Cách nhớ + Từ ghép thay cho bộ thủ / phân tích / chữ cùng bộ. */
final class LuyenVietCachNhoTest extends DbTestCase
{
    private int $lessonId;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->makeUser(['role' => 'admin']);
        $this->lessonId = Db::insert('lessons', [
            'level_id' => 1, 'lesson_no' => 4, 'title' => 'Bạn đi đâu đấy', 'status' => 'published',
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00',
        ]);
        $jin = Db::insert('characters', ['hanzi' => '今', 'pinyin' => 'jīn', 'han_viet' => 'KIM', 'meaning_vi' => 'nay, bây giờ', 'analysis' => 'Phân tích cũ.']);
        Db::insert('lesson_chars', ['lesson_id' => $this->lessonId, 'char_id' => $jin, 'sort_order' => 1, 'is_visible' => 1]);
        Db::insert('words', ['level_id' => 1, 'lesson_id' => $this->lessonId, 'hanzi' => '今天', 'pinyin' => 'jīntiān', 'meaning_vi' => 'hôm nay; ngày nay', 'pos' => 'n']);
    }

    /** @param array<string,mixed> $body */
    private function req(array $body): Request
    {
        $r = new Request('PUT', '/x', [], $body);
        $r->user = Db::one('SELECT * FROM users WHERE id = ?', [$this->adminId]);
        return $r;
    }

    public function testChuChuaSoanVanCoTuGhepTuBangTuVaCauNhoCu(): void
    {
        $c = PartContent::writing($this->lessonId, true)['chars'][0];
        self::assertSame([], $c['cachNho']['phan']);
        self::assertSame('Phân tích cũ.', $c['cachNho']['lienTuong']);
        self::assertSame([['hanzi' => '今天', 'pinyin' => 'jīntiān', 'nghia' => 'Hôm nay']], $c['tuGhep']);
    }

    public function testNapCachNhoRoiManHocTraVeDungNhuDaSoan(): void
    {
        $out = (new WritingController())->cachNho($this->req(['items' => [[
            'hanzi' => '今',
            'phan' => [
                ['mau' => 'a', 'vi_tri' => 'trên', 'chu' => '人', 'ten' => 'Bộ Nhân', 'nghia' => 'người'],
                ['mau' => 'b', 'vi_tri' => 'dưới', 'chu' => '丶㇇', 'ten' => 'một chấm + một nét gập', 'nghia' => 'như cây kim'],
                ['mau' => 'x', 'vi_tri' => 'thừa', 'chu' => '?', 'ten' => '?', 'nghia' => '?'],
            ],
            'lienTuong' => 'Âm Hán Việt là KIM → cây kim đồng hồ.',
            'tuGhep' => [
                ['hanzi' => '今年', 'pinyin' => 'jīnnián', 'nghia' => 'Năm nay'],
                ['hanzi' => '今', 'pinyin' => 'jīn', 'nghia' => 'chính chữ, phải bỏ'],
                ['hanzi' => '明天', 'pinyin' => 'míngtiān', 'nghia' => 'không chứa chữ, phải bỏ'],
            ],
        ], ['hanzi' => '今', 'lienTuong' => '']]]));
        self::assertSame(1, $out['updated']);
        self::assertCount(1, $out['skipped']);

        $c = PartContent::writing($this->lessonId, true)['chars'][0];
        self::assertSame(['a', 'b'], array_column($c['cachNho']['phan'], 'mau'));
        self::assertSame('Âm Hán Việt là KIM → cây kim đồng hồ.', $c['cachNho']['lienTuong']);
        self::assertSame(['今年'], array_column($c['tuGhep'], 'hanzi'));
    }
}
