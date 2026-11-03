import { describe, expect, it } from 'vitest';
import { catVuong } from './anh';

describe('catVuong', () => {
  it('cắt ô vuông ở giữa theo cạnh ngắn', () => {
    expect(catVuong(4000, 3000)).toEqual({ sx: 500, sy: 0, canh: 3000 });
    expect(catVuong(1080, 1920)).toEqual({ sx: 0, sy: 420, canh: 1080 });
    expect(catVuong(500, 500)).toEqual({ sx: 0, sy: 0, canh: 500 });
    expect(catVuong(301, 200)).toEqual({ sx: 50, sy: 0, canh: 200 });
  });
});
