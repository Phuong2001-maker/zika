<script lang="ts">
  import { onMount } from 'svelte';
  import type { Role } from '$lib/api/types';
  import { FeatureAccessState } from './feature-access.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  const st = new FeatureAccessState();
  const roles: { key: Role; label: string }[] = [{ key: 'learner', label: 'Học viên' }, { key: 'editor', label: 'Biên tập' }, { key: 'admin', label: 'Admin' }];
  onMount(() => { void st.load(); });
  const groups = $derived(st.data?.catalog.filter((f) => !f.parent) ?? []);
</script>

<div class="fa-head"><h1>Quyền truy cập & hiển thị</h1><div class="fa-buttons"><button class="btn btn-sm" disabled={!st.dirty || st.saving} onclick={() => st.reset()}>Hủy thay đổi</button><button class="btn btn-son btn-sm" disabled={!st.dirty || st.saving} onclick={async () => { if (await st.save()) toast.ok('Đã lưu quyền hiển thị và truy cập.'); }}>{st.saving ? 'Đang lưu...' : 'Lưu quyền'}</button></div></div>
<input class="inp fa-search" bind:value={st.search} placeholder="Tìm phần cần quản lý..." aria-label="Tìm quyền hiển thị" />
{#if st.error}<p class="err">{st.error} <button class="btn btn-sm" disabled={st.saving} onclick={() => st.load()}>Tải lại cấu hình</button></p>{/if}
{#if st.data}
  {#each groups as group (group.key)}
    {@const rows = st.data.catalog.filter((f) => f.key === group.key || f.key.startsWith(group.key + '.'))}
    {#if !st.search || rows.some((f) => f.label.toLocaleLowerCase('vi').includes(st.search.toLocaleLowerCase('vi')))}
      <section class="block fa-panel"><div class="fa-table-scroll"><table class="fa-table"><thead><tr><th>Chức năng</th>{#each roles as role}<th>{role.label}</th>{/each}</tr></thead><tbody>
        {#each rows as f (f.key)}
          <tr class:fa-parent={!f.parent}><td><div style={`padding-left: ${st.depth(f) * 18}px`}><b>{f.label}</b><small>{f.locked ? 'Luôn mở' : f.kind === 'display' ? 'Hiển thị' : 'Truy cập'}</small></div></td>
            {#each roles as role}{@const inherited = !st.parentOn(f, role.key)}<td><label class="sw fa-switch" title={inherited ? 'Đang ẩn theo mục cha' : f.locked ? 'Chức năng thiết yếu' : `${f.label} · ${role.label}`}><input type="checkbox" checked={st.data.matrix[role.key][f.key]} disabled={inherited || f.locked || st.saving} aria-label={`${f.label} cho ${role.label}`} onchange={(e) => { if (st.data) st.data.matrix[role.key][f.key] = e.currentTarget.checked; }} /><span></span></label><small class="fa-status">{inherited ? 'Theo cha: tắt' : st.data.matrix[role.key][f.key] ? 'Bật' : 'Tắt'}</small></td>{/each}
          </tr>
        {/each}
      </tbody></table></div></section>
    {/if}
  {/each}
{:else if !st.error}<p class="muted">Đang tải cấu hình quyền...</p>{/if}

<style>
  /* Cùng thang chữ với các trang admin khác: tiêu đề 28px như .head h1, chữ bảng 14px và đầu cột 12px như .tbl,
     chữ phụ 12px như .tag. Trước đây có cả 10px và 13px nên bảng nhìn chữ to chữ bé lẫn lộn. */
  .fa-head { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 16px; }
  .fa-head h1 { font-size: 28px; }
  .fa-buttons { display: flex; gap: 8px; }
  .fa-search { max-width: 420px; margin-bottom: 16px; }
  .fa-panel { margin-bottom: 16px; padding: 4px 14px; }
  .fa-table-scroll { overflow-x: auto; }
  .fa-table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .fa-table th { padding: 11px 10px; font-size: 12px; font-weight: 600; color: var(--text-3); letter-spacing: .02em; }
  .fa-table th:first-child { text-align: left; }
  .fa-table td { padding: 11px 10px; border-top: 1px solid var(--line); text-align: center; min-width: 85px; vertical-align: middle; }
  .fa-table td:first-child { text-align: left; min-width: 220px; width: 60%; }
  .fa-table b { font-weight: 500; }
  .fa-table td:first-child small { display: block; font-size: 12px; color: var(--text-3); margin-top: 2px; }
  .fa-parent { background: var(--ground); }
  .fa-parent b { font-weight: 700; }
  .fa-switch { justify-content: center; margin: 0; }
  .fa-status { display: block; font-size: 12px; color: var(--text-3); margin-top: 4px; }
</style>
