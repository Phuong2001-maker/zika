import { describe, expect, it } from 'vitest';
import { baiCuaTrang, baiDaMo, dinhDangGiay, dinhDangPhut, trangDaMo, urlBia, urlNghe, urlTrang, type QuyenDoc } from './sach';

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

  it('gắn phiên bản và mã ký vào đường dẫn: bìa không cần mã, trang sách và file nghe thì có', () => {
    const q = { ma: 'hn3', phien: 'abc123' };
    expect(urlBia(q)).toBe('/api/learn/books/hn3/bia?v=abc123');
    expect(urlTrang(q, '99.ab', 16)).toBe('/api/learn/books/hn3/16?v=abc123&t=99.ab');
    expect(urlTrang(q, '99.ab', 5, true)).toBe('/api/learn/books/hn3/dap-an/5?v=abc123&t=99.ab');
    expect(urlNghe(q, '99.ab', 2)).toBe('/api/learn/books/hn3/nghe/2?v=abc123&t=99.ab');
    expect(urlTrang({ ma: 'hsk1', phien: '' }, '1.x', 3)).toBe('/api/learn/books/hsk1/3?t=1.x');
  });

  // Cùng luật với BookAccess ở server; server mới là nơi chặn thật, đây chỉ để vẽ thẻ khoá đúng chỗ.
  describe('bài nào đọc được', () => {
    const sach = { bai, dapAn: { soTrang: 6, cao: [], bai: [{ so: 16, trang: 2 }, { so: 17, trang: 4 }] } };
    const quyen = (q: Partial<QuyenDoc>): QuyenDoc => ({ tuDo: false, khoa: false, mo: [], an: [], ke: null, diem: 0, ve: '', veGiay: 0, ...q });

    it('trang đầu sách đọc được khi đã học tới quyển, quyển còn khoá thì không', () => {
      expect(trangDaMo(sach, quyen({}), 5)).toBe(true);
      expect(trangDaMo(sach, quyen({ khoa: true }), 5)).toBe(false);
    });

    it('trang của bài chỉ đọc được khi bài đã mở, kể cả trang đáp án', () => {
      const q = quyen({ mo: [16] });
      expect(trangDaMo(sach, q, 20)).toBe(true);
      expect(trangDaMo(sach, q, 34)).toBe(false);
      expect(trangDaMo(sach, q, 3, true)).toBe(true);
      expect(trangDaMo(sach, q, 4, true)).toBe(false);
    });

    it('quản trị, biên tập đọc mọi bài', () => {
      expect(baiDaMo(sach, quyen({}), 17)).toBe(false);
      expect(baiDaMo(sach, quyen({ tuDo: true, khoa: true }), 17)).toBe(true);
    });

    // Cây Ẩn/hiện nội dung: bài ẩn khoá với mọi người, kể cả bài đã mở bằng điểm và quản trị.
    it('bài đang ẩn không đọc được, các bài khác và trang đầu sách vẫn theo luật cũ', () => {
      expect(baiDaMo(sach, quyen({ mo: [16, 17], an: [16] }), 16)).toBe(false);
      expect(baiDaMo(sach, quyen({ tuDo: true, an: [16] }), 16)).toBe(false);
      expect(trangDaMo(sach, quyen({ mo: [16], an: [16] }), 20)).toBe(false);
      expect(trangDaMo(sach, quyen({ mo: [16], an: [16] }), 3, true)).toBe(false);
      expect(baiDaMo(sach, quyen({ mo: [16, 17], an: [16] }), 17)).toBe(true);
      expect(trangDaMo(sach, quyen({ an: [16] }), 5)).toBe(true);
    });
  });
});
