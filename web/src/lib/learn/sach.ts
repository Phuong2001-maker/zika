// Giáo trình đọc ở /learn/books: HSK 1–3 (Hanu) và Hán ngữ 1–6, đọc theo ảnh từng trang, kèm file nghe và đáp án
// khi sách có.
//
// Danh sách quyển không viết cứng ở đây nữa: mỗi quyển có một `sach.json` do `data/giao-trinh/dung-sach.py` sinh,
// API đọc file đó và chỉ trả bộ sách của giáo trình người học (Hán ngữ hoặc HSK). Quyển nào không có file nghe
// thì `nghe` rỗng, không có đáp án thì `dapAn` null — web tự ẩn nút tương ứng.
//
// Vì sao là ảnh chứ không phải chữ: phần lớn các quyển là bản scan, mỗi trang chỉ một ảnh, không có text.

import { api } from '$lib/api/client';

/** Một quyển trên kệ sách (GET /learn/books). */
export interface TomTatSach {
  ma: string;
  bo: 'hanngu' | 'hsk';
  tenBo: string;
  cap: number;
  ten: string;
  /** Dòng phụ dưới tên sách ở thẻ bìa. */
  phu: string;
  /** Đổi khi ảnh/file nghe của quyển được dựng lại; gắn vào đường dẫn để trình duyệt bỏ bản cache cũ. */
  phien: string;
  soTrang: number;
  soBai: number;
  soNghe: number;
  phutNghe: number;
  coDapAn: boolean;
}

export interface BaiSach {
  so: number;
  /** Tên bài bằng chữ Hán, đúng như sách in. */
  ten: string;
  tenVi: string;
  /** Trang bắt đầu bài (đánh từ 1, đúng số ảnh). */
  trang: number;
}

export interface BaiNghe {
  /** Số thứ tự file trong quyển — cũng là tên file trên server. */
  so: number;
  bai: number;
  ten: string;
  giay: number;
}

export interface DapAn {
  soTrang: number;
  cao: number[];
  bai: { so: number; trang: number }[];
}

/** Mô tả đủ của một quyển (GET /learn/books/{ma}). */
export interface Sach {
  ma: string;
  bo: 'hanngu' | 'hsk';
  tenBo: string;
  cap: number;
  ten: string;
  phu: string;
  phien: string;
  soTrang: number;
  rong: number;
  /** Chiều cao ảnh từng trang (px, cùng rộng `rong`), để giữ chỗ đúng khi ảnh chưa tải. */
  cao: number[];
  bai: BaiSach[];
  nghe: BaiNghe[];
  dapAn: DapAn | null;
}

export const taiKeSach = () => api.get<TomTatSach[]>('/learn/books');
export const taiSach = (ma: string) => api.get<Sach>(`/learn/books/${ma}`);

// Ảnh và file nghe đi qua API đòi đăng nhập; đường /uploads/giao-trinh/ bị chặn vì sách có bản quyền.

// Mọi đường dẫn gắn ?v=<phien>: server cho trình duyệt giữ ảnh 30 ngày, dựng lại sách mà không đổi đường dẫn thì
// người học vẫn thấy ảnh cũ.
const v = (phien: string) => (phien ? `?v=${phien}` : '');

/** Ảnh bìa ở kệ sách — bản render nhỏ, rộng 800px. */
export function urlBia(q: { ma: string; phien: string }): string {
  return `/api/learn/books/${q.ma}/bia${v(q.phien)}`;
}

/** Ảnh một trang sách (rộng 1200px) hoặc một trang đáp án. Số trang đánh từ 1. */
export function urlTrang(q: { ma: string; phien: string }, trang: number, dapAn = false): string {
  return `/api/learn/books/${q.ma}/${dapAn ? 'dap-an/' : ''}${trang}${v(q.phien)}`;
}

export function urlNghe(q: { ma: string; phien: string }, so: number): string {
  return `/api/learn/books/${q.ma}/nghe/${so}${v(q.phien)}`;
}

/** Bài chứa trang `trang` — bài cuối cùng bắt đầu trước hoặc đúng trang đó. Trang đầu sách (lời nói đầu) thì null. */
export function baiCuaTrang<T extends { trang: number }>(ds: T[], trang: number): T | null {
  let ra: T | null = null;
  for (const b of ds) if (b.trang <= trang) ra = b;
  return ra;
}

/** 75 → "1:15", 3725 → "1:02:05". */
export function dinhDangGiay(giay: number): string {
  const s = Math.max(0, Math.floor(giay));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const ss = String(s % 60).padStart(2, '0');
  return h ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`;
}

/** 107 → "1 giờ 47 phút", 55 → "55 phút". */
export function dinhDangPhut(phut: number): string {
  if (phut < 60) return `${phut} phút`;
  const m = phut % 60;
  return `${Math.floor(phut / 60)} giờ${m ? ` ${m} phút` : ''}`;
}

/**
 * Trang đang đọc dở, nhớ riêng từng quyển (và riêng phần đáp án), cùng quyển mở gần nhất cho thẻ "Đọc tiếp".
 *
 * Chỉ lưu ở máy này chứ không đẩy lên server: đây là chỗ đánh dấu cho tiện tay, mất cũng
 * không sao, nên không đáng thêm bảng và endpoint chỉ để đồng bộ một con số.
 */
const KHOA = 'zk.sach.';

function khoa(ma: string, dapAn: boolean): string {
  return KHOA + ma + (dapAn ? '.dap-an' : '');
}

export function nhoTrang(ma: string, trang: number, dapAn = false): void {
  try {
    localStorage.setItem(khoa(ma, dapAn), String(trang));
    localStorage.setItem(KHOA + 'gan', ma);
  } catch {
    // Trình duyệt chặn localStorage (chế độ riêng tư): bỏ qua, lần sau mở lại từ trang 1.
  }
}

export function trangDaDoc(ma: string, dapAn = false): number {
  try {
    const v = Number(localStorage.getItem(khoa(ma, dapAn)));
    return Number.isInteger(v) && v > 0 ? v : 1;
  } catch {
    return 1;
  }
}

/** Mã quyển mở gần nhất, '' nếu chưa mở quyển nào ở máy này. */
export function quyenGan(): string {
  try {
    return localStorage.getItem(KHOA + 'gan') ?? '';
  } catch {
    return '';
  }
}
