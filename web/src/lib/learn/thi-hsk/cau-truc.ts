// Thi thử HSK 3.0: tên các phần, kiểu câu nói và cấu trúc gợi ý của từng cấp. Dùng chung cho màn học viên và admin.

export type Phan = 'listening' | 'reading' | 'writing' | 'speaking';

export const PHAN: { code: Phan; ten: string; icon: string }[] = [
  { code: 'listening', ten: 'Nghe', icon: 'headphones' },
  { code: 'reading', ten: 'Đọc', icon: 'book' },
  { code: 'writing', ten: 'Viết', icon: 'edit' },
  { code: 'speaking', ten: 'Nói', icon: 'mic' },
];

export const TEN_PHAN: Record<string, string> = Object.fromEntries(PHAN.map((p) => [p.code, p.ten]));

/** Kiểu câu nói (khớp QuestionPayload::SPEAK_TASKS). */
export const NHIEM_VU_NOI: [string, string][] = [
  ['repeat', 'Nghe và nhắc lại'],
  ['read', 'Đọc to'],
  ['answer', 'Trả lời câu hỏi'],
  ['topic', 'Nói theo chủ đề'],
  ['picture', 'Nhìn tranh nói'],
];
export const TEN_NHIEM_VU: Record<string, string> = Object.fromEntries(NHIEM_VU_NOI);

/**
 * Số câu và thời gian gợi ý khi admin tạo đề, theo cấu trúc đề HSK 3.0 các trang luyện thi công bố (đối chiếu 09/11/2026).
 * Chỉ để điền sẵn: admin sửa được, đề thật có thay đổi thì sửa ở đây.
 */
export const GOI_Y_CAP: Record<number, { cau: number; phut: number }> = {
  1: { cau: 40, phut: 32 },
  2: { cau: 60, phut: 52 },
  3: { cau: 70, phut: 73 },
  4: { cau: 70, phut: 75 },
  5: { cau: 72, phut: 100 },
  6: { cau: 82, phut: 115 },
};

export type SoCauPhan = Partial<Record<Phan, number>>;

/** "Nghe 20 · Đọc 20 · Nói 5" — bỏ phần không có câu. */
export function moTaPhan(s: SoCauPhan): string {
  return PHAN.filter((p) => (s[p.code] ?? 0) > 0)
    .map((p) => `${p.ten} ${s[p.code]}`)
    .join(' · ');
}

/** Thứ tự câu trong lượt làm bài, gom theo phần để dãy số câu chia nhóm Nghe / Đọc / Viết / Nói. */
export function nhomTheoPhan<T extends { section?: string | null }>(ds: T[]): { phan: Phan; cau: { q: T; k: number }[] }[] {
  const out: { phan: Phan; cau: { q: T; k: number }[] }[] = [];
  ds.forEach((q, k) => {
    const phan = (PHAN.some((p) => p.code === q.section) ? q.section : 'reading') as Phan;
    const cuoi = out[out.length - 1];
    if (cuoi && cuoi.phan === phan) cuoi.cau.push({ q, k });
    else out.push({ phan, cau: [{ q, k }] });
  });
  return out;
}
