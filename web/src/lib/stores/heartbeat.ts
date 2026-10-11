// Heartbeat theo dõi phiên (02 §3.4):
// gửi ngay khi mở app, rồi mỗi 120 giây khi tab hiển thị; đổi phần học thì gửi sớm (≤ 1 lần / 30 giây);
// rời trang hoặc tab ẩn quá 5 phút → sendBeacon /visit/leave.
import { api, beacon, isPreview } from '$lib/api/client';
import type { Activity, Announcement } from '$lib/api/types';

const INTERVAL_MS = 120_000;
const MIN_GAP_MS = 30_000;
const HIDDEN_LEAVE_MS = 300_000;

let timer: ReturnType<typeof setInterval> | null = null;
let hiddenTimer: ReturnType<typeof setTimeout> | null = null;
let activity: Activity | null = null;
let lastSent = 0;
let pendingEarly: ReturnType<typeof setTimeout> | null = null;
let running = false;
let left = false;
let onAnnouncements: ((a: Announcement[]) => void) | null = null;

async function send(): Promise<void> {
  if (!running) return;
  lastSent = Date.now();
  left = false;
  try {
    // Chế độ xem trước của admin: không gửi activity học (07 §0).
    const res = await api.post<{ serverTime: string; announcements?: Announcement[] }>(
      '/visit/ping',
      { activity: isPreview() ? null : activity },
      { quiet: true },
    );
    if (res?.announcements) onAnnouncements?.(res.announcements);
  } catch {
    /* mất mạng: lần sau gửi lại */
  }
}

function leave(): void {
  if (!running || left) return;
  left = true;
  beacon('/visit/leave', {});
}

function onVisibility(): void {
  if (document.visibilityState === 'visible') {
    if (hiddenTimer) clearTimeout(hiddenTimer);
    hiddenTimer = null;
    if (left || Date.now() - lastSent > INTERVAL_MS) void send();
  } else {
    hiddenTimer = setTimeout(leave, HIDDEN_LEAVE_MS);
  }
}

export const heartbeat = {
  start(handler: (a: Announcement[]) => void): void {
    if (running) return;
    running = true;
    onAnnouncements = handler;
    void send();
    timer = setInterval(() => {
      if (document.visibilityState === 'visible') void send();
    }, INTERVAL_MS);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', leave);
  },

  stop(): void {
    if (!running) return;
    running = false;
    if (timer) clearInterval(timer);
    if (hiddenTimer) clearTimeout(hiddenTimer);
    if (pendingEarly) clearTimeout(pendingEarly);
    timer = hiddenTimer = pendingEarly = null;
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('pagehide', leave);
  },

  /** Mỗi màn học gọi khi mở: activity đổi → gửi sớm, nhưng không quá 1 lần / 30 giây. */
  setActivity(a: Activity): void {
    const changed = JSON.stringify(a) !== JSON.stringify(activity);
    activity = a;
    if (!changed || !running) return;
    if (pendingEarly) clearTimeout(pendingEarly);
    const wait = Math.max(0, MIN_GAP_MS - (Date.now() - lastSent));
    pendingEarly = setTimeout(() => {
      pendingEarly = null;
      void send();
    }, wait);
  },

  current(): Activity | null {
    return activity;
  },
};
