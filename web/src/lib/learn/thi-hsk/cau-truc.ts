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

/** Tên phần như đề giấy: 一、听力 … theo thứ tự phần có trong đề. */
export const HAN_PHAN: Record<Phan, string> = { listening: '听力', reading: '阅读', writing: '书写', speaking: '口语' };
const SO_HAN = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十'];

/** Lời dặn một dòng cho từng nhóm câu, theo dạng câu và phần (đề giấy ghi tiếng Trung, web ghi tiếng Việt). */
export function loiDan(type: string, phan: Phan, payload: { options?: string[]; imageUrl?: string | null }): string {
  const ds = (payload.options ?? []).join('|');
  switch (type) {
    case 'pic':
      return phan === 'listening' ? 'Nghe rồi chọn tranh đúng.' : 'Đọc câu rồi chọn tranh đúng.';
    case 'listen':
      return ds === 'Đúng|Sai' || ds === 'Sai|Đúng' ? 'Nghe, xem tranh, chọn Đúng hoặc Sai.' : 'Nghe rồi chọn đáp án đúng.';
    case 'blank':
      return 'Chọn từ đúng điền vào chỗ trống.';
    case 'pick':
      return phan === 'writing' ? 'Chọn đáp án đúng.' : 'Đọc rồi chọn đáp án đúng.';
    case 'tf':
      return 'Đọc đoạn văn, câu có dấu ★ đúng hay sai?';
    case 'match':
      return 'Nối mỗi câu với nghĩa đúng.';
    case 'order':
      return 'Sắp xếp các mảnh thành câu đúng.';
    case 'toZh':
      return 'Chọn câu tiếng Trung đúng nghĩa.';
    case 'go':
      return 'Nhìn pinyin, viết chữ Hán vào ô.';
    case 'speak':
      return 'Ghi âm câu trả lời của bạn.';
    case 'tone':
      return 'Chọn thanh điệu đúng.';
    case 'meaning':
      return 'Chọn nghĩa đúng.';
    default:
      return '';
  }
}

export interface BoCau<T> {
  /** Số thứ tự nhóm trong phần (1, 2…) → "第一部分 · Phần 1". */
  so: number;
  han: string;
  type: string;
  cachLam: string;
  cau: { q: T; k: number }[];
}
export interface PhanDe<T> {
  phan: Phan;
  /** "一、听力" */
  han: string;
  ten: string;
  bo: BoCau<T>[];
}

/**
 * Chia đề thành phần (Nghe/Đọc/Viết/Nói) và nhóm câu như đề giấy: các câu liền nhau cùng dạng (và cùng kiểu Đúng/Sai)
 * là một nhóm "第N部分". Dùng cho màn Thi thử kiểu tờ đề (chốt 15/11/2026).
 */
export function chiaDe<T extends { section?: string | null; type: string; payload: { options?: string[]; imageUrl?: string | null } }>(ds: T[]): PhanDe<T>[] {
  const out: PhanDe<T>[] = [];
  for (const g of nhomTheoPhan(ds)) {
    const phan: PhanDe<T> = { phan: g.phan, han: `${SO_HAN[out.length] ?? out.length + 1}、${HAN_PHAN[g.phan]}`, ten: TEN_PHAN[g.phan], bo: [] };
    let khoaTruoc = '';
    for (const c of g.cau) {
      const cach = loiDan(c.q.type, g.phan, c.q.payload);
      // Trên 4 phương án là bộ A–F dùng chung cả nhóm (đề mô phỏng của sách): đổi bộ là sang phần mới, kể cả cùng dạng
      // (nghe chọn tranh 1–5 mỗi câu 3 tranh riêng, 6–10 nhóm 6 tranh, 11–15 nhóm 5 tranh là ba phần).
      const opts = c.q.payload.options ?? [];
      const khoa = `${c.q.type}|${cach}|${opts.length > 4 ? opts.join('¦') : ''}`;
      const cuoi = phan.bo[phan.bo.length - 1];
      if (cuoi && khoa === khoaTruoc) cuoi.cau.push(c);
      else phan.bo.push({ so: phan.bo.length + 1, han: `第${SO_HAN[phan.bo.length] ?? phan.bo.length + 1}部分`, type: c.q.type, cachLam: cach, cau: [c] });
      khoaTruoc = khoa;
    }
    out.push(phan);
  }
  return out;
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
