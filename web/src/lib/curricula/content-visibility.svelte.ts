import { api } from '$lib/api/client';

export interface VisibilityLesson { id: number; no: number; title: string; visible: boolean }
export interface VisibilityLevel { id: number; visible: boolean; lessons: VisibilityLesson[] }
export interface VisibilityCurriculum { id: number; name: string; label: string; visible: boolean; levels: VisibilityLevel[] }

export class ContentVisibilityState {
  curricula = $state<VisibilityCurriculum[]>([]);
  selected = $state(0);
  expanded = $state(1);
  loading = $state(true);
  busy = $state(false);
  error = $state('');
  current = $derived(this.curricula.find((c) => c.id === this.selected));

  async load(selected = 0, level = 1): Promise<void> {
    this.loading = true;
    this.error = '';
    try {
      const out = await api.get<{ curricula: VisibilityCurriculum[] }>('/admin/content-visibility');
      this.curricula = out.curricula;
      this.selected = out.curricula.some((c) => c.id === selected) ? selected : (out.curricula[0]?.id ?? 0);
      this.expanded = level;
    } catch { this.error = 'Không tải được danh sách. Thử lại nhé.'; }
    finally { this.loading = false; }
  }

  async toggle(kind: 'curriculum' | 'level' | 'lesson', visible: boolean, levelId?: number, lessonId?: number): Promise<void> {
    if (this.busy) return;
    this.busy = true;
    this.error = '';
    try {
      const out = await api.patch<{ curricula: VisibilityCurriculum[] }>('/admin/content-visibility', { kind, curriculumId: this.selected, levelId, lessonId, visible });
      this.curricula = out.curricula;
    } catch { this.error = 'Chưa lưu được thay đổi. Trạng thái trước đó được giữ nguyên.'; }
    finally { this.busy = false; }
  }
}
