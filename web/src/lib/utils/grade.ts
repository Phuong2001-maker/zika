// Chấm câu hỏi ở trình duyệt (06 §3). Bản PHP tương ứng: api/app/src/Services/QuestionGrader.php.
// Hai bản phải cho cùng kết quả — sửa bên này nhớ sửa cả bên kia.

/**
 * Nội dung một câu hỏi. Mỗi dạng câu dùng một nhóm trường khác nhau nên để index
 * signature, nhưng vẫn khai báo sẵn các trường hay dùng để không phải ép kiểu.
 */
export interface Payload {
  [key: string]: unknown;
  word?: string;
  ask?: string;
  vi?: string;
  sentence?: string;
  showMeaning?: boolean;
  audioText?: string;
  audioUrl?: string | null;
  options?: string[];
  tiles?: string[];
  pairs?: [string, string][];
  left?: string[];
  right?: string[];
  passage?: string;
  statement?: string;
  zh?: string;
  pinyin?: string;
  sample?: string;
  keywords?: string[];
  answer?: number | boolean | string;
}

export const CHOICE_TYPES = ['meaning', 'pinyin', 'tone', 'audio', 'listen', 'blank', 'pick', 'toZh'];

export type Answer = number | boolean | string | number[] | { pairs: Record<number, number> } | null;

export function isChoice(type: string): boolean {
  return CHOICE_TYPES.includes(type);
}

/** Chữ Hán: bỏ khoảng trắng và dấu câu Trung/Anh. */
export function normZh(s: string): string {
  return s.normalize('NFC').replace(/[\s，。！？、；：“”‘’（）《》,.!?;:'"()-]/g, '');
}

/** Tiếng Việt: thường hoá, bỏ dấu câu, gộp khoảng trắng, GIỮ dấu thanh. */
export function normVi(s: string): string {
  return (
    ' ' +
    s
      .normalize('NFC')
      .toLowerCase()
      .replace(/[.,!?;:"'“”()\-…]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim() +
    ' '
  );
}

/** Người học đã trả lời câu này chưa. */
export function answered(type: string, payload: Payload, a: Answer): boolean {
  if (a === null || a === undefined) return false;
  if (isChoice(type)) return typeof a === 'number';
  if (type === 'order') return Array.isArray(a) && a.length === (payload.tiles ?? []).length;
  if (type === 'trans') return typeof a === 'string' && a.trim() !== '';
  if (type === 'tf') return typeof a === 'boolean';
  if (type === 'match') {
    const pairs = (a as { pairs: Record<number, number> }).pairs ?? {};
    return Object.keys(pairs).length === (payload.left ?? payload.pairs ?? []).length;
  }
  return false;
}

/** Chấm đúng/sai. `payload` là bản đầy đủ (có đáp án) — chỉ dùng cho Bài tập. */
export function check(type: string, payload: Payload, a: Answer): boolean {
  if (isChoice(type)) return typeof a === 'number' && a === payload.answer;

  if (type === 'order') {
    const tiles = payload.tiles ?? [];
    if (!Array.isArray(a) || a.length !== tiles.length) return false;
    const built = (a as number[]).map((i) => tiles[i] ?? '').join('');
    return normZh(built) === normZh(String(payload.answer ?? ''));
  }

  if (type === 'trans') {
    if (typeof a !== 'string' || a.trim() === '') return false;
    const got = normVi(a);
    return (payload.keywords ?? []).every((kw) =>
      String(kw)
        .split('|')
        .some((variant) => {
          const v = normVi(variant).trim();
          return v !== '' && got.includes(' ' + v + ' ');
        }),
    );
  }

  if (type === 'tf') return typeof a === 'boolean' && a === payload.answer;

  if (type === 'match') {
    const pairs = (a as { pairs: Record<number, number> })?.pairs ?? {};
    const n = (payload.pairs ?? []).length;
    if (n === 0 || Object.keys(pairs).length !== n) return false;
    for (let i = 0; i < n; i++) if (pairs[i] !== i) return false;
    return true;
  }

  return false;
}

/** Chuỗi đáp án đúng để hiện ở khung phản hồi. */
export function rightAnswerText(type: string, payload: Payload): string {
  if (isChoice(type)) return String((payload.options ?? [])[Number(payload.answer)] ?? '');
  if (type === 'order') return String(payload.answer ?? '');
  if (type === 'trans') return String(payload.sample ?? '');
  if (type === 'tf') return payload.answer ? 'Đúng' : 'Sai';
  if (type === 'match') return (payload.pairs ?? []).map((p) => `${p[0]} ${p[1]}`).join(' · ');
  return '';
}

/** Nhãn dạng câu hiện ở đầu thẻ câu hỏi (06 §1). */
export const TYPE_LABEL: Record<string, string> = {
  meaning: 'Chọn nghĩa đúng',
  pinyin: 'Chọn pinyin đúng',
  tone: 'Chọn thanh điệu đúng',
  audio: 'Nghe và chọn chữ Hán',
  listen: 'Nghe và chọn',
  blank: 'Điền vào chỗ trống',
  pick: 'Chọn câu viết đúng',
  toZh: 'Chọn câu tiếng Trung đúng nghĩa',
  order: 'Sắp xếp thành câu',
  trans: 'Dịch sang tiếng Việt',
  tf: 'Đọc và chọn Đúng / Sai',
  match: 'Nối từ với nghĩa',
};

/** Màu nền cho các cặp đã nối (06 §2.12). */
export const PAIR_COLORS = ['#DCEBFF', '#CFF5E1', '#FFF0B3', '#E8E0FF', '#FFE0DC', '#FFE9C7'];
