// Trạng thái màn đọc một quyển giáo trình. Bố cục máy tính và điện thoại dùng chung đối tượng này nên kéo cửa sổ
// qua 768px không mất trang đang đọc, không ngắt file đang nghe.

import { ApiError } from '$lib/api/client';
import {
  baiCuaTrang, baiDaMo, moBaiSach, taiQuyen, taiSach, trangDaDoc, trangDaMo,
  type BaiNghe, type BaiSach, type QuyenDoc, type Sach,
} from '$lib/learn/sach';
import { diemDoc } from '$lib/learn/diem-doc.svelte';
import { MayNghe } from './may-nghe.svelte';

/** Một mục trong mục lục, cho cả sách lẫn đáp án. */
export interface MucBai {
  so: number;
  ten: string;
  tenVi: string;
  trang: number;
}

export class DocSach {
  sach = $state<Sach | null>(null);
  dangTai = $state(true);
  loi = $state('');
  /** Đang xem phần đáp án thay vì trang sách. */
  dapAn = $state(false);
  /** Trang đang đọc của phần đang xem. */
  trang = $state(1);
  /** Trang cần cuộn tới khi danh sách trang vừa dựng lại (mở quyển, đổi Sách ↔ Đáp án). */
  dich = $state(1);
  ngan = $state<'' | 'muc-luc' | 'nghe'>('');
  /** Quyền đọc: bài nào mở, bài nào mở tiếp được, số điểm. Giữ riêng với `sach` vì đổi sau mỗi lần mở bài. */
  quyen = $state<QuyenDoc | null>(null);
  /**
   * Mã ký đang gắn vào đường dẫn ảnh. Không đổi theo mỗi lần lấy quyền: đổi là mọi ảnh đang hiện tải lại, nên chỉ
   * thay khi mã cũ đã đi quá nửa tuổi (xem lamMoiQuyen).
   */
  ve = $state('');
  /** Bài đang gọi API mở (nút xoay chờ, chặn bấm hai lần). */
  dangMo = $state<number | null>(null);
  loiMo = $state('');
  readonly may = new MayNghe();

  constructor() {
    this.may.lamMoiVe = async () => {
      await this.lamMoiQuyen(true);
      return this.ve || null;
    };
  }

  readonly soTrang = $derived(this.sach ? (this.dapAn ? (this.sach.dapAn?.soTrang ?? 0) : this.sach.soTrang) : 0);
  readonly cao = $derived(this.sach ? (this.dapAn ? (this.sach.dapAn?.cao ?? []) : this.sach.cao) : []);

  // Bảng tra là object thường dựng lại trong $derived (không sửa tại chỗ), nên không cần SvelteMap.
  readonly baiTheoSo = $derived<Record<number, BaiSach>>(Object.fromEntries((this.sach?.bai ?? []).map((b) => [b.so, b])));

  /** Mục lục của phần đang xem: đáp án đánh trang riêng nhưng mượn tên bài của sách. */
  readonly mucLuc = $derived.by<MucBai[]>(() => {
    const s = this.sach;
    if (!s) return [];
    if (!this.dapAn) return s.bai;
    return (s.dapAn?.bai ?? []).map((d) => {
      const b = this.baiTheoSo[d.so];
      return { so: d.so, ten: b?.ten ?? '', tenVi: b?.tenVi ?? '', trang: d.trang };
    });
  });

  /** Trang bắt đầu bài → bài, để chèn dải tên bài lên đầu trang đó. */
  readonly dauBai = $derived<Record<number, MucBai>>(Object.fromEntries(this.mucLuc.map((b) => [b.trang, b])));
  readonly baiHienTai = $derived(baiCuaTrang(this.mucLuc, this.trang));

  readonly ngheTheoBai = $derived.by(() => {
    const m: Record<number, BaiNghe[]> = {};
    for (const n of this.sach?.nghe ?? []) (m[n.bai] ??= []).push(n);
    return m;
  });
  readonly dapAnTheoBai = $derived<Record<number, number>>(Object.fromEntries((this.sach?.dapAn?.bai ?? []).map((d) => [d.so, d.trang])));

  /** Bài `so` của quyển đang mở đọc được chưa (null = trang đầu sách). */
  baiMo(so: number | null): boolean {
    return !!this.sach && !!this.quyen && baiDaMo(this.sach, this.quyen, so);
  }

  /** Trang `n` của phần đang xem đọc được chưa. */
  trangMo(n: number): boolean {
    return !!this.sach && !!this.quyen && trangDaMo(this.sach, this.quyen, n, this.dapAn);
  }

  /** Bài `so` của quyển này là bài mở tiếp được bằng điểm. */
  laBaiKe(so: number): boolean {
    return this.quyen?.ke?.ma === this.sach?.ma && this.quyen?.ke?.bai === so;
  }

  /** Dùng 1 điểm mở bài `so`. */
  async moBai(so: number): Promise<void> {
    const s = this.sach;
    if (!s || this.dangMo !== null) return;
    this.dangMo = so;
    this.loiMo = '';
    try {
      this.#napQuyen(await moBaiSach(s.ma, so));
      void diemDoc.refresh();
    } catch (e) {
      this.loiMo = e instanceof ApiError ? e.message : 'Chưa mở được bài, thử lại nhé.';
      void this.lamMoiQuyen();
    } finally {
      this.dangMo = null;
    }
  }

  /**
   * Lấy lại quyền đọc (vừa nhận điểm ở tab khác, mã ký sắp hết hạn). `doiVe` = true thì luôn lấy mã mới (ảnh hay
   * file nghe vừa lỗi, có thể do máy ngủ quá 6 giờ).
   */
  async lamMoiQuyen(doiVe = false): Promise<void> {
    const s = this.sach;
    if (!s) return;
    try {
      this.#napQuyen(await taiQuyen(s.ma), doiVe);
    } catch {
      // Mất mạng: giữ quyền cũ, ảnh lỗi thì người học bấm Thử lại.
    }
  }

  /** Mã ký đã đi quá nửa tuổi: màn đọc hỏi định kỳ để thay trước khi hết hạn giữa chừng. */
  veSapHet(): boolean {
    const het = Number(this.ve.split('.')[0]);
    const tuoi = this.quyen?.veGiay ?? 0;
    return !het || het - Date.now() / 1000 < tuoi / 2;
  }

  #napQuyen(q: QuyenDoc, doiVe = false): void {
    if (doiVe || !this.ve || this.veSapHet()) this.ve = q.ve;
    this.quyen = q;
    const s = this.sach;
    // Máy nghe chỉ giữ file của bài đã mở: bấm "file sau" hay hết file tự sang file kế không rơi vào bài khoá.
    if (s) this.may.capNhat(this.ve, s.nghe.filter((n) => baiDaMo(s, q, n.bai)));
  }

  async tai(ma: string): Promise<void> {
    this.dangTai = true;
    this.loi = '';
    this.ngan = '';
    this.dapAn = false;
    try {
      const s = await taiSach(ma);
      this.sach = s;
      this.dich = Math.min(trangDaDoc(ma), s.soTrang);
      this.trang = this.dich;
      this.ve = s.quyen.ve;
      this.may.napSach(ma, s.phien, s.ten);
      this.#napQuyen(s.quyen, true);
    } catch (e) {
      this.sach = null;
      this.quyen = null;
      this.loi = e instanceof ApiError && e.status === 404 ? 'khong-co' : 'mang';
    } finally {
      this.dangTai = false;
    }
  }

  /**
   * Đổi sang phần đáp án của bài `bai`. Chỗ xem dở trong đáp án vẫn thuộc bài đó thì mở lại đúng chỗ ấy (đang so
   * đáp án câu 5 thì quay lại vẫn ở câu 5), khác bài thì mở đầu đáp án của bài.
   */
  moDapAn(bai?: number): void {
    const s = this.sach;
    if (!s?.dapAn) return;
    this.dich = chon(Math.min(trangDaDoc(s.ma, true), s.dapAn.soTrang), s.dapAn.bai, bai);
    this.trang = this.dich;
    this.dapAn = true;
  }

  /** Về trang sách, cùng cách chọn chỗ như moDapAn. */
  veSach(bai?: number): void {
    const s = this.sach;
    if (!s) return;
    this.dich = chon(Math.min(trangDaDoc(s.ma), s.soTrang), s.bai, bai);
    this.trang = this.dich;
    this.dapAn = false;
  }

  /** Nghe từ file đầu của một bài (dải tên bài, mục lục). */
  ngheBai(bai: BaiSach | MucBai): void {
    const ds = this.ngheTheoBai[bai.so];
    if (ds?.length && this.baiMo(bai.so)) this.may.chon(ds[0]);
  }
}

/** Trang nhớ dở nếu nó thuộc bài `bai` (hoặc không chỉ bài nào), không thì trang đầu của bài `bai`. */
function chon(nho: number, ds: { so: number; trang: number }[], bai?: number): number {
  if (bai === undefined || baiCuaTrang(ds, nho)?.so === bai) return nho;
  return ds.find((b) => b.so === bai)?.trang ?? nho;
}
