<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Core\Upload;

final class TtsHashTest extends TestCase
{
    public function testLegacyPathIsUnchanged(): void
    {
        self::assertSame('440ee0853ad1e99f962b63e459ef992d7c211722', Upload::ttsHash(' 你好 '));
    }

    public function testDifferentReadingsCannotOverwriteEachOther(): void
    {
        self::assertSame('298879273cfe12881e3a503a1f60cc51eb2e20e7', Upload::ttsHash('还', 'hái'));
        self::assertSame('3a544e1d1352203cd4d51b9d8f8914950e33635d', Upload::ttsHash('还', 'huán'));
        self::assertNotSame(Upload::ttsHash('头', 'tou'), Upload::ttsHash('头', 'tóu'));
        self::assertNotSame(Upload::ttsHash('道', 'dao'), Upload::ttsHash('道', 'dào'));
    }

    public function testPinyinIgnoresCaseAndWhitespace(): void
    {
        self::assertSame('9e3be89eae672e91077bc98791367617fadeec06', Upload::ttsHash('你好', " Nǐ\tHǎo\n"));
        self::assertSame(Upload::ttsHash('女', 'nǚ'), Upload::ttsHash('女', "nu\u{0308}\u{030c}"));
    }
}
