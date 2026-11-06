import { describe, expect, it } from 'vitest';
import { buddyShownOn } from './shopee-buddy';

describe('buddyShownOn', () => {
  it.each(['/learn/flashcards', '/learn/writing', '/learn/grammar', '/learn/tests', '/learn/books'])(
    'hiện ở Bàn học và màn chọn cấp: %s',
    (path) => {
      expect(buddyShownOn(path)).toBe(true);
    },
  );

  it.each([
    '/learn/flashcards/hsk/1',
    '/learn/grammar/hsk/2',
    '/learn/flashcards/hsk/1/lesson/2',
    '/learn/writing/hsk/1/review',
    '/learn/exercises/hsk/1/lesson/1',
    '/learn/tests/hsk/1/lesson/3',
    '/learn/tests/hsk/1/mock/5',
    '/learn/books/hsk1',
  ])('hiện cả ở màn chọn bài và màn đang làm bài: %s', (path) => {
    expect(buddyShownOn(path)).toBe(true);
  });

  it('không hiện ngoài khu học', () => {
    expect(buddyShownOn('/account')).toBe(false);
    expect(buddyShownOn('/admin/lessons')).toBe(false);
  });
});
