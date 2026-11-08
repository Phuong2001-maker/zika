import { describe, expect, it } from 'vitest';
import { baiCuaTrang, dinhDangGiay, dinhDangPhut, urlBia, urlNghe, urlTrang } from './sach';

describe('giáo trình', () => {
  const bai = [
    { so: 16, trang: 20 },
    { so: 17, trang: 34 },
  ];

  it('tìm bài của trang đang đọc, trang đầu sách thì không thuộc bài nào', () => {
    expect(baiCuaTrang(bai, 1)).toBeNull();
    expect(baiCuaTrang(bai, 20)?.so).toBe(16);
    expect(baiCuaTrang(bai, 33)?.so).toBe(16);
    expect(baiCuaTrang(bai, 34)?.so).toBe(17);
    expect(baiCuaTrang(bai, 999)?.so).toBe(17);
  });

  it('ghi thời lượng theo kiểu máy nghe nhạc', () => {
    expect(dinhDangGiay(0)).toBe('0:00');
    expect(dinhDangGiay(75.6)).toBe('1:15');
    expect(dinhDangGiay(3725)).toBe('1:02:05');
    expect(dinhDangPhut(55)).toBe('55 phút');
    expect(dinhDangPhut(60)).toBe('1 giờ');
    expect(dinhDangPhut(107)).toBe('1 giờ 47 phút');
  });

  it('gắn phiên bản vào đường dẫn để bỏ bản cache cũ khi dựng lại sách', () => {
    const q = { ma: 'hn3', phien: 'abc123' };
    expect(urlBia(q)).toBe('/api/learn/books/hn3/bia?v=abc123');
    expect(urlTrang(q, 16)).toBe('/api/learn/books/hn3/16?v=abc123');
    expect(urlTrang(q, 5, true)).toBe('/api/learn/books/hn3/dap-an/5?v=abc123');
    expect(urlNghe(q, 2)).toBe('/api/learn/books/hn3/nghe/2?v=abc123');
    expect(urlTrang({ ma: 'hsk1', phien: '' }, 3)).toBe('/api/learn/books/hsk1/3');
  });
});
