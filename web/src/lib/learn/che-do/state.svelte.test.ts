import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Word } from '$lib/api/types';
import { GhepCap, GoChu, TracNghiem } from './state.svelte';

const tu = (id: number, hanzi: string, pinyin: string, meaning: string): Word => ({
  id, hanzi, pinyin, meaning, hanViet: '', pos: '', audioUrl: null, examples: [],
});
const BAI = [
  tu(1, '一', 'yī', 'một'), tu(2, '二', 'èr', 'hai'), tu(3, '三', 'sān', 'ba'), tu(4, '四', 'sì', 'bốn'),
  tu(5, '五', 'wǔ', 'năm'), tu(6, '六', 'liù', 'sáu'), tu(7, '七', 'qī', 'bảy'),
];

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

/** Ghép đúng hết lượt đang mở. */
function ghepHetLuot(g: GhepCap): void {
  g.trai.forEach((w, ti) => {
    const pi = g.phai.findIndex((p, k) => p.id === w.id && !g.xongPhai.has(k));
    g.chon('trai', ti);
    expect(g.chon('phai', pi)).toBe('dung');
  });
}

describe('GhepCap', () => {
  it('7 từ chia 2 lượt 4 + 3, ghép đúng hết thì xong và báo ra ngoài một lần', () => {
    const onXong = vi.fn();
    const g = new GhepCap(BAI, onXong);
    expect(g.luot.map((l) => l.length)).toEqual([4, 3]);
    ghepHetLuot(g);
    expect(g.xong).toBe(false);
    vi.advanceTimersByTime(500);
    expect(g.trang).toBe(1);
    ghepHetLuot(g);
    expect(g.xong).toBe(true);
    expect(onXong).toHaveBeenCalledTimes(1);
    expect(g.ketQua()).toMatchObject({ dung: 7, tong: 7, sai: [] });
  });

  it('ghép nhầm: rung, ghi cả hai từ vào danh sách ôn, rồi bỏ chọn', () => {
    const g = new GhepCap(BAI);
    const t = g.trai[0];
    const pi = g.phai.findIndex((p) => p.id !== t.id);
    g.chon('trai', 0);
    expect(g.chon('phai', pi)).toBe('sai');
    expect(g.rung).not.toBeNull();
    expect(g.chon('trai', 1)).toBeNull(); // đang rung thì không nhận bấm
    vi.advanceTimersByTime(500);
    expect(g.rung).toBeNull();
    expect(g.chonTrai).toBeNull();
    expect([...g.sai].sort()).toEqual([t.id, g.phai[pi].id].sort());
  });

  it('bấm lại ô đang chọn thì bỏ chọn; ô đã ghép thì không bấm được nữa', () => {
    const g = new GhepCap(BAI);
    g.chon('trai', 0);
    g.chon('trai', 0);
    expect(g.chonTrai).toBeNull();
    const pi = g.phai.findIndex((p) => p.id === g.trai[0].id);
    g.chon('trai', 0);
    g.chon('phai', pi);
    expect(g.chon('trai', 0)).toBeNull();
    expect(g.chonTrai).toBeNull();
  });
});

describe('TracNghiem', () => {
  it('trả lời sai thì từ đó được hỏi lại đúng một lần ở cuối', () => {
    const q = new TracNghiem(BAI.slice(0, 4), BAI);
    const dau = q.cau!;
    const sai = q.dapAn.findIndex((_, i) => i !== q.dungIdx);
    expect(q.traLoi(sai)).toBe(false);
    expect(q.hangDoi).toHaveLength(5);
    expect(q.hangDoi[4].id).toBe(dau.id);
    expect(q.traLoi(q.dungIdx)).toBeNull(); // đã chọn rồi thì không đổi được
    q.tiep();
    // Trả lời đúng hết phần còn lại, kể cả lần hỏi lại.
    while (!q.xong) {
      q.traLoi(q.dungIdx);
      q.tiep();
    }
    expect(q.hangDoi).toHaveLength(5);
    expect(q.ketQua()).toMatchObject({ dung: 3, tong: 4 });
    expect(q.ketQua().sai.map((w) => w.id)).toEqual([dau.id]);
  });

  it('chưa chọn thì không sang câu được', () => {
    const q = new TracNghiem(BAI, BAI);
    q.tiep();
    expect(q.vt).toBe(0);
  });
});

describe('GoChu', () => {
  it('đúng nhờ gợi ý vẫn vào danh sách ôn; bỏ qua cũng vậy; ô trống không chấm', () => {
    const g = new GoChu(BAI.slice(0, 3));
    const a = g.cau!;
    g.kiemTra();
    expect(g.trangThai).toBe('go');
    g.xinGoiY();
    g.nhap = a.pinyin;
    g.kiemTra();
    expect(g.trangThai).toBe('dung');
    expect(g.sai.has(a.id)).toBe(true);

    g.tiep();
    const b = g.cau!;
    expect(g.nhap).toBe('');
    expect(g.goiY).toBe(0);
    g.nhap = b.hanzi;
    g.kiemTra();
    expect(g.sai.has(b.id)).toBe(false);

    g.tiep();
    const c = g.cau!;
    g.boQua();
    expect(g.trangThai).toBe('sai');
    g.tiep();
    expect(g.xong).toBe(true);
    expect(g.ketQua().sai.map((w) => w.id).sort()).toEqual([a.id, c.id].sort());
  });

  it('gợi ý tối đa 2 bước', () => {
    const g = new GoChu(BAI);
    g.xinGoiY();
    g.xinGoiY();
    g.xinGoiY();
    expect(g.goiY).toBe(2);
  });
});
