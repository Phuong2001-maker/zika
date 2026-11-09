// Kiểu dữ liệu API (04-api.md). Khoá JSON luôn camelCase.

export type Part = 'lt' | 'bt' | 'kt' | 'lv' | 'ht' | 'np';
/** Màn đang đứng, để Chuyển nhanh đánh dấu "Đang dùng": 6 phần học hoặc Thi thử HSK 3.0. */
export type ManHoc = Part | 'hsk30';
export type Role = 'learner' | 'editor' | 'admin';
export type SelfLevel = 'beginner' | 'hsk12' | 'hsk34' | 'hsk5';
export type Device = 'phone' | 'tablet' | 'desktop';

export interface PublicConfig {
  siteName: string;
  slogan: string;
  logoUrl: string | null;
  googleEnabled: boolean;
  emailEnabled: boolean;
  allowSignup: boolean;
  contactEmail: string;
  showFeedbackButton: boolean;
  csrf: string;
}

export interface User {
  id: number;
  email: string;
  displayName: string;
  fullName: string;
  initials: string;
  avatarUrl: string | null;
  role: Role;
  signupMethod: 'email' | 'google';
  hasPassword: boolean;
  selfLevel: SelfLevel;
  dailyGoalMin: number;
  currentLevelId: number | null;
  curriculumId: number | null;
  /** Cách gọi cấp của giáo trình đang học: "HSK" hoặc "Quyển". */
  levelLabel: string;
  createdAt: string;
  stats: { studySec: number; lessonsDone: number };
}

export interface UserSettings {
  voice: 'xiaoxiao' | 'yunxi' | 'browser';
  rate: number;
  autoSpeak: boolean;
  cardFront: 'vi' | 'hanzi' | 'pinyin';
  shuffle: boolean;
  showExamples: boolean;
  hanziFont: 'sans' | 'serif' | 'kai';
  fontSize: 's' | 'm' | 'l';
  toneColors: boolean;
  showHanViet: boolean;
  theme: 'light' | 'dark' | 'system';
  remindAt: string | null;
  testBeep: boolean;
  dialogue: { showPinyin: boolean; showVi: boolean };
}

export interface Announcement {
  id: number;
  kind: 'banner' | 'modal';
  title: string;
  body: string;
  ctaText: string | null;
  ctaRoute: string | null;
}

/** Một dòng trong chuông thông báo (GET /me/notifications). */
export interface InboxItem {
  id: number;
  title: string;
  body: string;
  ctaText: string | null;
  ctaRoute: string | null;
  at: string;
  read: boolean;
}

export interface Inbox {
  items: InboxItem[];
  unread: number;
  total: number;
}

export interface MeResponse {
  features?: Record<string, boolean>;
  activeTests?: import('$lib/auth/features').ActiveTest[];
  user: User;
  settings: UserSettings;
  csrf: string;
  support: { enabled: boolean };
  announcements: Announcement[];
}

export interface Activity {
  part: Part | null;
  screen: 'home' | 'level' | 'do' | 'review' | 'account' | 'admin' | 'book' | 'chat';
  level?: number;
  lessonId?: number;
  lessonNo?: number;
  mockId?: number;
  /** Mã quyển giáo trình đang đọc (hn3, hsk1) — để quản trị thấy "Hán ngữ 3" chứ không phải "HSK 3". */
  book?: string;
}

/* ---------------- Nội dung học ---------------- */

export interface LevelInfo {
  id: number;
  name: string;
  visible: boolean;
  sampleText: string;
  targetWords: number;
  lessonCount: number;
  wordCount: number;
}

export type PartCounts = Record<Part, number>;

export interface LessonItem {
  id: number;
  no: number;
  title: string;
  sampleText: string;
  counts: PartCounts;
  sub: Partial<Record<Part, string>>;
  status?: 'draft' | 'published';
}

export interface MockTestInfo {
  id: number;
  title: string;
  questionCount: number;
  durationSec: number;
  maxPoints: number;
  passPoints: number;
}

export interface LevelLessons {
  level: { id: number; name: string; wordCount: number; visible?: boolean };
  lessons: LessonItem[];
  review: {
    lt: { wordCount: number };
    bt: { count: number; pool: number };
    lv: { charCount: number };
    ht: { dialogueCount: number };
    np: { pointCount: number };
    kt: { mockTests: MockTestInfo[] };
  };
}

export interface LessonRef {
  id: number;
  no: number;
  title: string;
  levelId: number;
  status?: 'draft' | 'published';
}

export interface NextLesson {
  id: number;
  no: number;
  title: string;
  wordCount?: number;
  preview?: { hanzi: string; meaning: string }[];
}

export interface Example {
  zh: string;
  highlight: string | null;
  pinyin: string;
  vi: string;
  audioUrl: string | null;
}

export interface Word {
  id: number;
  hanzi: string;
  pinyin: string;
  hanViet: string;
  meaning: string;
  /** Cột tiếng Anh của bảng 生词 khi sách in (giáo trình Hán ngữ); HSK không có. */
  meaningEn?: string | null;
  pos: string;
  audioUrl: string | null;
  examples: Example[];
  lessonId?: number;
  lessonNo?: number;
}

export interface FlashcardsData {
  lesson: LessonRef;
  words: Word[];
  next: NextLesson | null;
}

export interface ReviewWords {
  level: { id: number; name: string };
  words: Word[];
}

/* ---------------- Tiến độ ---------------- */

export interface ProgressRow {
  lessonId: number;
  status: 'in_progress' | 'done';
  position: number;
  total: number;
  bestScore: number | null;
  lastScore: number | null;
  maxScore: number | null;
  timesDone: number;
  state: Record<string, unknown> | null;
  updatedAt: string;
  passed?: boolean;
  openAttempt?: { id: number; deadlineAt: string } | null;
}

export interface ReviewProgress {
  position: number;
  total: number;
  timesDone: number;
  state: Record<string, unknown> | null;
  updatedAt: string | null;
}

export interface LevelProgress {
  lessons: ProgressRow[];
  review: ReviewProgress;
  doneCount: number;
  lessonCount: number;
}

export interface HomeProgress {
  currentLevelId: number | null;
  levels: { levelId: number; done: number; total: number }[];
  continue: {
    levelId: number;
    lessonId: number;
    lessonNo: number;
    title: string;
    sampleText: string;
    position: number;
    total: number;
    updatedAt: string | null;
    extra?: Record<string, unknown>;
  } | null;
  reviewSuggest: { levelId: number; done: number; total: number } | null;
  /** Khối “Hôm nay”: chuỗi ngày học, 7 ngày gần nhất (cuối mảng = hôm nay), số từ đã lật xong. */
  today?: { streak: number; week: boolean[]; words: number };
}

export interface CompleteResult {
  firstTime: boolean;
  levelDone: { done: number; total: number };
  lessonsDone: number;
}

/* ---------------- Ủng hộ ---------------- */

export interface SupportBlock {
  enabled: boolean;
  links?: { id: number; name: string; imageUrl: string | null; goUrl: string }[];
}
