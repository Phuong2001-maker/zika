import { describe, expect, it } from 'vitest';
import type { Word } from '$lib/api/types';
import { chiaDeu, chuanPinyin, dinhDangGiay, ghepDung, goDung, taoDapAn } from './cham';

const tu = (id: number, hanzi: string, pinyin: string, meaning: string): Word => ({
  id, hanzi, pinyin, meaning, hanViet: '', pos: '', audioUrl: null, examples: [],
});

describe('chuanPinyin', () => {
  it('bỏ dấu, số thanh, khoảng trắng; ü/v như u', () => {
    expect(chuanPinyin('jué de')).toBe('juede');
    expect(chuanPinyin('jue2de5')).toBe('juede');
    expect(chuanPinyin('JUEDE')).toBe('juede');
    expect(chuanPinyin('lǚyóu')).toBe('luyou');
    expect(chuanPinyin('lvyou')).toBe('luyou');
    expect(chuanPinyin("Xī'ān")).toBe('xian');
  });
});

describe('goDung', () => {
  const w = tu(1, '觉得', 'juéde', 'cho rằng');
  it('nhận chữ Hán, bỏ qua khoảng trắng và dấu câu', () => {
    expect(goDung('觉得', w)).toBe(true);
    expect(goDung(' 觉 得。', w)).toBe(true);
    expect(goDung('觉', w)).toBe(false);
  });
  it('nhận pinyin có hoặc không dấu', () => {
    expect(goDung('juede', w)).toBe(true);
    expect(goDung('jué de', w)).toBe(true);
    expect(goDung('jue2 de', w)).toBe(true);
    expect(goDung('jude', w)).toBe(false);
  });
  it('nhận mọi cách đọc khi pinyin có dấu "/"', () => {
    const shei = tu(2, '谁', 'shéi/shuí', 'ai');
    expect(goDung('shei', shei)).toBe(true);
    expect(goDung('shuí', shei)).toBe(true);
    expect(goDung('li', tu(3, '里', 'lǐ / li', 'trong'))).toBe(true);
  });
  it('ô trống không bao giờ đúng', () => {
    expect(goDung('', w)).toBe(false);
    expect(goDung('  ', w)).toBe(false);
    expect(goDung('...', w)).toBe(false);
  });
});

describe('chiaDeu', () => {
  it('chia đều, không để lượt cuối lẻ một', () => {
    expect(chiaDeu([...Array(13).keys()], 6).map((l) => l.length)).toEqual([5, 4, 4]);
    expect(chiaDeu([...Array(12).keys()], 6).map((l) => l.length)).toEqual([6, 6]);
    expect(chiaDeu([...Array(42).keys()], 6).map((l) => l.length)).toEqual([6, 6, 6, 6, 6, 6, 6]);
    expect(chiaDeu([...Array(7).keys()], 6).map((l) => l.length)).toEqual([4, 3]);
    expect(chiaDeu([], 6)).toEqual([]);
  });
  it('giữ đủ phần tử, đúng thứ tự', () => {
    expect(chiaDeu([1, 2, 3, 4, 5, 6, 7], 6).flat()).toEqual([1, 2, 3, 4, 5, 6, 7]);
  });
});

describe('ghepDung', () => {
  it('cùng từ hoặc trùng nghĩa thì ghép được', () => {
    const a = tu(1, '爸爸', 'bàba', 'bố');
    const b = tu(2, '爸', 'bà', 'bố');
    const c = tu(3, '妈妈', 'māma', 'mẹ');
    expect(ghepDung(a, a)).toBe(true);
    expect(ghepDung(a, b)).toBe(true);
    expect(ghepDung(a, c)).toBe(false);
  });
});

describe('taoDapAn', () => {
  const kho = [tu(1, '一', 'yī', 'một'), tu(2, '二', 'èr', 'hai'), tu(3, '三', 'sān', 'ba'), tu(4, '四', 'sì', 'bốn'), tu(5, '两', 'liǎng', 'hai')];
  it('có đáp án đúng, không trùng nghĩa, đủ 4 lựa chọn', () => {
    for (let seed = 1; seed < 30; seed++) {
      const { dapAn, dung } = taoDapAn(kho[1], kho, 3, seed);
      expect(dapAn[dung]).toBe('hai');
      expect(dapAn.filter((d) => d === 'hai')).toHaveLength(1);
      expect(new Set(dapAn).size).toBe(dapAn.length);
      expect(dapAn).toHaveLength(4);
    }
  });
  it('bài ít nghĩa khác thì ít lựa chọn hơn, không bịa', () => {
    const { dapAn } = taoDapAn(kho[0], kho.slice(0, 2));
    expect(dapAn.sort()).toEqual(['hai', 'một']);
  });
});

describe('dinhDangGiay', () => {
  it('dưới một phút ghi giây, trên thì phút:giây', () => {
    expect(dinhDangGiay(0)).toBe('0s');
    expect(dinhDangGiay(42.9)).toBe('42s');
    expect(dinhDangGiay(73)).toBe('1:13');
    expect(dinhDangGiay(600)).toBe('10:00');
  });
});
