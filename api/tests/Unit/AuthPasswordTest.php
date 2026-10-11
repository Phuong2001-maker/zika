<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Core\Auth;

final class AuthPasswordTest extends TestCase
{
    public function testPasswordRules(): void
    {
        self::assertNull(Auth::passwordProblem('matkhau123'));
        self::assertNull(Auth::passwordProblem('Đường123x'));
        self::assertSame('Mật khẩu cần ít nhất 8 ký tự, có cả chữ và số.', Auth::passwordProblem('abc123'));
        self::assertSame('Mật khẩu cần ít nhất 8 ký tự, có cả chữ và số.', Auth::passwordProblem('chiconchu'));
        self::assertSame('Mật khẩu cần ít nhất 8 ký tự, có cả chữ và số.', Auth::passwordProblem('12345678'));
        self::assertSame('Mật khẩu dài tối đa 72 ký tự.', Auth::passwordProblem(str_repeat('a1', 40)));
    }

    public function testHashIsNotPlainAndVerifies(): void
    {
        $hash = Auth::hashPassword('matkhau123');
        self::assertNotSame('matkhau123', $hash);
        self::assertTrue(password_verify('matkhau123', $hash));
        self::assertFalse(password_verify('matkhau124', $hash));
    }
}
