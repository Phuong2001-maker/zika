// Xáo trộn có seed (06 §2.9): cùng seed → cùng thứ tự, để tải lại trang vẫn giữ nguyên.

/** Bộ sinh số giả ngẫu nhiên mulberry32. */
export function rng(seed: number): () => number {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** Fisher–Yates; không truyền seed thì dùng Math.random. */
export function shuffle<T>(arr: readonly T[], seed?: number): T[] {
  const out = arr.slice();
  const rand = seed === undefined ? Math.random : rng(seed);
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(rand() * (i + 1));
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
}

/**
 * Hoán vị chỉ số 0..n-1 đã xáo, BẢO ĐẢM khác thứ tự gốc khi n ≥ 2
 * (ô chữ sắp xếp câu, cột phải nối từ không được trùng đáp án đúng).
 */
export function shuffledIndexes(n: number, seed: number): number[] {
  const base = Array.from({ length: n }, (_, i) => i);
  if (n < 2) return base;
  let out = shuffle(base, seed);
  let tries = 1;
  while (out.every((v, i) => v === i) && tries < 10) {
    out = shuffle(base, seed + tries * 7919);
    tries++;
  }
  if (out.every((v, i) => v === i)) out = [...base.slice(1), base[0]]; // xoay vòng nếu vẫn trùng
  return out;
}

/** Seed ổn định từ các số (ví dụ id câu + id lượt). */
export function seedOf(...nums: number[]): number {
  let h = 2166136261;
  for (const n of nums) {
    h ^= n >>> 0;
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}
