// Tiện ích dùng chung cho các màn học: đổi “số bài” trên URL thành id thật,
// và lấy tiến độ của một phần.
import { api } from '$lib/api/client';
import type { LevelLessons, LessonItem, Part, ProgressRow, LevelProgress } from '$lib/api/types';

/** Bộ nhớ tạm trong phiên: map (cấp → danh sách bài) để không gọi lại mỗi lần đổi bài. */
const cache = new Map<number, LevelLessons>();

export async function levelLessons(levelId: number, fresh = false): Promise<LevelLessons> {
  if (!fresh) {
    const hit = cache.get(levelId);
    if (hit) return hit;
  }
  const data = await api.get<LevelLessons>(`/learn/levels/${levelId}/lessons`);
  cache.set(levelId, data);
  return data;
}

export function forgetLevel(levelId: number): void {
  cache.delete(levelId);
}

/** URL dùng “Bài {no}” cho dễ đọc, nhưng API cần id — đổi ở đây. */
export async function lessonByNo(levelId: number, no: number): Promise<LessonItem | null> {
  const data = await levelLessons(levelId);
  return data.lessons.find((l) => l.no === no) ?? null;
}

export async function levelProgress(levelId: number, part: Part): Promise<LevelProgress> {
  return api.get<LevelProgress>(`/progress/levels/${levelId}`, { query: { part } });
}

export function progressOf(p: LevelProgress | null, lessonId: number): ProgressRow | null {
  return p?.lessons.find((x) => x.lessonId === lessonId) ?? null;
}

/** Lưu vị trí đang học (gọi sau mỗi thẻ / mỗi câu). */
export async function saveProgress(
  lessonId: number,
  part: Part,
  position: number,
  total: number,
  state?: Record<string, unknown> | null,
): Promise<void> {
  await api.put(`/progress/lessons/${lessonId}/${part}`, { position, total, state });
}

export async function completePart(lessonId: number, part: Part): Promise<{ firstTime: boolean; levelDone: { done: number; total: number }; lessonsDone: number }> {
  return api.post(`/progress/lessons/${lessonId}/${part}/complete`);
}

export async function saveReviewProgress(levelId: number, part: Part, position: number, total: number, state?: Record<string, unknown> | null): Promise<void> {
  await api.put(`/progress/review/${levelId}/${part}`, { position, total, state });
}

export async function completeReview(levelId: number, part: Part): Promise<unknown> {
  return api.post(`/progress/review/${levelId}/${part}/complete`);
}

/** Xáo mảng theo hạt giống cố định để tải lại trang vẫn giữ nguyên thứ tự (06 §2.9). */
export function seededShuffle<T>(arr: T[], seed: number): T[] {
  const out = [...arr];
  let s = seed || 1;
  for (let i = out.length - 1; i > 0; i--) {
    s = (s * 1664525 + 1013904223) % 4294967296;
    const j = s % (i + 1);
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
}
