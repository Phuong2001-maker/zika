// Logic “học tiếp” dùng chung cho các màn học. Tách thành hàm thuần để test được mà không cần dựng trang.
// Quy ước: `position` luôn là chỉ số 0 của mục người học đang đứng (thẻ, câu, điểm, chữ), nên mục họ
// sẽ thấy khi mở lại là position + 1 — dùng `soHienThi` để in ra, không tự cộng ở từng màn.

export interface HangTienDo {
  status?: 'in_progress' | 'done';
  position: number;
  state: Record<string, unknown> | null;
}

/** Số thứ tự đem in ra màn hình (1-based), không bao giờ vượt tổng — tránh “13 / 12”. */
export function soHienThi(position: number, total: number): number {
  if (total <= 0) return 0;
  return Math.min(Math.max(position, 0) + 1, total);
}

/** `order` đã lưu chỉ dùng được khi còn đúng là một hoán vị của 0..count-1 (bài có thể đã thêm/bớt từ). */
export function thuTuHopLe(order: unknown, count: number): number[] | null {
  if (!Array.isArray(order) || order.length !== count) return null;
  const seen = new Set<number>();
  for (const v of order) {
    if (!Number.isInteger(v) || v < 0 || v >= count || seen.has(v as number)) return null;
    seen.add(v as number);
  }
  return order as number[];
}

/** Vị trí đã lưu còn dùng được không: chưa xong bài, nằm trong khoảng, và người học không chọn “Từ đầu”. */
export function viTriDaLuu(row: HangTienDo | null | undefined, count: number, fromStart: boolean): number | null {
  if (fromStart || !row || row.status === 'done') return null;
  const p = row.position;
  if (!Number.isInteger(p) || p <= 0 || p >= count) return null;
  return p;
}

/** Lật thẻ: khôi phục chỉ số thẻ + thứ tự đã xáo. Trả null thì bắt đầu mới. */
export function hocTiepThe(row: HangTienDo | null | undefined, count: number, fromStart: boolean): { i: number; order: number[] } | null {
  const i = viTriDaLuu(row, count, fromStart);
  if (i === null) return null;
  const order = thuTuHopLe(row?.state?.order, count);
  return order ? { i, order } : null;
}

/** Lượt bài tập còn dở của bài (id), hoặc null nếu phải mở lượt mới. */
export function luotBaiTapDo(row: (HangTienDo & { openAttempt?: { id: number } | null }) | null | undefined, fromStart: boolean): number | null {
  if (fromStart || !row) return null;
  const mode = row.state?.mode;
  if (typeof mode === 'string' && mode !== 'full') return null;
  const id = Number(row.state?.attemptId ?? row.openAttempt?.id);
  return Number.isInteger(id) && id > 0 ? id : null;
}

export interface KetQuaLuot {
  qid: number;
  correct?: boolean;
}

/**
 * Dựng lại lượt làm bài: thứ tự câu theo server, câu đầu chưa trả lời, và kết quả các câu trước nó.
 * Trả null nếu một câu của lượt không còn trong bài (nội dung đã đổi) — khi đó nên mở lượt mới.
 */
export function khoiPhucLuot(
  questionIds: number[],
  results: KetQuaLuot[],
  indexOfQid: (qid: number) => number,
): { order: number[]; i: number; results: boolean[] } | null {
  const order = questionIds.map(indexOfQid);
  if (order.length === 0 || order.some((k) => k < 0)) return null;
  const byQid = new Map(results.map((r) => [Number(r.qid), !!r.correct]));
  let i = questionIds.findIndex((id) => !byQid.has(id));
  // Đã trả lời hết mà chưa bấm “Xem kết quả”: đứng ở câu cuối để người học bấm nốt, câu đó chấm lại cũng không sao.
  if (i < 0) i = questionIds.length - 1;
  return { order, i, results: questionIds.slice(0, i).map((id) => byQid.get(id) ?? false) };
}
