// Máy nghe file ghi âm đi kèm giáo trình: một thẻ <audio> dùng chung cho cả quyển, trạng thái để runes nên thanh
// phát ở máy tính và điện thoại đọc cùng một chỗ.

import { urlNghe, type BaiNghe } from '$lib/learn/sach';

/** Tốc độ chọn được. 0,75× là mức người học hay dùng nhất khi nghe bài khoá lần đầu. */
export const TOC_DO = [0.5, 0.75, 1, 1.25] as const;
const KHOA_TOC = 'zk.sach.toc-do';

export class MayNghe {
  ma = $state('');
  phien = $state('');
  tenSach = $state('');
  /** Mã ký của quyển, gắn vào đường dẫn file nghe (server từ chối đường dẫn không có mã hợp lệ). */
  ve = $state('');
  /** Chỉ các file của bài đã mở. */
  ds = $state<BaiNghe[]>([]);
  /** File đang mở trên thanh phát; null = thanh phát đóng. */
  dang = $state<BaiNghe | null>(null);
  phat = $state(false);
  dangTai = $state(false);
  loi = $state(false);
  hienTai = $state(0);
  tongGiay = $state(0);
  tocDo = $state(1);
  lap = $state(false);

  /** Lấy mã ký mới khi file lỗi giữa chừng (mã hết hạn lúc tạm dừng lâu); null = không lấy được. */
  lamMoiVe: (() => Promise<string | null>) | null = null;
  #audio: HTMLAudioElement | null = null;
  #daThuLai = false;

  constructor() {
    try {
      const v = Number(localStorage.getItem(KHOA_TOC));
      if ((TOC_DO as readonly number[]).includes(v)) this.tocDo = v;
    } catch {
      // Không đọc được localStorage thì dùng 1×.
    }
  }

  /** Vị trí file đang phát trong danh sách cả quyển (-1 khi chưa phát gì). */
  get viTri(): number {
    return this.dang ? this.ds.findIndex((b) => b.so === this.dang?.so) : -1;
  }

  napSach(ma: string, phien: string, tenSach: string): void {
    if (this.ma !== ma) this.dong();
    this.ma = ma;
    this.phien = phien;
    this.tenSach = tenSach;
  }

  /** Mã ký mới hoặc danh sách file đọc được đổi (vừa mở bài): không ngắt file đang phát. */
  capNhat(ve: string, ds: BaiNghe[]): void {
    this.ve = ve;
    this.ds = ds;
  }

  /** Mở một file lên thanh phát; bấm lại đúng file đang mở thì chỉ phát tiếp. */
  chon(bai: BaiNghe, phatLuon = true): void {
    const a = this.#el();
    if (this.dang?.so !== bai.so) {
      this.dang = bai;
      this.hienTai = 0;
      this.tongGiay = bai.giay;
      this.loi = false;
      a.src = urlNghe(this, this.ve, bai.so);
      this.#daThuLai = false;
      a.playbackRate = this.tocDo;
      a.loop = this.lap;
      this.#manHinhKhoa();
    }
    if (phatLuon) this.choi();
  }

  choi(): void {
    const a = this.#el();
    if (!this.dang) return;
    this.dangTai = true;
    a.play().catch(() => {
      this.dangTai = false;
    });
  }

  tamDung(): void {
    this.#audio?.pause();
  }

  batTat(): void {
    if (this.phat) this.tamDung();
    else this.choi();
  }

  tua(giay: number): void {
    this.den(this.hienTai + giay);
  }

  den(giay: number): void {
    const a = this.#audio;
    if (!a || !this.dang) return;
    const het = Math.max(this.tongGiay, Number.isFinite(a.duration) ? a.duration : 0);
    a.currentTime = Math.min(Math.max(0, giay), Math.max(0, het - 0.05));
    this.hienTai = a.currentTime;
  }

  doiTocDo(): void {
    const i = (TOC_DO as readonly number[]).indexOf(this.tocDo);
    this.tocDo = TOC_DO[(i + 1) % TOC_DO.length];
    if (this.#audio) this.#audio.playbackRate = this.tocDo;
    try {
      localStorage.setItem(KHOA_TOC, String(this.tocDo));
    } catch {
      // Bỏ qua: lần sau về 1×.
    }
  }

  doiLap(): void {
    this.lap = !this.lap;
    if (this.#audio) this.#audio.loop = this.lap;
  }

  /** Giống máy nghe nhạc: đang nghe quá 3 giây thì về đầu file, không thì lùi một file. */
  truoc(): void {
    if (this.hienTai > 3 || this.viTri <= 0) {
      this.den(0);
      return;
    }
    this.chon(this.ds[this.viTri - 1]);
  }

  sau(): void {
    const i = this.viTri;
    if (i >= 0 && i < this.ds.length - 1) this.chon(this.ds[i + 1]);
  }

  /** Đóng thanh phát và nhả file (rời màn đọc, đổi quyển). */
  dong(): void {
    const a = this.#audio;
    if (a) {
      a.pause();
      a.removeAttribute('src');
      a.load();
    }
    this.dang = null;
    this.phat = false;
    this.dangTai = false;
    this.hienTai = 0;
    if (typeof navigator !== 'undefined' && 'mediaSession' in navigator) navigator.mediaSession.metadata = null;
  }

  #el(): HTMLAudioElement {
    if (this.#audio) return this.#audio;
    const a = new Audio();
    a.preload = 'metadata';
    a.addEventListener('timeupdate', () => (this.hienTai = a.currentTime));
    // Thời lượng lấy từ sach.json (script đếm từng frame). File MP3 bitrate thay đổi mà thiếu header Xing (cả bộ
    // Hán ngữ 6) thì trình duyệt đoán sai tới 15% — 510 giây cho file 595 giây — nên chỉ dùng số của trình duyệt khi
    // sach.json không có.
    a.addEventListener('durationchange', () => {
      if (!this.dang?.giay && Number.isFinite(a.duration) && a.duration > 0) this.tongGiay = a.duration;
    });
    a.addEventListener('play', () => (this.phat = true));
    a.addEventListener('pause', () => (this.phat = false));
    a.addEventListener('waiting', () => (this.dangTai = true));
    a.addEventListener('playing', () => {
      this.dangTai = false;
      this.loi = false;
      this.#daThuLai = false;
    });
    a.addEventListener('canplay', () => (this.dangTai = false));
    a.addEventListener('error', () => {
      if (!a.getAttribute('src')) return;
      // Lỗi đầu tiên của một file: thường là mã ký đã hết hạn sau khi tạm dừng lâu. Lấy mã mới, phát tiếp đúng chỗ.
      const dang = this.dang;
      if (dang && !this.#daThuLai && this.lamMoiVe) {
        this.#daThuLai = true;
        const luc = this.hienTai;
        void this.lamMoiVe().then((ve) => {
          if (!ve || this.dang?.so !== dang.so) return;
          this.ve = ve;
          a.src = urlNghe(this, ve, dang.so);
          a.currentTime = luc;
          this.choi();
        });
        return;
      }
      this.loi = true;
      this.dangTai = false;
      this.phat = false;
    });
    a.addEventListener('ended', () => this.#het());
    this.#audio = a;
    return a;
  }

  /** Hết file: nghe tiếp phần sau của cùng bài (Từ mới → Bài khoá → Luyện tập), sang bài khác thì dừng. */
  #het(): void {
    const i = this.viTri;
    const tiep = this.ds[i + 1];
    if (i >= 0 && tiep && tiep.bai === this.dang?.bai) this.chon(tiep);
  }

  /** Tên file và nút điều khiển trên màn hình khoá / thanh thông báo của điện thoại. */
  #manHinhKhoa(): void {
    if (typeof navigator === 'undefined' || !('mediaSession' in navigator) || !this.dang) return;
    const ms = navigator.mediaSession;
    ms.metadata = new MediaMetadata({ title: `Bài ${this.dang.bai} · ${this.dang.ten}`, artist: this.tenSach, album: 'Zìkǎ' });
    const gan = (ten: MediaSessionAction, fn: () => void) => {
      try {
        ms.setActionHandler(ten, fn);
      } catch {
        // Trình duyệt chưa hỗ trợ nút này.
      }
    };
    gan('play', () => this.choi());
    gan('pause', () => this.tamDung());
    gan('previoustrack', () => this.truoc());
    gan('nexttrack', () => this.sau());
    gan('seekbackward', () => this.tua(-5));
    gan('seekforward', () => this.tua(5));
  }
}
