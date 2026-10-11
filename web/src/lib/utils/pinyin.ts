// Tiện ích pinyin ở trình duyệt (06 §7). Bản PHP tương ứng: api/app/src/Core/Pinyin.php.

/** Nguyên âm mang dấu, xếp theo thanh 1 → 4. */
const MARKS: Record<number, string> = {
  1: 'āēīōūǖĀĒĪŌŪǕ',
  2: 'áéíóúǘÁÉÍÓÚǗ',
  3: 'ǎěǐǒǔǚǍĚǏǑǓǙ',
  4: 'àèìòùǜÀÈÌÒÙǛ',
};

/** Thanh điệu của một âm tiết: 1–4, hoặc 5 khi không dấu (thanh nhẹ). */
export function tone(syllable: string): number {
  for (const [t, chars] of Object.entries(MARKS)) {
    for (const c of chars) if (syllable.includes(c)) return Number(t);
  }
  return 5;
}

export interface TonePart {
  text: string;
  tone: number;
}

/**
 * Tách chuỗi pinyin thành từng phần để tô màu theo thanh điệu.
 * Giữ nguyên khoảng trắng và dấu câu, chỉ tách ở ranh giới âm tiết.
 */
export function toneParts(pinyin: string): TonePart[] {
  if (!pinyin) return [];
  const out: TonePart[] = [];
  // Mỗi cụm chữ cái (kể cả chữ có dấu) là một âm tiết; phần còn lại giữ nguyên.
  const re = /([\p{Letter}\p{Mark}]+)|([^\p{Letter}\p{Mark}]+)/gu;
  for (const m of pinyin.matchAll(re)) {
    const word = m[1];
    if (word) out.push({ text: word, tone: tone(word) });
    else out.push({ text: m[2], tone: 0 });
  }
  return out;
}

/** Chuỗi chỉ gồm chữ Hán. */
export function isHanzi(s: string): boolean {
  return s.length > 0 && /^\p{Script=Han}+$/u.test(s);
}

/** Tách từng chữ Hán, bỏ trùng, giữ thứ tự xuất hiện. */
export function hanziChars(s: string): string[] {
  const out: string[] = [];
  for (const c of s) {
    if (/\p{Script=Han}/u.test(c) && !out.includes(c)) out.push(c);
  }
  return out;
}
