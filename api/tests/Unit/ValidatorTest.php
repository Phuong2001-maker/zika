<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Core\HttpError;
use Zika\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testValidDataPasses(): void
    {
        $v = new Validator(['displayName' => '  Minh Anh ', 'email' => 'MinhAnh@Example.com', 'goal' => '15', 'lv' => 'hsk12']);
        self::assertSame('Minh Anh', $v->str('displayName', 'Tên hiển thị', min: 2, max: 60));
        self::assertSame('minhanh@example.com', $v->email('email'));
        self::assertSame(15, $v->int('goal', 'Mục tiêu', 10, 30));
        self::assertSame('hsk12', $v->in('lv', 'Trình độ', ['beginner', 'hsk12']));
        $v->done();
        self::assertSame([], $v->errors());
    }

    public function testCollectsVietnameseErrors(): void
    {
        $v = new Validator(['displayName' => 'A', 'email' => 'not-an-email', 'goal' => 'abc', 'lv' => 'x']);
        $v->str('displayName', 'Tên hiển thị', min: 2, max: 60);
        $v->email('email');
        $v->int('goal', 'Mục tiêu');
        $v->in('lv', 'Trình độ', ['beginner']);
        $v->str('missing', 'Ghi chú');
        $e = $v->errors();
        self::assertSame('Tên hiển thị cần ít nhất 2 ký tự.', $e['displayName']);
        self::assertSame('Email không hợp lệ.', $e['email']);
        self::assertSame('Mục tiêu phải là số nguyên.', $e['goal']);
        self::assertSame('Trình độ không hợp lệ.', $e['lv']);
        self::assertSame('Hãy nhập Ghi chú.', $e['missing']);

        try {
            $v->done();
            self::fail('Phải ném lỗi 422');
        } catch (HttpError $err) {
            self::assertSame(422, $err->status);
            self::assertSame('VALIDATION', $err->errorCode);
            self::assertArrayHasKey('email', $err->toArray()['error']['fields']);
        }
    }

    public function testIntRangeAndDates(): void
    {
        $v = new Validator(['n' => 99, 'd' => '2026-02-30', 'ok' => '2026-10-04']);
        $v->int('n', 'Số', 1, 60);
        self::assertNull($v->date('d', 'Ngày'));
        self::assertSame('2026-10-04', $v->date('ok', 'Ngày'));
        self::assertSame('Số phải từ 1 đến 60.', $v->errors()['n']);
        self::assertSame('Ngày không hợp lệ.', $v->errors()['d']);
    }

    public function testIdsList(): void
    {
        self::assertSame([3, 1, 2], (new Validator(['ids' => [3, '1', 2]]))->ids('ids'));
        $v = new Validator(['ids' => [1, -2]]);
        self::assertSame([], $v->ids('ids'));
        self::assertTrue($v->hasError('ids'));
    }
}
