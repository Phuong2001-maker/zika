// Định dạng kiểu Việt Nam (05 §10, README §2): 1.284 · 3,5% · dd/MM/yyyy · HH:mm · “41 giờ 20 phút”.

const TZ = 'Asia/Ho_Chi_Minh';

/** Số có dấu chấm ngăn nghìn, dấu phẩy thập phân. */
export function num(n: number | null | undefined, decimals = 0): string {
  if (n === null || n === undefined || Number.isNaN(n)) return '—';
  return new Intl.NumberFormat('vi-VN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(n);
}

/** Phần trăm: 3,5% (bỏ “,0” khi chẵn). */
export function pct(n: number | null | undefined, decimals = 1): string {
  if (n === null || n === undefined || Number.isNaN(n)) return '—';
  const s = num(n, Number.isInteger(n) ? 0 : decimals);
  return `${s}%`;
}

/** Giờ VN của một chuỗi ISO dưới dạng các phần (không phụ thuộc múi giờ máy người dùng). */
function parts(iso: string | Date): Record<string, string> {
  const d = typeof iso === 'string' ? new Date(iso) : iso;
  const out: Record<string, string> = {};
  for (const p of new Intl.DateTimeFormat('en-GB', {
    timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, weekday: 'short',
  }).formatToParts(d)) {
    out[p.type] = p.value;
  }
  if (out.hour === '24') out.hour = '00';
  return out;
}

export function date(iso: string | Date | null | undefined): string {
  if (!iso) return '—';
  const p = parts(iso);
  return `${p.day}/${p.month}/${p.year}`;
}

export function dayMonth(iso: string | Date | null | undefined): string {
  if (!iso) return '—';
  const p = parts(iso);
  return `${p.day}/${p.month}`;
}

export function time(iso: string | Date | null | undefined): string {
  if (!iso) return '—';
  const p = parts(iso);
  return `${p.hour}:${p.minute}`;
}

/** Khoá ngày theo giờ VN: 2026-10-04 */
export function dayKey(iso: string | Date): string {
  const p = parts(iso);
  return `${p.year}-${p.month}-${p.day}`;
}

const WEEKDAYS = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
const WD_SHORT: Record<string, number> = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

/** “Chủ nhật, 04/10/2026” */
export function longDate(iso: string | Date): string {
  const p = parts(iso);
  return `${WEEKDAYS[WD_SHORT[p.weekday] ?? 0]}, ${p.day}/${p.month}/${p.year}`;
}

export function weekdayName(iso: string | Date): string {
  return WEEKDAYS[WD_SHORT[parts(iso).weekday] ?? 0];
}

/** “Hôm nay 20:41”, “Hôm qua 22:47”, “01/10 21:20” */
export function lastSeen(iso: string | null | undefined, now: Date = new Date()): string {
  if (!iso) return '—';
  const k = dayKey(iso);
  if (k === dayKey(now)) return `Hôm nay ${time(iso)}`;
  if (k === dayKey(new Date(now.getTime() - 86_400_000))) return `Hôm qua ${time(iso)}`;
  return `${dayMonth(iso)} ${time(iso)}`;
}

/** Thời điểm tương đối: “vừa xong”, “5 phút trước”, “2 giờ trước”, “hôm qua”, “3 ngày trước”. */
export function ago(iso: string | null | undefined, now: Date = new Date()): string {
  if (!iso) return '';
  const diff = Math.max(0, now.getTime() - new Date(iso).getTime());
  const min = Math.floor(diff / 60_000);
  if (min < 1) return 'vừa xong';
  if (min < 60) return `${min} phút trước`;
  const h = Math.floor(min / 60);
  if (dayKey(iso) === dayKey(now)) return `${h} giờ trước`;
  if (dayKey(iso) === dayKey(new Date(now.getTime() - 86_400_000))) return 'hôm qua';
  const d = Math.max(2, Math.round(diff / 86_400_000));
  if (d < 30) return `${d} ngày trước`;
  return date(iso);
}

/** Thời lượng: “3 phút”, “1 giờ 10 phút”, “41 giờ 20 phút”. */
export function duration(sec: number | null | undefined): string {
  const min = Math.floor(Math.max(0, sec ?? 0) / 60);
  const h = Math.floor(min / 60);
  const m = min % 60;
  if (h === 0) return `${m} phút`;
  return m === 0 ? `${h} giờ` : `${h} giờ ${m} phút`;
}

/** “4 phút 10 giây” (kết quả kiểm tra). */
export function minSec(sec: number): string {
  const s = Math.max(0, Math.round(sec));
  const m = Math.floor(s / 60);
  const r = s % 60;
  if (m === 0) return `${r} giây`;
  return r === 0 ? `${m} phút` : `${m} phút ${String(r).padStart(2, '0')} giây`;
}

/** Đồng hồ “04:32” (âm thì về 00:00). */
export function clock(ms: number): string {
  const s = Math.max(0, Math.ceil(ms / 1000));
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
}

export const POS_LABELS: Record<string, string> = {
  n: 'danh từ', v: 'động từ', adj: 'tính từ', adv: 'phó từ', mw: 'lượng từ', phrase: 'cụm từ',
  num: 'số từ', pron: 'đại từ', prep: 'giới từ', conj: 'liên từ', part: 'trợ từ', other: 'khác',
};

export const SELF_LEVEL_LABELS: Record<string, string> = {
  beginner: 'Mới bắt đầu', hsk12: 'HSK 1–2', hsk34: 'HSK 3–4', hsk5: 'HSK 5 trở lên',
};

export const ROLE_LABELS: Record<string, string> = {
  learner: 'Người học', editor: 'Biên tập nội dung', admin: 'Quản trị viên',
};

/** Hán Việt hiển thị IN HOA (03 §3.2). */
export function upperHv(s: string | null | undefined): string {
  return (s ?? '').toLocaleUpperCase('vi-VN');
}
