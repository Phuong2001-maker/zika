import { api, ApiError } from '$lib/api/client';
import { session } from '$lib/stores/session.svelte';
import type { Role } from '$lib/api/types';

export interface Feature { key: string; label: string; parent: string | null; kind: 'display' | 'access'; locked: boolean }
interface Config { catalog: Feature[]; matrix: Record<Role, Record<string, boolean>>; revision: number }
export class FeatureAccessState {
  data = $state<Config | null>(null);
  original: Config | null = null;
  saving = $state(false);
  error = $state('');
  search = $state('');
  get dirty(): boolean { return !!this.data && JSON.stringify(this.data.matrix) !== JSON.stringify(this.original?.matrix); }
  async load() {
    this.error = '';
    try { this.data = await api.get<Config>('/admin/feature-access'); this.original = $state.snapshot(this.data); }
    catch { this.error = 'Chưa tải được cấu hình quyền.'; }
  }
  parentOn(feature: Feature, role: Role): boolean {
    const parent = this.data?.catalog.find((f) => f.key === feature.parent);
    return !parent || (!!this.data?.matrix[role][parent.key] && this.parentOn(parent, role));
  }
  depth(feature: Feature): number { const p = this.data?.catalog.find((f) => f.key === feature.parent); return p ? this.depth(p) + 1 : 0; }
  reset() { if (this.original) this.data = structuredClone(this.original); this.error = ''; }
  async save() {
    if (!this.data || !this.dirty || this.saving) return;
    this.saving = true;
    this.error = '';
    try {
      const changes = (['learner', 'editor', 'admin'] as Role[]).flatMap((role) => this.data!.catalog.filter((f) => this.data!.matrix[role][f.key] !== this.original?.matrix[role][f.key]).map((f) => ({ feature: f.key, role, enabled: this.data!.matrix[role][f.key] })));
      this.data = await api.put<Config>('/admin/feature-access', { revision: this.data.revision, changes });
      this.original = $state.snapshot(this.data);
      await session.refreshFeatures();
      return true;
    } catch (e) { this.error = e instanceof ApiError ? e.message : 'Chưa lưu được cấu hình quyền.'; return false; }
    finally { this.saving = false; }
  }
}
