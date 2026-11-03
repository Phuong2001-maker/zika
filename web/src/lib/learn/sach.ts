// Ba quyển giáo trình đọc ở phần Giáo trình (/learn/books).
//
// Ảnh từng trang nằm ngoài repo, trong thư mục uploads — sinh bằng
// `data/giao-trinh/tach-giao-trinh.py`. Vì sao là ảnh chứ không phải chữ: HSK 2 và HSK 3
// là bản scan, mỗi trang chỉ có một ảnh JPEG và không hề có text.
//
// Vì sao danh sách nằm trong code chứ không nằm trong database: ba quyển này cố định,
// admin không soạn và không sửa, nên thêm bảng + endpoint chỉ tốn công mà không được gì.

export interface Quyen {
  /** Mã dùng cho URL và tên thư mục ảnh. */
  ma: string;
  cap: number;
  ten: string;
  /** Dòng phụ dưới tên sách ở thẻ bìa. */
  phu: string;
  soTrang: number;
  /** Giữ chỗ đúng chiều cao khi ảnh chưa tải, tránh lệch vị trí cuộn. */
  caoTrang: number;
  caoTrangDau: number[];
}

export const SACH: Quyen[] = [
  { ma: 'hsk1', cap: 1, ten: 'Giáo trình HSK 1', phu: 'Đại học Hà Nội · bản 3.0', soTrang: 149, caoTrang: 1622, caoTrangDau: [1698, 1698] },
  { ma: 'hsk2', cap: 2, ten: 'Giáo trình HSK 2', phu: 'Đại học Hà Nội · bản 3.0', soTrang: 121, caoTrang: 1584, caoTrangDau: [1564] },
  { ma: 'hsk3', cap: 3, ten: 'Giáo trình HSK 3', phu: 'Đại học Hà Nội · bản 3.0', soTrang: 199, caoTrang: 1621, caoTrangDau: [1698] },
];

export function quyenTheoMa(ma: string): Quyen | undefined {
  return SACH.find((q) => q.ma === ma);
}

// Ảnh đi qua API đòi quyền quản trị; đường /uploads/giao-trinh/ bị chặn vì sách có bản quyền.

/** Ảnh bìa ở màn chọn sách — bản render nhỏ, rộng 800px. */
export function urlBia(ma: string): string {
  return `/api/admin/books/${ma}/bia`;
}

/** Ảnh một trang sách, rộng 1200px. Số trang đánh từ 1. */
export function urlTrang(ma: string, trang: number): string {
  return `/api/admin/books/${ma}/${trang}`;
}

/**
 * Trang đang đọc dở, nhớ riêng từng quyển.
 *
 * Chỉ lưu ở máy này chứ không đẩy lên server: đây là chỗ đánh dấu cho tiện tay, mất cũng
 * không sao, nên không đáng thêm bảng và endpoint chỉ để đồng bộ một con số.
 */
const KHOA = 'zk.sach.';

export function nhoTrang(ma: string, trang: number): void {
  try {
    localStorage.setItem(KHOA + ma, String(trang));
  } catch {
    // Trình duyệt chặn localStorage (chế độ riêng tư): bỏ qua, lần sau mở lại từ trang 1.
  }
}

export function trangDaDoc(ma: string): number {
  try {
    const v = Number(localStorage.getItem(KHOA + ma));
    return Number.isInteger(v) && v > 0 ? v : 1;
  } catch {
    return 1;
  }
}
