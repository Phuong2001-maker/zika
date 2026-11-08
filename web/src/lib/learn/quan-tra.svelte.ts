// Quán trà — phòng trò chuyện chia bàn theo chủ đề. Trạng thái một bàn tách riêng để bố cục máy tính và điện thoại
// dùng chung: kéo cửa sổ qua 768px không mất chữ đang gõ, không tải lại tin.
//
// Hosting không có WebSocket nên hỏi tin mới mỗi 4 giây, chỉ khi tab đang hiện (ẩn tab thì ngừng, mở lại hỏi ngay).

import { api, ApiError } from '$lib/api/client';

export interface TinNhan {
  id: number;
  user: { id: number; name: string; initials: string; avatarUrl: string | null; staff: boolean };
  body: string;
  deleted: boolean;
  mine: boolean;
  createdAt: string;
  replyTo: { id: number; name: string; body: string; deleted: boolean } | null;
  /** Chỉ biên tập/quản trị thấy. */
  reports?: number;
}

export interface Ban {
  id?: number;
  slug: string;
  name: string;
  description: string;
  icon: string;
  open: boolean;
  sortOrder?: number;
  /** Tin mới nhất của bàn (0 = chưa có tin) — so với tin đã xem để biết bàn có tin chưa đọc. */
  lastId?: number;
  last?: { name: string; body: string; at: string } | null;
  /** Số tin 24 giờ qua. */
  today?: number;
}

export const taiCacBan = () => api.get<{ rooms: Ban[]; open: boolean; mutedUntil: string | null }>('/chat', { quiet: true });

// Tin cuối đã xem của từng bàn, chỉ lưu ở máy này: đủ để đánh dấu "có tin mới", không đáng thêm bảng.
const KHOA_DA_XEM = 'zk.quan-tra.';
export function daXem(slug: string): number {
  try {
    return Number(localStorage.getItem(KHOA_DA_XEM + slug)) || 0;
  } catch {
    return 0;
  }
}
function ghiDaXem(slug: string, id: number): void {
  try {
    if (id > daXem(slug)) localStorage.setItem(KHOA_DA_XEM + slug, String(id));
  } catch {
    // Chế độ riêng tư: bỏ qua.
  }
}

interface PhanHoi {
  room: Ban;
  messages: TinNhan[];
  deleted: number[];
  now: string;
  open: boolean;
  mutedUntil: string | null;
  staff: boolean;
}

const CHU_KY = 4000;

export class QuanTra {
  ban = $state<Ban | null>(null);
  tin = $state<TinNhan[]>([]);
  daTai = $state(false);
  loi = $state('');
  mo = $state(true);
  /** null: không bị cấm · '': cấm vĩnh viễn · ISO: cấm đến mốc đó. */
  biCam = $state<string | null>(null);
  quanTri = $state(false);
  /** Còn tin cũ hơn để tải. */
  conCu = $state(true);
  dangTaiCu = $state(false);
  noiDung = $state('');
  traLoi = $state<TinNhan | null>(null);
  dangGui = $state(false);
  /** Số tin mới đến khi người đọc đang cuộn lên trên — hiện nút "Tin mới". */
  tinMoiChuaXem = $state(0);

  #moc = '';
  #dongHo: ReturnType<typeof setInterval> | null = null;
  /** Màn đọc báo người dùng có đang ở cuối danh sách không, để tự cuộn khi có tin mới. */
  oCuoi: () => boolean = () => true;
  cuonXuong: () => void = () => {};

  constructor(readonly slug: string) {}

  get idCuoi(): number {
    return this.tin.at(-1)?.id ?? 0;
  }

  get #duong(): string {
    return `/chat/ban/${this.slug}`;
  }

  async batDau(): Promise<void> {
    await this.taiDau();
    this.#dongHo = setInterval(() => {
      if (document.visibilityState === 'visible') void this.hoiMoi();
    }, CHU_KY);
  }

  dung(): void {
    if (this.#dongHo) clearInterval(this.#dongHo);
    this.#dongHo = null;
  }

  async taiDau(): Promise<void> {
    this.loi = '';
    try {
      const r = await api.get<PhanHoi>(this.#duong, { quiet: true });
      this.#nap(r);
      this.tin = r.messages;
      this.conCu = r.messages.length >= 50;
      this.daTai = true;
      queueMicrotask(() => this.cuonXuong());
    } catch {
      this.loi = 'Chưa vào được Quán trà. Kiểm tra mạng rồi thử lại.';
    }
  }

  async hoiMoi(): Promise<void> {
    if (!this.daTai) return;
    try {
      const r = await api.get<PhanHoi>(this.#duong, { quiet: true, query: { after: this.idCuoi, since: this.#moc } });
      const cuoi = this.oCuoi();
      this.#nap(r);
      if (r.deleted.length) {
        this.tin = this.tin.map((t) => (r.deleted.includes(t.id) ? { ...t, deleted: true, body: '' } : t));
      }
      const moi = r.messages.filter((m) => !this.tin.some((t) => t.id === m.id));
      if (moi.length) {
        this.tin = [...this.tin, ...moi];
        if (cuoi || moi.every((m) => m.mine)) queueMicrotask(() => this.cuonXuong());
        else this.tinMoiChuaXem += moi.length;
      }
    } catch {
      // Mất mạng chốc lát: lần hỏi sau tự thử lại.
    }
  }

  async taiCu(): Promise<void> {
    const dau = this.tin[0]?.id;
    if (!dau || this.dangTaiCu) return;
    this.dangTaiCu = true;
    try {
      const r = await api.get<PhanHoi>(this.#duong, { quiet: true, query: { before: dau } });
      this.tin = [...r.messages, ...this.tin];
      this.conCu = r.messages.length >= 50;
    } finally {
      this.dangTaiCu = false;
    }
  }

  async gui(): Promise<void> {
    const body = this.noiDung.trim();
    if (!body || this.dangGui) return;
    this.dangGui = true;
    this.loi = '';
    try {
      await api.post(this.#duong, { body, replyTo: this.traLoi?.id ?? null });
      this.noiDung = '';
      this.traLoi = null;
      await this.hoiMoi();
      queueMicrotask(() => this.cuonXuong());
    } catch (e) {
      this.loi = e instanceof ApiError ? e.message : 'Chưa gửi được, thử lại nhé.';
    } finally {
      this.dangGui = false;
    }
  }

  async xoa(t: TinNhan): Promise<void> {
    try {
      await api.del(`/chat/tin/${t.id}`);
      this.tin = this.tin.map((x) => (x.id === t.id ? { ...x, deleted: true, body: '' } : x));
    } catch (e) {
      this.loi = e instanceof ApiError ? e.message : 'Chưa xoá được tin.';
    }
  }

  async baoCao(t: TinNhan, lyDo: string): Promise<boolean> {
    try {
      await api.post(`/chat/tin/${t.id}/report`, { reason: lyDo });
      return true;
    } catch (e) {
      this.loi = e instanceof ApiError ? e.message : 'Chưa gửi được báo cáo.';
      return false;
    }
  }

  /** Đang đọc bàn này: tin cuối hiện trên màn coi như đã xem. */
  ghiDaXem(): void {
    if (this.idCuoi) ghiDaXem(this.slug, this.idCuoi);
  }

  #nap(r: PhanHoi): void {
    this.ban = r.room;
    this.#moc = r.now;
    this.mo = r.open;
    this.biCam = r.mutedUntil;
    this.quanTri = r.staff;
  }
}
