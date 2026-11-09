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

<div class="fa-head"><div><h1>Quyền truy cập & hiển thị</h1><p>Tùy chỉnh từng vai trò. Tắt cha sẽ ẩn các con, giữ nguyên lựa chọn riêng của từng con.</p></div><div class="fa-buttons"><button class="btn" disabled={!st.dirty || st.saving} onclick={() => st.reset()}>Hủy thay đổi</button><button class="btn btn-son" disabled={!st.dirty || st.saving} onclick={async () => { if (await st.save()) toast.ok('Đã lưu quyền hiển thị và truy cập.'); }}>{st.saving ? 'Đang lưu...' : 'Lưu quyền'}</button></div></div>
<div class="fa-note">Mục “Hiển thị” chỉ điều khiển giao diện. Mục “Truy cập” còn chặn đường dẫn và API. Trang tài khoản, đăng xuất và khu quản trị luôn được giữ; lượt thi đang làm vẫn được nộp bài.</div>
<p style="margin-bottom: 18px"><a class="btn btn-sm" href="/admin/curricula?tab=visibility">Ẩn/hiện giáo trình, cấp và bài học</a></p>
<input class="inp fa-search" bind:value={st.search} placeholder="Tìm phần cần quản lý..." aria-label="Tìm quyền hiển thị" />
{#if st.error}<p class="err">{st.error} <button class="btn btn-sm" disabled={st.saving} onclick={() => st.load()}>Tải lại cấu hình</button></p>{/if}
{#if st.data}
  {#each groups as group (group.key)}
    {@const rows = st.data.catalog.filter((f) => f.key === group.key || f.key.startsWith(group.key + '.'))}
    {#if !st.search || rows.some((f) => f.label.toLocaleLowerCase('vi').includes(st.search.toLocaleLowerCase('vi')))}
      <section class="block fa-panel"><div class="fa-table-scroll"><table class="fa-table"><thead><tr><th>{group.label}</th>{#each roles as role}<th>{role.label}</th>{/each}</tr></thead><tbody>
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
  .fa-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin: 24px 0 18px; } .fa-head h1 { font-size: 26px; margin: 0 0 8px; } .fa-head p { margin: 0; color: var(--muted); font-size: 13px; max-width: 600px; line-height: 1.7; } .fa-buttons { display: flex; gap: 8px; } .fa-note { background: var(--troi); border: 1px solid var(--line); border-radius: 12px; padding: 14px 18px; font-size: 13px; line-height: 1.8; margin-bottom: 18px; } .fa-search { max-width: 420px; margin-bottom: 20px; } .fa-panel { margin-bottom: 20px; padding: 8px 14px; } .fa-table-scroll { overflow-x: auto; } .fa-table { width: 100%; border-collapse: collapse; } .fa-table th { padding: 14px 10px; color: var(--muted); font-size: 12px; } .fa-table th:first-child { text-align: left; } .fa-table td { padding: 12px 10px; border-top: 1px solid var(--line); text-align: center; min-width: 85px; } .fa-table td:first-child { text-align: left; min-width: 220px; width: 60%; } .fa-table b { font-size: 13px; font-weight: 500; } .fa-table td:first-child small { display: block; font-size: 10px; color: var(--muted); margin-top: 4px; } .fa-parent { background: var(--ground); } .fa-parent b { font-weight: 800; } .fa-switch { justify-content: center; margin: 0; } .fa-status { display: block; font-size: 10px; color: var(--muted); margin-top: 5px; }
</style>
