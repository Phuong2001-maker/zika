// Hàm thuần cho 3 chế độ luyện từ ở màn Lật thẻ (Ghép cặp, Trắc nghiệm, Gõ chữ).
// Tách khỏi component để test được mà không cần trình duyệt.

import type { Word } from '$lib/api/types';
import { normZh } from '$lib/utils/grade';
import { shuffle } from '$lib/utils/shuffle';

/**
 * Pinyin về dạng so sánh: bỏ dấu thanh, bỏ số thanh, bỏ khoảng trắng và dấu nháy, ü/v → u.
 * "jué de", "jue2de", "JUEDE" cùng ra "juede" — người học gõ không dấu vẫn được tính đúng.
 */
export function chuanPinyin(s: string): string {
  return s
    .normalize('NFD')
    .replace(/\p{Mark}/gu, '')
    .toLowerCase()
    .replace(/v/g, 'u')
    .replace(/[0-5\s'’·\-–—.,;:!?，。！？…⋯～~]/g, '');
}

/**
 * Chữ Hán về dạng so sánh, bỏ cả dấu lược “…” của cụm có chỗ trống (太…了, 又…又…, …似的): không bàn phím
 * nào gõ tiện dấu đó, nên người học gõ 太了 hay 太…了 đều được tính đúng.
 */
function chuanHan(s: string): string {
  return normZh(s.replace(/[…⋯～~]|\.{2,}|。{2,}/gu, ''));
}

/** Đáp án gõ có đúng từ này không: nhận chữ Hán, hoặc bất kỳ cách đọc nào (pinyin "shéi/shuí"). */
export function goDung(nhap: string, w: Pick<Word, 'hanzi' | 'pinyin'>): boolean {
  const zh = chuanHan(nhap);
  if (zh === '') return false;
  if (zh === chuanHan(w.hanzi)) return true;
  const py = chuanPinyin(nhap);
  if (py === '') return false;
  return w.pinyin.split('/').some((cach) => chuanPinyin(cach) === py);
}

/**
 * Chia đều thành các lượt tối đa `toiDa` phần tử: 13 từ → 5 + 4 + 4 chứ không phải 6 + 6 + 1,
 * lượt cuối chỉ một cặp thì chẳng còn gì để ghép.
 */
export function chiaDeu<T>(ds: readonly T[], toiDa: number): T[][] {
  if (ds.length === 0) return [];
  const soLuot = Math.ceil(ds.length / toiDa);
  const co = Math.floor(ds.length / soLuot);
  const du = ds.length % soLuot;
  const out: T[][] = [];
  let i = 0;
  for (let k = 0; k < soLuot; k++) {
    const n = co + (k < du ? 1 : 0);
    out.push(ds.slice(i, i + n));
    i += n;
  }
  return out;
}

/** Hai ô ghép được với nhau: cùng từ, hoặc trùng nghĩa / trùng chữ (bài có 12 chỗ hai từ cùng nghĩa). */
export function ghepDung(trai: Word, phai: Word): boolean {
  return trai.id === phai.id || trai.meaning === phai.meaning || trai.hanzi === phai.hanzi;
}

/**
 * Các đáp án cho câu trắc nghiệm: nghĩa đúng + tối đa `soNhieu` nghĩa khác lấy từ cùng bài,
 * bỏ nghĩa trùng với đáp án (không thì có hai lựa chọn cùng đúng). Trả về đã xáo và vị trí đúng.
 */
export function taoDapAn(w: Word, kho: readonly Word[], soNhieu = 3, rand?: number): { dapAn: string[]; dung: number } {
  const khac = [...new Set(kho.filter((x) => x.meaning !== w.meaning).map((x) => x.meaning))];
  const nhieu = shuffle(khac, rand).slice(0, soNhieu);
  const dapAn = shuffle([w.meaning, ...nhieu], rand === undefined ? undefined : rand + 1);
  return { dapAn, dung: dapAn.indexOf(w.meaning) };
}

/** "73.4" giây → "1:13"; dưới một phút thì "42s". */
export function dinhDangGiay(giay: number): string {
  const s = Math.max(0, Math.floor(giay));
  if (s < 60) return `${s}s`;
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
