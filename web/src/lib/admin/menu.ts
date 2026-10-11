// Menu khu quản trị (08 §1.1): thứ tự, quyền, icon, số hiển thị bên phải.
import type { Part } from '$lib/api/types';
import { PART } from '$lib/parts';

export interface AdminBadges {
  users: number;
  lessons: number;
  words: number;
  unreadFeedback: number;
}

export interface MenuItem {
  key: string;
  label: string;
  href: string;
  icon: string;
  /** 'A' = chỉ quản trị, 'E' = biên tập + quản trị. */
  role: 'A' | 'E';
  /** Số xám bên phải. */
  count?: (b: AdminBadges) => number;
  /** Huy hiệu đỏ (góp ý chưa đọc). */
  badge?: (b: AdminBadges) => number;
  /** Ô màu thay cho icon đơn sắc, dùng cho nhóm phần học. */
  tile?: string;
  /** Phần học tương ứng, để trang soạn bài làm sáng đúng mục. */
  part?: Part;
}

export interface MenuGroup {
  title: string;
  items: MenuItem[];
}

/**
 * @param lessonId bài admin mở gần nhất — các mục soạn theo bài trỏ thẳng vào đó.
 */
export function menuGroups(lessonId: number | null): MenuGroup[] {
  const lesson = (slug: string): string => (lessonId ? `/admin/lessons/${lessonId}/${slug}` : '/admin/lessons');

  return [
    {
      title: 'Theo dõi',
      items: [
        { key: 'overview', label: 'Tổng quan', href: '/admin', icon: 'chart', role: 'A' },
        { key: 'users', label: 'Người dùng', href: '/admin/users', icon: 'users', role: 'A', count: (b) => b.users },
        { key: 'sessions', label: 'Phiên truy cập', href: '/admin/sessions', icon: 'clock', role: 'A' },
      ],
    },
    {
      title: 'Nội dung',
      items: [
        { key: 'lessons', label: 'Bài học HSK', href: '/admin/lessons', icon: 'cards', role: 'E', count: (b) => b.lessons },
        { key: 'news', label: 'Thông báo', href: '/admin/news', icon: 'megaphone', role: 'A' },
        { key: 'feedback', label: 'Góp ý', href: '/admin/feedback', icon: 'msg', role: 'E', badge: (b) => b.unreadFeedback },
      ],
    },
    {
      title: 'Phần học · soạn theo bài',
      items: [
        { key: 'words', label: 'Từ vựng · Lật thẻ', href: '/admin/words', icon: PART.lt.icon, tile: PART.lt.accent, role: 'E', count: (b) => b.words, part: 'lt' },
        { key: 'exercises', label: 'Bài tập', href: lesson('exercises'), icon: PART.bt.icon, tile: PART.bt.accent, role: 'E', part: 'bt' },
        { key: 'tests', label: 'Kiểm tra', href: lesson('tests'), icon: PART.kt.icon, tile: PART.kt.accent, role: 'E', part: 'kt' },
        { key: 'writing', label: 'Luyện viết', href: lesson('writing'), icon: PART.lv.icon, tile: PART.lv.accent, role: 'E', part: 'lv' },
        { key: 'dialogue', label: 'Hội thoại', href: lesson('dialogue'), icon: PART.ht.icon, tile: PART.ht.accent, role: 'E', part: 'ht' },
        { key: 'grammar', label: 'Ngữ pháp', href: lesson('grammar'), icon: PART.np.icon, tile: PART.np.accent, role: 'E', part: 'np' },
      ],
    },
    { title: 'Kinh phí', items: [{ key: 'support', label: 'Ủng hộ & Shopee', href: '/admin/support', icon: 'cup', role: 'A' }] },
    { title: 'Hệ thống', items: [{ key: 'settings', label: 'Cài đặt', href: '/admin/settings', icon: 'gear', role: 'A' }] },
  ];
}

/** Mục đang ở, suy ra từ đường dẫn hiện tại. */
export function activeKey(path: string): string {
  const m = path.match(/^\/admin\/lessons\/\d+\/([a-z]+)/);
  if (m) return m[1];
  if (path.startsWith('/admin/users')) return 'users';
  if (path.startsWith('/admin/sessions')) return 'sessions';
  if (path.startsWith('/admin/lessons')) return 'lessons';
  if (path.startsWith('/admin/words')) return 'words';
  if (path.startsWith('/admin/mock-tests')) return 'tests';
  if (path.startsWith('/admin/news')) return 'news';
  if (path.startsWith('/admin/feedback')) return 'feedback';
  if (path.startsWith('/admin/support')) return 'support';
  if (path.startsWith('/admin/settings')) return 'settings';
  return 'overview';
}

/** Bài admin mở gần nhất — nhớ giữa các phiên để menu trỏ đúng chỗ (08 §1.1). */
const LAST_LESSON = 'zk.admin.lastLesson';

export function rememberLesson(id: number): void {
  try {
    localStorage.setItem(LAST_LESSON, String(id));
  } catch {
    // Trình duyệt chặn localStorage (chế độ riêng tư): bỏ qua, menu dùng mặc định.
  }
}

export function lastLesson(): number | null {
  try {
    const v = Number(localStorage.getItem(LAST_LESSON));
    return v > 0 ? v : null;
  } catch {
    return null;
  }
}
