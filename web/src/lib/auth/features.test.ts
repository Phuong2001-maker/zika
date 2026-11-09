import { describe, expect, it } from 'vitest';
import { canView, homePath } from './routes';
import { featureAllowed, routeFeature } from './features';

describe('quyền hiển thị theo cấu hình server', () => {
  it('ưu tiên cấu hình thay vì ẩn cứng theo vai trò', () => {
    expect(canView('learner', '/learn/chat', { 'community.chat': true })).toBe(true);
    expect(canView('admin', '/learn/chat', { 'community.chat': false })).toBe(false);
    expect(canView('learner', '/admin/settings', { 'community.chat': true })).toBe(false);
  });
  it('chọn lối về dùng được kể cả khi tắt toàn bộ phần học', () => {
    expect(homePath('learner', { 'learn.flashcards': false, 'learn.writing': true })).toBe('/learn/writing');
    expect(homePath('learner', { books: true })).toBe('/learn/books');
    expect(homePath('learner', {})).toBe('/account');
    expect(homePath('admin', {})).toBe('/admin');
  });
  it('giữ đường thi đang làm, chặn các phần đã tắt', () => {
    expect(canView('learner', '/learn/tests/hsk/1/mock/8', {}, [{ mockId: 8, levelId: 1, lessonNo: null }])).toBe(true);
    expect(canView('learner', '/learn/tests/hsk/1/mock/9', {}, [{ mockId: 8, levelId: 1, lessonNo: null }])).toBe(false);
    expect(canView('learner', '/settings', { 'account.preferences': false })).toBe(false);
    expect(canView('learner', '/account', {})).toBe(true);
  });
  it('khóa không biết trong cấu hình đã tải mặc định bị từ chối', () => {
    expect(featureAllowed({}, 'learner', 'unknown')).toBe(false);
    expect(routeFeature('/learn/books/hn1')).toBe('books');
  });
});
