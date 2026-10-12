// Phân loại route để chặn/chuyển hướng theo trạng thái đăng nhập (07 §0, 08 §0).

import type { Role } from '$lib/api/types';

/** Route ai cũng mở được (khách chưa đăng nhập). */
const PUBLIC_PREFIXES = ['/login', '/forgot', '/reset', '/verify-email', '/invite', '/pages/'];

/** Route chỉ dành cho khách: đã đăng nhập mà vào thì chuyển về Bàn học. */
const GUEST_ONLY = ['/login', '/forgot'];

function matches(path: string, prefix: string): boolean {
  return prefix.endsWith('/') ? path.startsWith(prefix) : path === prefix || path.startsWith(prefix + '/');
}

export function isPublicRoute(path: string): boolean {
  return PUBLIC_PREFIXES.some((p) => matches(path, p));
}

export function isGuestOnly(path: string): boolean {
  return GUEST_ONLY.some((p) => matches(path, p));
}

export function isAdminRoute(path: string): boolean {
  return matches(path, '/admin');
}

/** Chỉ chấp nhận đường dẫn nội bộ bắt đầu bằng “/” (chống chuyển hướng mở), giống AuthController::safeNext. */
export function safeNext(next: string | null | undefined, fallback = '/learn/flashcards'): string {
  if (!next || !/^\/(?![/\\])\S*$/.test(next)) return fallback;
  return next;
}

/** /login?next=<đường dẫn hiện tại> */
export function loginUrl(currentPathWithSearch: string): string {
  return `/login?next=${encodeURIComponent(currentPathWithSearch)}`;
}

/** Trang nhà theo vai trò: quản trị → Tổng quan, biên tập → Bài học, học viên → Bàn học (07 §0, 08 §0). */
export function homePath(role: Role | null | undefined): string {
  if (role === 'admin') return '/admin';
  if (role === 'editor') return '/admin/lessons';
  return '/learn/flashcards';
}

/** Vai trò này mở được đường dẫn không: học viên không vào khu quản trị. */
export function canView(role: Role, path: string): boolean {
  return !(isAdminRoute(path) && role === 'learner');
}

/** Đích sau khi đăng nhập: theo “next” nếu hợp lệ và đúng quyền, không thì về trang nhà của vai trò. */
export function landingPath(role: Role, next?: string | null): string {
  const home = homePath(role);
  const target = safeNext(next, home);
  return canView(role, target) ? target : home;
}
