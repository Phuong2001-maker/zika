import { api } from '$lib/api/client';

export interface SupportLink {
  id: number;
  name: string;
  /** Đường /go/{id}: server đếm lượt bấm rồi mới chuyển sang Shopee. */
  url: string;
}

export interface MonthlyFund {
  /** Admin tự nhập; null là chưa nhập, giao diện hiện "Đang cập nhật". */
  amount: number | null;
  month: string;
  updatedOn: string | null;
}

interface SupportData {
  icon: SupportLink | null;
  fund: SupportLink[];
  monthlyFund: MonthlyFund;
  /** Hôm nay đã bấm link (đã nhận điểm mở bài): bé túi nghỉ đến 0h hôm sau. */
  claimedToday: boolean;
}

/** Link Shopee và tiền quỹ phía người học. Bé túi và thanh đầu trang dùng chung, không gọi API hai lần. */
class SupportLinks {
  icon = $state<SupportLink | null>(null);
  fund = $state<SupportLink[]>([]);
  monthlyFund = $state<MonthlyFund>({ amount: null, month: '', updatedOn: null });
  claimedToday = $state(true);
  private loading: Promise<void> | null = null;

  refresh(): Promise<void> {
    this.loading ??= this.load().finally(() => {
      this.loading = null;
    });
    return this.loading;
  }

  private async load(): Promise<void> {
    try {
      const data = await api.get<SupportData>('/support/links', { quiet: true });
      this.icon = data.icon;
      this.fund = data.fund;
      this.monthlyFund = data.monthlyFund;
      this.claimedToday = data.claimedToday;
    } catch {
      // Đây là phần phụ: lỗi mạng thì giữ nguyên trạng thái, không làm phiền người đang học.
    }
  }

  logView(place: 'icon' | 'fund'): void {
    void api.post('/support/view', { place }, { quiet: true }).catch(() => {});
  }
}

export const supportLinks = new SupportLinks();
