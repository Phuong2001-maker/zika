// Cài đặt học (03 §4.1): lưu localStorage để áp dụng ngay khi mở app, đồng bộ server khi bấm “Lưu cài đặt”.
import type { UserSettings } from '$lib/api/types';
import { loadFont } from '$lib/utils/fonts';

export const DEFAULT_SETTINGS: UserSettings = {
  voice: 'xiaoxiao',
  rate: 1,
  autoSpeak: true,
  cardFront: 'vi',
  shuffle: true,
  showExamples: true,
  hanziFont: 'sans',
  fontSize: 'm',
  toneColors: true,
  showHanViet: true,
  theme: 'light',
  remindAt: '20:00',
  testBeep: true,
  dialogue: { showPinyin: true, showVi: true },
};

const KEY = 'zk.settings';

function readLocal(): UserSettings {
  try {
    const raw = localStorage.getItem(KEY);
    if (raw) return { ...DEFAULT_SETTINGS, ...JSON.parse(raw) };
  } catch {
    /* localStorage bị chặn */
  }
  return { ...DEFAULT_SETTINGS };
}

class SettingsStore {
  value = $state<UserSettings>(typeof window === 'undefined' ? { ...DEFAULT_SETTINGS } : readLocal());
  /** Trang quản trị luôn sáng (05 §2). */
  forceLight = $state(false);

  /** Nhận bản đã chuẩn hoá từ server (GET /me, PUT /me/settings). */
  set(s: UserSettings): void {
    this.value = { ...DEFAULT_SETTINGS, ...s, dialogue: { ...DEFAULT_SETTINGS.dialogue, ...(s.dialogue ?? {}) } };
    try {
      localStorage.setItem(KEY, JSON.stringify(this.value));
    } catch {
      /* bỏ qua */
    }
    this.apply();
  }

  /** Gắn thuộc tính lên <html>: data-theme, data-hz-font, data-hz-size, class no-tone. */
  apply(s: UserSettings = this.value): void {
    if (typeof document === 'undefined') return;
    const el = document.documentElement;
    const dark = s.theme === 'dark' || (s.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    el.dataset.theme = this.forceLight ? 'light' : dark ? 'dark' : 'light';
    if (s.hanziFont === 'sans') delete el.dataset.hzFont;
    else el.dataset.hzFont = s.hanziFont;
    if (s.fontSize === 'm') delete el.dataset.hzSize;
    else el.dataset.hzSize = s.fontSize;
    el.classList.toggle('no-tone', !s.toneColors);
    if (s.hanziFont === 'kai') loadFont('Ma Shan Zheng');
    if (s.hanziFont === 'serif') loadFont('Noto Serif SC');
  }

  setForceLight(on: boolean): void {
    if (this.forceLight === on) return;
    this.forceLight = on;
    this.apply();
  }
}

export const settings = new SettingsStore();
