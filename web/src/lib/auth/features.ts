import type { Role } from '$lib/api/types';
export type Features = Record<string, boolean>;
export interface ActiveTest { mockId: number | null; levelId: number; lessonNo: number | null; lessonId?: number | null; lessonTitle?: string | null }

export function featureAllowed(features: Features | null | undefined, role: Role | undefined, key: string): boolean {
  if (features) return features[key] ?? false;
  return role === 'admin' || !['learn.exercises', 'learn.tests', 'community.chat'].some((k) => key === k || key.startsWith(k + '.'));
}

export function routeFeature(path: string): string | null {
  const p = path.split(/[?#]/)[0];
  if (p.startsWith('/learn/books')) return 'books';
  if (p.startsWith('/learn/chat')) return 'community.chat';
  if (p.startsWith('/notifications')) return 'community.notifications';
  if (p === '/settings') return 'account.preferences';
  const m = p.match(/^\/learn\/(flashcards|exercises|tests|writing|dialogues|grammar)(?:\/|$)/);
  if (m && p.includes('/mock/')) return 'community.contests.enter';
  return m ? `learn.${m[1]}` : null;
}

export function isActiveTest(path: string, active: ActiveTest[] = []): boolean {
  const m = path.match(/^\/learn\/tests\/hsk\/(\d+)\/(mock|lesson)\/(\d+)/);
  return !!m && active.some((a) => a.levelId === Number(m[1]) && (m[2] === 'mock' ? a.mockId === Number(m[3]) : a.lessonNo === Number(m[3])));
}
