// Nền chung cho 6 bộ nhập Excel của khu quản trị.
//
// Mỗi phần học khai báo một ImportSpec: các sheet, các cột, dòng ví dụ và hàm đọc bảng thành
// dữ liệu gửi lên API. Màn hình và hộp thoại không biết gì về từng phần, chỉ làm việc với spec —
// nên thêm một bộ nhập mới chỉ là thêm một file, giống cách PartContent::register() làm ở API.

import type { Sheet } from '$lib/admin/xlsx';

export interface ColumnSpec {
  key: string;
  /** Tiêu đề ghi ra file mẫu. */
  header: string;
  /** Tên cột khác cũng chấp nhận khi đọc (viết tắt, tên cũ, tiếng Anh). */
  alias?: string[];
  /** Giải thích hiện trong bảng hướng dẫn của hộp thoại. */
  hint?: string;
  /** Thiếu cột này thì báo lỗi ngay thay vì đọc ra bảng rỗng. */
  required?: boolean;
}

export interface SheetSpec {
  name: string;
  /** Mô tả ngắn hiện trên bảng hướng dẫn. */
  about?: string;
  columns: ColumnSpec[];
  /** Dòng ví dụ (không gồm dòng tiêu đề) ghi vào file mẫu. */
  sample: string[][];
  /** Sheet có cũng được, không có cũng xong. */
  optional?: boolean;
}

/** Một dòng đọc không được — vẫn nhập các dòng còn lại, chỉ liệt kê chỗ sai. */
export interface RowIssue {
  sheet: string;
  /** Số dòng như Excel hiển thị (tính cả dòng tiêu đề). */
  row: number;
  message: string;
}

export interface ParseResult<T> {
  items: T[];
  issues: RowIssue[];
  /** Vài dòng đầu để xem trước trong hộp thoại. */
  preview: { cols: string[]; rows: string[][] };
  /** Mô tả kết quả thay cho “N dòng” khi một bản ghi không bằng một dòng (hội thoại, ngữ pháp). */
  summary?: string;
}

export interface ImportSpec<T> {
  /** Tên hiện trên tiêu đề hộp thoại, ví dụ “Nhập từ vựng từ Excel”. */
  title: string;
  /** Tên file mẫu tải về. */
  fileName: string;
  /** Câu dẫn ngắn ở đầu hộp thoại. */
  intro: string;
  sheets: SheetSpec[];
  parse(sheets: Sheet[]): ParseResult<T>;
}

/* ===================================================================== */
/* So khớp tiêu đề cột                                                    */
/* ===================================================================== */

/** Bỏ dấu tiếng Việt, hạ chữ thường, bỏ mọi thứ không phải chữ số — để “Dạng câu” khớp “dang cau”. */
export function norm(s: string): string {
  return s
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');
}

/**
 * Khoá so khớp cho tên do người soạn đặt (tên cấu trúc ngữ pháp…), nơi chữ Hán mang nghĩa.
 * Khác `norm` ở chỗ chỉ bỏ dấu tiếng Việt và khoảng trắng, giữ nguyên mọi ký tự còn lại —
 * `norm` ném hết chữ ngoài a–z0–9 nên “想 + động từ” và “怎么 + động từ” ra cùng một khoá,
 * còn tên thuần chữ Hán thì thành chuỗi rỗng và cấu trúc nào cũng “trùng tên”.
 */
export function khoaTen(s: string): string {
  return s
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase()
    .replace(/\s+/g, '');
}

/**
 * Dò dòng tiêu đề rồi trả về vị trí từng cột.
 * Tiêu đề không nhất thiết ở dòng 1 — người dùng hay chèn dòng ghi chú ở trên, nên quét 10 dòng đầu
 * và chọn dòng khớp được nhiều cột nhất.
 */
export function headerMap(rows: string[][], columns: ColumnSpec[]): { at: number; index: Record<string, number> } {
  const wanted = columns.map((c) => ({ key: c.key, names: [c.header, ...(c.alias ?? [])].map(norm) }));

  let best = { at: -1, index: {} as Record<string, number>, hits: 0 };
  for (let r = 0; r < Math.min(rows.length, 10); r++) {
    const index: Record<string, number> = {};
    let hits = 0;
    rows[r].forEach((cell, c) => {
      const n = norm(cell);
      if (n === '') return;
      const hit = wanted.find((w) => w.names.includes(n) && !(w.key in index));
      if (hit) {
        index[hit.key] = c;
        hits++;
      }
    });
    if (hits > best.hits) best = { at: r, index, hits };
  }
  return { at: best.at, index: best.index };
}

/** Lấy ô theo khoá cột; cột không có hoặc ô trống đều trả chuỗi rỗng. */
export function cellOf(row: string[], index: Record<string, number>, key: string): string {
  const c = index[key];
  return c === undefined ? '' : (row[c] ?? '').trim();
}

/* ===================================================================== */
/* Đọc giá trị trong ô                                                    */
/* ===================================================================== */

/** Danh sách trong một ô: ngăn bằng “|” hoặc xuống dòng (dấu “;” hay nằm trong câu nên không dùng). */
export function splitList(s: string): string[] {
  return s
    .split(/[|\n\r]+/)
    .map((x) => x.trim())
    .filter((x) => x !== '');
}

/** Đáp án trắc nghiệm: “A”…“D”, “a”, hoặc số thứ tự 1–4. Không đọc được thì −1. */
export function answerIndex(s: string): number {
  const t = s.trim();
  if (t === '') return -1;
  const letter = /^[A-Da-d]$/.exec(t);
  if (letter) return t.toUpperCase().charCodeAt(0) - 65;
  const n = Number(t);
  return Number.isInteger(n) && n >= 1 && n <= 4 ? n - 1 : -1;
}

const TRUE_WORDS = ['dung', 'd', 'true', 't', 'co', 'x', 'yes', 'y', '1'];
const FALSE_WORDS = ['sai', 's', 'false', 'f', 'khong', 'no', 'n', '0'];

/** “Đúng/Sai”, “Có/Không”, “TRUE/FALSE”, “1/0”, “x”. Trống hoặc lạ thì trả về `fallback`. */
export function parseBool(s: string, fallback: boolean | null = null): boolean | null {
  const n = norm(s);
  if (n === '') return fallback;
  if (TRUE_WORDS.includes(n)) return true;
  if (FALSE_WORDS.includes(n)) return false;
  return fallback;
}

export function parseNumber(s: string, fallback: number): number {
  // Excel tiếng Việt xuất số thập phân bằng dấu phẩy.
  const n = Number(s.trim().replace(',', '.'));
  return Number.isFinite(n) ? n : fallback;
}

/** Dòng hoàn toàn trống — bỏ qua, không tính là lỗi. */
export function isBlank(row: string[]): boolean {
  return !row.some((c) => c.trim() !== '');
}

/* ===================================================================== */
/* Khung đọc một sheet                                                    */
/* ===================================================================== */

export class Issues {
  readonly list: RowIssue[] = [];

  constructor(private sheet: string) {}

  /** @param at chỉ số dòng trong mảng rows (0-based) — hiển thị thành số dòng Excel. */
  add(at: number, message: string): void {
    this.list.push({ sheet: this.sheet, row: at + 1, message });
  }
}

/** Lấy sheet theo tên (không phân biệt dấu/hoa thường); không thấy thì lấy theo thứ tự. */
export function pickSheet(sheets: Sheet[], spec: SheetSpec, order: number): Sheet | null {
  const byName = sheets.find((s) => norm(s.name) === norm(spec.name));
  if (byName) return byName;
  // Một sheet duy nhất thì chắc chắn là sheet chính, dù người dùng đặt tên gì.
  if (order === 0 && sheets.length > 0) return sheets[0];
  return sheets[order] ?? null;
}

/** Bảng xem trước: dòng tiêu đề + tối đa `max` dòng dữ liệu đầu tiên. */
export function previewOf(sheet: Sheet | null, at: number, max = 6): ParseResult<never>['preview'] {
  if (!sheet || at < 0) return { cols: [], rows: [] };
  const cols = sheet.rows[at] ?? [];
  const rows = sheet.rows.slice(at + 1).filter((r) => !isBlank(r)).slice(0, max);
  return { cols, rows };
}

/** Dòng tiêu đề + dòng ví dụ của một sheet, dùng khi dựng file mẫu. */
export function sampleRows(spec: SheetSpec): string[][] {
  return [spec.columns.map((c) => c.header), ...spec.sample];
}

/* ===================================================================== */
/* Kết quả gửi về từ API                                                  */
/* ===================================================================== */

/** Một mục API không nhận, kèm lý do để hiện lại cho người soạn. */
export interface SkippedItem {
  row?: number;
  hanzi?: string;
  reason: string;
}

export interface ImportOutcome {
  created: number;
  skipped: SkippedItem[];
  /** Số chữ tải được dữ liệu nét (chỉ phần Luyện viết). */
  strokeFetched?: number;
}

/** Thêm vào cuối, hay xoá hết nội dung cũ của phần đó rồi nhập mới. */
export type ImportMode = 'append' | 'replace';
