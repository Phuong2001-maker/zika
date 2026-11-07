import { describe, expect, it } from 'vitest';
import { hocTiepThe, khoiPhucLuot, luotBaiTapDo, soHienThi, thuTuHopLe, viTriDaLuu } from './resume';

const hang = (position: number, state: Record<string, unknown> | null = null, status: 'in_progress' | 'done' = 'in_progress') => ({ position, state, status });

describe('soHienThi', () => {
  it('đổi chỉ số 0 sang số thứ tự và không vượt tổng', () => {
    expect(soHienThi(4, 37)).toBe(5);
    expect(soHienThi(12, 12)).toBe(12);
    expect(soHienThi(20, 12)).toBe(12);
    expect(soHienThi(0, 0)).toBe(0);
  });
});

describe('thuTuHopLe', () => {
  it('chỉ nhận hoán vị đúng cỡ', () => {
    expect(thuTuHopLe([2, 0, 1], 3)).toEqual([2, 0, 1]);
    expect(thuTuHopLe([0, 1], 3)).toBeNull();
    expect(thuTuHopLe([0, 0, 1], 3)).toBeNull();
    expect(thuTuHopLe([0, 1, 5], 3)).toBeNull();
    expect(thuTuHopLe('x', 3)).toBeNull();
  });
});

describe('viTriDaLuu / hocTiepThe', () => {
  const order = [3, 1, 0, 2];
  it('khôi phục vị trí và thứ tự đã xáo', () => {
    expect(hocTiepThe(hang(2, { order }), 4, false)).toEqual({ i: 2, order });
  });
  it('bỏ qua khi Từ đầu, đã xong, vị trí 0 hoặc ngoài khoảng', () => {
    expect(hocTiepThe(hang(2, { order }), 4, true)).toBeNull();
    expect(hocTiepThe(hang(2, { order }, 'done'), 4, false)).toBeNull();
    expect(viTriDaLuu(hang(0), 4, false)).toBeNull();
    expect(viTriDaLuu(hang(4), 4, false)).toBeNull();
    expect(viTriDaLuu(null, 4, false)).toBeNull();
  });
  it('thứ tự lệch số từ thì bắt đầu mới', () => {
    expect(hocTiepThe(hang(2, { order: [0, 1] }), 4, false)).toBeNull();
    expect(hocTiepThe(hang(2), 4, false)).toBeNull();
  });
  it('chữ viết dùng viTriDaLuu không cần order', () => {
    expect(viTriDaLuu(hang(3), 5, false)).toBe(3);
  });
});

describe('luotBaiTapDo', () => {
  it('lấy attemptId trong state hoặc openAttempt', () => {
    expect(luotBaiTapDo(hang(1, { attemptId: 9, mode: 'full' }), false)).toBe(9);
    expect(luotBaiTapDo({ ...hang(0), openAttempt: { id: 4 } }, false)).toBe(4);
  });
  it('bỏ qua khi Từ đầu, không có lượt hoặc lượt chế độ khác', () => {
    expect(luotBaiTapDo(hang(1, { attemptId: 9 }), true)).toBeNull();
    expect(luotBaiTapDo(hang(1), false)).toBeNull();
    expect(luotBaiTapDo(hang(1, { attemptId: 9, mode: 'wrong' }), false)).toBeNull();
  });
});

describe('khoiPhucLuot', () => {
  const idx = (qid: number) => [10, 20, 30].indexOf(qid);
  it('đứng ở câu chưa trả lời đầu tiên và giữ kết quả các câu trước', () => {
    const r = khoiPhucLuot([30, 10, 20], [{ qid: 30, correct: true }, { qid: 10, correct: false }], idx);
    expect(r).toEqual({ order: [2, 0, 1], i: 2, results: [true, false] });
  });
  it('trả lời hết mà chưa kết thúc thì đứng ở câu cuối', () => {
    const r = khoiPhucLuot([10, 20], [{ qid: 10, correct: true }, { qid: 20, correct: true }], idx);
    expect(r).toEqual({ order: [0, 1], i: 1, results: [true] });
  });
  it('câu không còn trong bài thì trả null', () => {
    expect(khoiPhucLuot([10, 99], [], idx)).toBeNull();
  });
});
