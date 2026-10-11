import { describe, expect, it } from 'vitest';
import { isAdminRoute, isGuestOnly, isPublicRoute, loginUrl, safeNext } from './routes';
import { emailProblem, nameProblem, passwordProblem } from '$lib/utils/validate';

describe('phân loại route', () => {
  it('route công khai và chỉ dành cho khách', () => {
    expect(isPublicRoute('/login')).toBe(true);
    expect(isPublicRoute('/pages/privacy')).toBe(true);
    expect(isPublicRoute('/invite')).toBe(true);
    expect(isPublicRoute('/learn/flashcards')).toBe(false);
    expect(isPublicRoute('/loginx')).toBe(false);
    expect(isGuestOnly('/forgot')).toBe(true);
    expect(isGuestOnly('/reset')).toBe(false);
    expect(isAdminRoute('/admin/users/3')).toBe(true);
    expect(isAdminRoute('/administrator')).toBe(false);
  });

  it('next chỉ nhận đường dẫn nội bộ (chống chuyển hướng mở)', () => {
    expect(safeNext('/learn/tests/hsk/2')).toBe('/learn/tests/hsk/2');
    expect(safeNext('//evil.com')).toBe('/learn/flashcards');
    expect(safeNext('https://evil.com')).toBe('/learn/flashcards');
    expect(safeNext('/\\evil.com')).toBe('/learn/flashcards');
    expect(safeNext(null)).toBe('/learn/flashcards');
    expect(loginUrl('/learn/flashcards/hsk/2?lesson=6')).toBe('/login?next=%2Flearn%2Fflashcards%2Fhsk%2F2%3Flesson%3D6');
  });
});

describe('kiểm tra form ở máy', () => {
  it('email, mật khẩu, tên', () => {
    expect(emailProblem('')).toBe('Hãy nhập Email.');
    expect(emailProblem('abc')).toBe('Email không hợp lệ.');
    expect(emailProblem('ban@email.com')).toBeNull();
    expect(passwordProblem('abc12345')).toBeNull();
    expect(passwordProblem('abcdefgh')).not.toBeNull();
    expect(passwordProblem('1234567')).not.toBeNull();
    expect(nameProblem('A')).toBe('Tên hiển thị cần ít nhất 2 ký tự.');
    expect(nameProblem('Minh Anh')).toBeNull();
  });
});
