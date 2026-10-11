// Kiểu dữ liệu và lời gọi API của khu quản trị (04 §10).
import { api } from '$lib/api/client';
import type { Part } from '$lib/api/types';

/* ---------------- Cấp, bài, từ ---------------- */

export interface AdminLevel {
  id: number;
  name: string;
  targetWords: number;
  sampleText: string;
  visible: boolean;
  enteredWords: number;
  lessonCount: number;
  reviewPublishedOnly: boolean;
  reviewBtCount: number;
}

export type LessonCounts = { words: number; bt: number; kt: number; lv: number; ht: number; np: number };

export interface AdminLesson {
  id: number;
  no: number;
  title: string;
  sampleText: string;
  status: 'draft' | 'published';
  counts: LessonCounts;
  updatedAt: string;
  updatedByName: string | null;
}

export interface LessonsPage {
  lessons: AdminLesson[];
  kpi: { total: number; complete: number; missing: number; draft: number };
  mock: { count: number; questionCount: number; durationMin: number; passPoints: number; maxPoints: number };
}

export interface WordExample {
  id?: number;
  zh: string;
  pinyin: string;
  vi: string;
  highlight: string | null;
}

export interface AdminWord {
  id: number;
  levelId: number;
  lessonId: number | null;
  lessonNo?: number | null;
  hanzi: string;
  pinyin: string;
  hanViet: string;
  meaning: string;
  pos: string;
  note?: string | null;
  exampleCount?: number;
  examples?: WordExample[];
}

export interface DictLookup {
  found: boolean;
  entries: { pinyin: string; hanViet: string; meaning: string; pos: string }[];
  hanVietByChar: string;
  existing: { wordId: number; lessonId: number | null; lessonNo: number | null } | null;
}

/* ---------------- Câu hỏi ---------------- */

export interface AdminQuestion {
  id: number;
  type: string;
  payload: Record<string, unknown>;
  explain: string | null;
  score: number;
  sortOrder: number;
  promptText: string;
  section?: 'listening' | 'reading';
}

export interface TestConfig {
  durationSec: number;
  passScore: number;
  listenLimit: number;
  attemptsLimit: number | null;
  shuffleQuestions: boolean;
  shuffleOptions: boolean;
  autoSubmit: boolean;
  showAnswersAfter: boolean;
}

/* ---------------- Luyện viết ---------------- */

export interface AdminChar {
  lessonCharId: number;
  charId: number;
  hanzi: string;
  pinyin: string;
  tone: number;
  hanViet: string;
  meaning: string;
  strokes: number;
  structure: string;
  strokeStatus: 'ok' | 'missing' | 'custom';
  strokeDataUrl: string | null;
  visible: boolean;
  sourceWord: { hanzi: string; meaning: string } | null;
}

export interface WritingConfig {
  startMode: 'demo' | 'trace' | 'free';
  grid: 'tian' | 'mi';
  passCount: number;
  hintAfterMisses: number;
  showAnimation: boolean;
  highlightRadical: boolean;
}

/* ---------------- Hội thoại, ngữ pháp ---------------- */

export interface DialogueSpeaker {
  key: string;
  id?: number;
  name: string;
  avatarChar: string;
  color: string;
  roleVi: string;
}

export interface DialogueLine {
  id?: number;
  speakerKey: string;
  zh: string;
  pinyin: string;
  vi: string;
  highlight: string | null;
  audioPath?: string | null;
}

export interface DialogueQuestion {
  prompt: string;
  options: string[];
  answer: number;
}

export interface AdminDialogue {
  title: string;
  scene: string;
  imageUrl: string | null;
  speakers: DialogueSpeaker[];
  lines: DialogueLine[];
  questions: DialogueQuestion[];
}

export interface GrammarBlock {
  label: string;
  hz: string;
  color: string;
}

export interface GrammarExample {
  kind: string;
  formula: string;
  zh: string;
  highlight: string | null;
  pinyin: string;
  vi: string;
}

export interface GrammarPractice {
  answer: string;
  vi: string;
  tiles: string[];
}

export interface AdminGrammarPoint {
  id: number;
  title: string;
  subtitle: string;
  explain: string;
  note: string | null;
  blocks: GrammarBlock[];
  examples: GrammarExample[];
  practice: GrammarPractice | null;
  sortOrder: number;
}

/* ---------------- Lời gọi ---------------- */

export const adminApi = {
  badges: () => api.get<{ users: number; lessons: number; words: number; unreadFeedback: number }>('/admin/badges'),

  levels: () => api.get<AdminLevel[]>('/admin/levels'),
  patchLevel: (id: number, body: Partial<AdminLevel>) => api.patch(`/admin/levels/${id}`, body),

  lessons: (levelId: number) => api.get<LessonsPage>(`/admin/levels/${levelId}/lessons`),
  createLesson: (levelId: number, title: string) => api.post<{ id: number; no: number }>(`/admin/levels/${levelId}/lessons`, { title }),
  lesson: (id: number) => api.get<AdminLesson & { levelId: number }>(`/admin/lessons/${id}`),
  patchLesson: (id: number, body: { title?: string; status?: string; sampleText?: string }) => api.patch(`/admin/lessons/${id}`, body),
  deleteLesson: (id: number, confirm: string) => api.del(`/admin/lessons/${id}`, { confirm }),
  reorderLessons: (levelId: number, ids: number[]) => api.put(`/admin/levels/${levelId}/lessons/order`, { ids }),

  words: (q: Record<string, string | number>) => api.getFull<AdminWord[]>('/admin/words', { query: q }),
  word: (id: number) => api.get<AdminWord>(`/admin/words/${id}`),
  createWord: (body: Partial<AdminWord> & { examples: WordExample[] }) => api.post<AdminWord>('/admin/words', body),
  updateWord: (id: number, body: Partial<AdminWord> & { examples: WordExample[] }) => api.put<AdminWord>(`/admin/words/${id}`, body),
  deleteWord: (id: number) => api.del(`/admin/words/${id}`),
  bulkWords: (body: { levelId: number; lessonId: number | null; items: Partial<AdminWord>[] }) =>
    api.post<{ created: number; skipped: { hanzi: string; reason: string }[] }>('/admin/words/bulk', body),
  reorderWords: (lessonId: number, ids: number[]) => api.put(`/admin/lessons/${lessonId}/words/order`, { ids }),
  dict: (hanzi: string, level: number) => api.get<DictLookup>('/admin/dict', { query: { hanzi, level } }),

  questions: (lessonId: number, part: 'bt' | 'kt') =>
    api.get<{ questions: AdminQuestion[]; config?: TestConfig }>(`/admin/lessons/${lessonId}/questions`, { query: { part } }),
  createQuestion: (lessonId: number, body: Record<string, unknown>) => api.post<AdminQuestion>(`/admin/lessons/${lessonId}/questions`, body),
  updateQuestion: (id: number, body: Record<string, unknown>) => api.put<AdminQuestion>(`/admin/questions/${id}`, body),
  deleteQuestion: (id: number) => api.del(`/admin/questions/${id}`),
  reorderQuestions: (lessonId: number, part: 'bt' | 'kt', ids: number[]) => api.put(`/admin/lessons/${lessonId}/questions/order`, { part, ids }),
  copyFromBt: (lessonId: number, questionIds: number[]) =>
    api.post<{ copied: number; skipped: unknown[] }>(`/admin/lessons/${lessonId}/questions/copy-from-bt`, { questionIds }),
  saveTestConfig: (lessonId: number, body: TestConfig) => api.put<TestConfig>(`/admin/lessons/${lessonId}/test-config`, body),

  writing: (lessonId: number) => api.get<{ config: WritingConfig; chars: AdminChar[] }>(`/admin/lessons/${lessonId}/writing`),
  writingFromWords: (lessonId: number) =>
    api.post<{ added: number; strokeFetched: number; chars: AdminChar[] }>(`/admin/lessons/${lessonId}/writing/from-words`),
  addChar: (lessonId: number, hanzi: string) => api.post<{ chars: AdminChar[] }>(`/admin/lessons/${lessonId}/writing/chars`, { hanzi }),
  patchLessonChar: (id: number, body: { visible?: boolean; structure?: string }) => api.patch(`/admin/lesson-chars/${id}`, body),
  deleteLessonChar: (id: number) => api.del(`/admin/lesson-chars/${id}`),
  reorderChars: (lessonId: number, ids: number[]) => api.put(`/admin/lessons/${lessonId}/writing/order`, { ids }),
  saveWritingConfig: (lessonId: number, body: WritingConfig) => api.put<WritingConfig>(`/admin/lessons/${lessonId}/writing/config`, body),
  fetchStroke: (charId: number) => api.post<{ ok: boolean }>(`/admin/characters/${charId}/stroke-data/fetch`),

  dialogue: (lessonId: number) => api.get<AdminDialogue | null>(`/admin/lessons/${lessonId}/dialogue`),
  saveDialogue: (lessonId: number, body: Record<string, unknown>) => api.put<AdminDialogue>(`/admin/lessons/${lessonId}/dialogue`, body),

  grammar: (lessonId: number) => api.get<AdminGrammarPoint[]>(`/admin/lessons/${lessonId}/grammar`),
  createGrammar: (lessonId: number, body: Record<string, unknown>) => api.post<AdminGrammarPoint>(`/admin/lessons/${lessonId}/grammar`, body),
  updateGrammar: (id: number, body: Record<string, unknown>) => api.put<AdminGrammarPoint>(`/admin/grammar/${id}`, body),
  deleteGrammar: (id: number) => api.del(`/admin/grammar/${id}`),
  reorderGrammar: (lessonId: number, ids: number[]) => api.put(`/admin/lessons/${lessonId}/grammar/order`, { ids }),
};

/** Nhãn 6 phần dùng trong bảng Bài học HSK. */
export const PART_COL: { key: keyof LessonCounts; part: Part; label: string; short: string; unit: string }[] = [
  { key: 'words', part: 'lt', label: 'Từ vựng', short: 'TV', unit: 'từ' },
  { key: 'bt', part: 'bt', label: 'Bài tập', short: 'BT', unit: 'câu' },
  { key: 'kt', part: 'kt', label: 'Kiểm tra', short: 'KT', unit: 'câu' },
  { key: 'lv', part: 'lv', label: 'Luyện viết', short: 'LV', unit: 'chữ' },
  { key: 'ht', part: 'ht', label: 'Hội thoại', short: 'HT', unit: 'câu' },
  { key: 'np', part: 'np', label: 'Ngữ pháp', short: 'NP', unit: 'điểm' },
];

/** Đường dẫn trang soạn của từng phần. */
export const PART_SLUG: Record<Part, string> = {
  lt: 'words',
  bt: 'exercises',
  kt: 'tests',
  lv: 'writing',
  ht: 'dialogue',
  np: 'grammar',
};
