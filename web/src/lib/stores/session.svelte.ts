// Phiên đăng nhập: { user, csrf, loaded } — nạp bằng GET /public/config rồi GET /me khi mở app (02 §3.2).
import { api, ApiError, setCsrf } from '$lib/api/client';
import type { Announcement, MeResponse, PublicConfig, User } from '$lib/api/types';
import { settings } from './settings.svelte';

class Session {
  config = $state<PublicConfig | null>(null);
  user = $state<User | null>(null);
  loaded = $state(false);
  supportEnabled = $state(false);
  announcements = $state<Announcement[]>([]);
  /** Đã mở bảng Chuyển nhanh ở Bàn học trong phiên này chưa (mở sẵn lần đầu, 05 §7.2). */
  switcherIntroShown = $state(false);

  get isStaff(): boolean {
    return this.user?.role === 'editor' || this.user?.role === 'admin';
  }

  get isAdmin(): boolean {
    return this.user?.role === 'admin';
  }

  async init(): Promise<void> {
    try {
      const cfg = await api.get<PublicConfig>('/public/config', { quiet: true });
      this.config = cfg;
      setCsrf(cfg.csrf);
    } catch {
      // Không tải được cấu hình: vẫn thử /me; các form sẽ báo lỗi mạng khi gửi.
    }
    await this.refresh();
    this.loaded = true;
  }

  /** Tải lại /me (sau đăng nhập, đổi thông tin…). Trả user hoặc null. */
  async refresh(): Promise<User | null> {
    try {
      const me = await api.get<MeResponse>('/me', { quiet: true });
      this.applyMe(me);
    } catch (e) {
      if (e instanceof ApiError && (e.status === 401 || e.status === 403)) this.user = null;
      else if (!(e instanceof ApiError)) throw e;
    }
    return this.user;
  }

  applyMe(me: MeResponse): void {
    this.user = me.user;
    setCsrf(me.csrf);
    this.supportEnabled = me.support.enabled;
    this.announcements = me.announcements;
    settings.set(me.settings);
  }

  /** Sau đăng nhập/đăng ký/xác nhận email thành công. */
  async signedIn(csrf: string): Promise<void> {
    setCsrf(csrf);
    await this.refresh();
  }

  setUser(u: User): void {
    this.user = u;
  }

  clear(): void {
    this.user = null;
    this.announcements = [];
    this.supportEnabled = false;
  }
}

export const session = new Session();
