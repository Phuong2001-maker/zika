import { describe, expect, it } from 'vitest';
import { seedOf, shuffle, shuffledIndexes, tileOrder } from './shuffle';

describe('xáo trộn có seed', () => {
  it('cùng seed → cùng thứ tự (tải lại trang vẫn giữ nguyên)', () => {
    const a = [1, 2, 3, 4, 5, 6, 7, 8];
    expect(shuffle(a, 42)).toEqual(shuffle(a, 42));
    expect(shuffle(a, 42)).not.toEqual(shuffle(a, 43));
    expect([...shuffle(a, 7)].sort((x, y) => x - y)).toEqual(a);
  });

  it('hoán vị ô chữ luôn khác thứ tự đúng', () => {
    for (let n = 2; n <= 8; n++) {
      for (let s = 0; s < 200; s++) {
        const p = shuffledIndexes(n, s);
        expect(p.every((v, i) => v === i)).toBe(false);
        expect([...p].sort((x, y) => x - y)).toEqual(Array.from({ length: n }, (_, i) => i));
      }
    }
    expect(shuffledIndexes(1, 5)).toEqual([0]);
  });

  it('ô chữ lưu đúng thứ tự vẫn hiện bị xáo, cùng câu thì cùng thứ tự', () => {
    const o = ['我', '是', '越南人'];
    const p = tileOrder(o);
    expect(p).not.toEqual([0, 1, 2]);
    expect([...p].sort()).toEqual([0, 1, 2]);
    expect(tileOrder(['我', '是', '越南人'])).toEqual(p);
    expect(tileOrder(['好'])).toEqual([0]);
  });

  it('seedOf ổn định', () => {
    expect(seedOf(501, 981)).toBe(seedOf(501, 981));
    expect(seedOf(501, 981)).not.toBe(seedOf(981, 501));
  });
});
