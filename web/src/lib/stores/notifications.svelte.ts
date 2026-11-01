// Chuông thông báo của học viên: thông báo admin soạn ở /admin/news.
// Số chưa đọc đi theo nhịp ping của heartbeat (2 phút/lần); danh sách chỉ tải khi mở chuông.
import { api } from '$lib/api/client';
import type { Inbox, InboxItem } from '$lib/api/types';

class Notifications {
  items = $state<InboxItem[]>([]);
  unread = $state(0);
  total = $state(0);
  dangTai = $state(false);
  daTai = $state(false);

  private nhan(r: Inbox): void {
    this.items = r.items;
    this.unread = r.unread;
    this.total = r.total;
    this.daTai = true;
  }

  async tai(): Promise<void> {
    this.dangTai = true;
    try {
      this.nhan(await api.get<Inbox>('/me/notifications', { quiet: true }));
    } catch {
      /* mất mạng: giữ danh sách cũ */
    } finally {
      this.dangTai = false;
    }
  }

  /** Số chưa đọc từ ping khác số đang giữ nghĩa là có thông báo mới: lần mở chuông sau phải tải lại. */
  dongBo(n: number): void {
    if (n !== this.unread) this.daTai = false;
    this.unread = n;
  }

  async docHet(): Promise<void> {
    this.items = this.items.map((x) => ({ ...x, read: true }));
    this.unread = 0;
    try {
      this.nhan(await api.post<Inbox>('/me/notifications/read-all', {}, { quiet: true }));
    } catch {
      /* lần ping sau sẽ trả lại số đúng */
    }
  }

  docMot(id: number): void {
    const x = this.items.find((i) => i.id === id);
    if (!x || x.read) return;
    x.read = true;
    this.unread = Math.max(0, this.unread - 1);
    void api.post(`/announcements/${id}/seen`, {}, { quiet: true }).catch(() => {});
  }

  xoa(): void {
    this.items = [];
    this.unread = 0;
    this.total = 0;
    this.daTai = false;
  }
}

export const notifications = new Notifications();
