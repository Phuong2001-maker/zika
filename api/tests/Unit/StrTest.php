<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zika\Core\Str;

final class StrTest extends TestCase
{
    /** @return list<array{string,string}> */
    public static function initialsCases(): array
    {
        // 04 §2.2: 2 chữ cái đầu của 2 từ cuối, in hoa, bỏ dấu, giữ Đ.
        return [
            ['Minh Anh', 'MA'],
            ['Phạm Đức Long', 'ĐL'],
            ['Lý Vy', 'LV'],
            ['Nguyễn Minh Anh', 'MA'],
            ['Lan', 'LA'],
            ['  ', '?'],
        ];
    }

    #[DataProvider('initialsCases')]
    public function testInitials(string $name, string $expected): void
    {
        self::assertSame($expected, Str::initials($name));
    }

    public function testDisplayFromFull(): void
    {
        self::assertSame('Minh Anh', Str::displayFromFull('Nguyễn Minh Anh'));
        self::assertSame('Bạn mới', Str::displayFromFull(''));
    }

    public function testMaskEmail(): void
    {
        self::assertSame('m•••@example.com', Str::maskEmail('minhanh@example.com'));
        self::assertSame('khongphaiemail', Str::maskEmail('khongphaiemail'));
    }

    public function testDurationVietnamese(): void
    {
        self::assertSame('3 phút', Str::duration(180));
        self::assertSame('1 giờ 10 phút', Str::duration(4200));
        self::assertSame('41 giờ 20 phút', Str::duration(148800));
        self::assertSame('2 giờ', Str::duration(7200));
        self::assertSame('0 phút', Str::duration(-5));
    }

    public function testNumberFormatVietnamese(): void
    {
        self::assertSame('1.284', Str::num(1284));
        self::assertSame('3,5', Str::num(3.5, 1));
    }

    public function testIsHan(): void
    {
        self::assertTrue(Str::isHan('资源'));
        self::assertTrue(Str::isHan('一点儿'));
        self::assertFalse(Str::isHan('资源a'));
        self::assertFalse(Str::isHan(''));
    }

    public function testTokenIsBase64Url(): void
    {
        $t = Str::token();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $t);
        self::assertNotSame($t, Str::token());
    }
}
