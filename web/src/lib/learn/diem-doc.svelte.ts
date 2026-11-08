// Điểm mở bài giáo trình. Lần đầu bấm bé túi (hoặc link Shopee) được 3 điểm, từ hôm sau mỗi ngày bấm được 1;
// điểm chưa dùng giữ lại, ngày không bấm thì không bù. Server cộng điểm ngay ở lối /go nên web chỉ đọc lại số.

import { api } from '$lib/api/client';

interface DiemTuServer {
  diem: number;
  /** Hôm nay đã nhận điểm (đã bấm link). */
  homNay: boolean;
  /** Chưa nhận lần nào: lần bấm tới được 3 điểm. */
  lanDau: boolean;
  /** Link /go để nhận điểm; null khi quản trị chưa gắn link Shopee nào — nút nhận gọi thẳng API. */
  link: string | null;
}

class DiemDoc {
  diem = $state(0);
  homNay = $state(true);
  lanDau = $state(false);
  link = $state<string | null>(null);
  daTai = $state(false);
  #dangTai: Promise<void> | null = null;

  /** Số điểm lần nhận tới: để nút ghi "Nhận 3 điểm" hay "Nhận 1 điểm". */
  get lanToi(): number {
    return this.lanDau ? 3 : 1;
  }

  refresh(): Promise<void> {
    this.#dangTai ??= api
      .get<DiemTuServer>('/learn/points', { quiet: true })
      .then((d) => this.#nap(d))
      .catch(() => {
        // Phần phụ: lỗi mạng thì giữ số cũ.
      })
      .finally(() => {
        this.#dangTai = null;
      });
    return this.#dangTai;
  }

  /**
   * Nút "Nhận điểm hôm nay": mở link Shopee ở tab mới (server cộng điểm khi đi qua /go), rồi đọc lại số điểm. Trên
   * điện thoại link mở app Shopee; quay lại web thì sự kiện focus/visibility gọi refresh lần nữa.
   */
  async nhan(): Promise<void> {
    if (this.link) {
      window.open(this.link, '_blank', 'noopener');
      await new Promise((r) => setTimeout(r, 1500));
      await this.refresh();
      return;
    }
    this.#nap(await api.post<DiemTuServer>('/learn/points/daily'));
  }

  #nap(d: DiemTuServer): void {
    this.diem = d.diem;
    this.homNay = d.homNay;
    this.lanDau = d.lanDau;
    this.link = d.link;
    this.daTai = true;
  }
}

export const diemDoc = new DiemDoc();
