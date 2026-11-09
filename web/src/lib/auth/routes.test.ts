import { describe, expect, it } from 'vitest';
import { canView, homePath, isAdminRoute, isGuestOnly, isPublicRoute, landingPath, loginUrl, safeNext } from './routes';
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

describe('vào trang nào sau khi đăng nhập', () => {
  it('mỗi vai trò có một trang nhà', () => {
    expect(homePath('admin')).toBe('/admin');
    expect(homePath('editor')).toBe('/admin/lessons');
    expect(homePath('learner')).toBe('/learn/flashcards');
    expect(homePath(null)).toBe('/learn/flashcards');
  });

  it('học viên không mở được khu quản trị', () => {
    expect(canView('learner', '/admin/users')).toBe(false);
    expect(canView('learner', '/learn/flashcards')).toBe(true);
    expect(canView('editor', '/admin/lessons')).toBe(true);
    expect(canView('admin', '/admin')).toBe(true);
  });

  it('chỉ quản trị được vào Quán trà', () => {
    expect(canView('admin', '/learn/chat')).toBe(true);
    expect(canView('admin', '/learn/chat/hoi-bai')).toBe(true);
    expect(canView('editor', '/learn/chat')).toBe(false);
    expect(canView('learner', '/learn/chat')).toBe(false);
    expect(landingPath('learner', '/learn/chat')).toBe('/learn/flashcards');
  });

  it('chỉ quản trị đọc được giáo trình', () => {
    // Giáo trình mở cho mọi vai trò (08/11/2026).
    expect(canView('admin', '/learn/books')).toBe(true);
    expect(canView('editor', '/learn/books/hsk1')).toBe(true);
    expect(canView('learner', '/learn/books')).toBe(true);
    expect(canView('learner', '/learn/books/hsk2')).toBe(true);
    expect(landingPath('learner', '/learn/books/hsk2')).toBe('/learn/books/hsk2');
  });

  it('không có “next” thì về trang nhà của vai trò', () => {
    expect(landingPath('admin', null)).toBe('/admin');
    expect(landingPath('editor', null)).toBe('/admin/lessons');
    expect(landingPath('learner', null)).toBe('/learn/flashcards');
  });

  it('có “next” hợp lệ và đúng quyền thì theo “next”', () => {
    expect(landingPath('admin', '/learn/tests/hsk/2')).toBe('/learn/tests/hsk/2');
    expect(landingPath('learner', '/learn/writing/hsk/1')).toBe('/learn/writing/hsk/1');
    expect(landingPath('editor', '/admin/words')).toBe('/admin/words');
  });

  it('học viên đăng nhập từ trang tài khoản thì về lật thẻ', () => {
    expect(landingPath('learner', '/account')).toBe('/learn/flashcards');
    expect(landingPath('learner', '/account?tab=settings')).toBe('/learn/flashcards');
    expect(canView('learner', '/account')).toBe(true);
    expect(landingPath('admin', '/account')).toBe('/account');
  });

  it('“next” trỏ vào chỗ không có quyền thì bỏ, về trang nhà', () => {
    expect(landingPath('learner', '/admin/users')).toBe('/learn/flashcards');
  });

  it('“next” ra ngoài site thì bỏ (chống chuyển hướng mở)', () => {
    expect(landingPath('admin', 'https://evil.com')).toBe('/admin');
    expect(landingPath('learner', '//evil.com')).toBe('/learn/flashcards');
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
