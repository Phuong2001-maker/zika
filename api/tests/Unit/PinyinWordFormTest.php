<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Core\Pinyin;

final class PinyinWordFormTest extends TestCase
{
    public function testNhanTuThuongVaDangKhung(): void
    {
        self::assertTrue(Pinyin::isWordForm('你好'));
        self::assertTrue(Pinyin::isWordForm('太…了'));
        self::assertTrue(Pinyin::isWordForm('除了…以外'));
        self::assertTrue(Pinyin::isWordForm('又…又…'));
        self::assertTrue(Pinyin::isWordForm('…似的'));
    }

    public function testTuChoiDauBaChamSaiChoVaChuKhongPhaiHan(): void
    {
        foreach (['', '…', '……', '太……了', '……太', 'abc', '你好!', '太…a'] as $sai) {
            self::assertFalse(Pinyin::isWordForm($sai), "Không được nhận: $sai");
        }
    }
}
