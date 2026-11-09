// Điểm mở bài giáo trình là luồng riêng: lần nhận đầu được 3 điểm, từ hôm sau mỗi ngày nhận 1;
// điểm chưa dùng giữ lại, ngày không nhận thì không bù. Link Shopee không tham gia vào việc cộng điểm.

import { api } from '$lib/api/client';
import { session } from '$lib/stores/session.svelte';

interface DiemTuServer {
  diem: number;
  /** Hôm nay đã nhận điểm. */
  homNay: boolean;
  /** Chưa nhận lần nào: lần bấm tới được 3 điểm. */
  lanDau: boolean;
}

class DiemDoc {
  diem = $state(0);
  homNay = $state(true);
  lanDau = $state(false);
  daTai = $state(false);
  #dangTai: Promise<void> | null = null;

  /** Số điểm lần nhận tới: để nút ghi "Nhận 3 điểm" hay "Nhận 1 điểm". */
  get lanToi(): number {
    return this.lanDau ? 3 : 1;
  }

  refresh(): Promise<void> {
    if (!session.can('books.points')) return Promise.resolve();
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

  /** Nút "Nhận điểm hôm nay" gọi API điểm riêng và không mở trang khác. */
  async nhan(): Promise<void> {
    this.#nap(await api.post<DiemTuServer>('/learn/points/daily'));
  }

  #nap(d: DiemTuServer): void {
    this.diem = d.diem;
    this.homNay = d.homNay;
    this.lanDau = d.lanDau;
    this.daTai = true;
  }
}

export const diemDoc = new DiemDoc();
