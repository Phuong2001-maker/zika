<script lang="ts">
  import { onMount } from 'svelte';
  import Select from '$lib/components/Select.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import { adminApi, type AdminCurriculum, type CurriculumLearner } from '$lib/admin/api';
  import { ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';

  // Mỗi học viên một giáo trình. Để Mặc định (null) là học theo giáo trình mặc định.
  const PER = 50;

  let curricula = $state<AdminCurriculum[]>([]);
  let rows = $state<CurriculumLearner[] | null>(null);
  let total = $state(0);
  let page = $state(1);
  let search = $state('');
  let failed = $state(false);
  let saving = $state(false);
  /** Giá trị đang chọn theo id học viên; giữ riêng để tìm kiếm hoặc tải thêm không mất chọn chưa lưu. */
  let chosen = $state<Record<number, number | null>>({});
  let saved = $state<Record<number, number | null>>({});
  let timer: ReturnType<typeof setTimeout> | null = null;

  const changed = $derived(Object.keys(chosen).map(Number).filter((id) => chosen[id] !== saved[id]));
  const defaultName = $derived(curricula.find((c) => c.isDefault)?.name ?? 'giáo trình mặc định');
  const options = $derived([
    { v: null, label: `Mặc định · ${defaultName}` },
    ...curricula.filter((c) => !c.isDefault).map((c) => ({ v: c.id, label: c.name })),
  ]);

  const initials = (name: string): string =>
    name
      .trim()
      .split(/\s+/)
      .slice(-2)
      .map((w) => w.charAt(0).toUpperCase())
      .join('');

  async function load(next = 1): Promise<void> {
    failed = false;
    try {
      const res = await adminApi.curriculumLearners({ q: search.trim(), page: next, per: PER });
      total = Number(res.meta?.total ?? res.data.length);
      page = next;
      const isDefault = (id: number | null): boolean => id === null || curricula.some((c) => c.isDefault && c.id === id);
      for (const u of res.data) {
        const value = isDefault(u.curriculumId) ? null : u.curriculumId;
        saved[u.id] = value;
        if (!(u.id in chosen)) chosen[u.id] = value;
      }
      rows = next === 1 ? res.data : [...(rows ?? []), ...res.data];
    } catch {
      failed = true;
    }
  }

  onMount(async () => {
    try {
      curricula = await adminApi.curricula();
    } catch {
      failed = true;
    }
    await load();
  });

  function onSearch(): void {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => void load(), 300);
  }

  function reset(): void {
    chosen = { ...saved };
  }

  async function save(): Promise<void> {
    saving = true;
    try {
      await adminApi.assignCurricula(changed.map((id) => ({ userId: id, curriculumId: chosen[id] })));
      saved = { ...saved, ...Object.fromEntries(changed.map((id) => [id, chosen[id]])) };
      toast.ok('Đã lưu giáo trình cho học viên.');
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    } finally {
      saving = false;
    }
  }
</script>

<svelte:head><title>Phân giáo trình · Quản trị · Zìkǎ</title></svelte:head>

<div class="cg-shell">
  <span class="ico-in cg-search"
    ><Icon name="search" size={16} /><input
      class="inp"
      type="search"
      aria-label="Tìm học viên theo tên hoặc email"
      placeholder="Tìm học viên theo tên hoặc email…"
      bind:value={search}
      oninput={onSearch}
    /></span
  >
  {#if failed}
    <p class="err">Không tải được danh sách học viên. <button type="button" class="btn btn-sm" onclick={() => load()}>Thử lại</button></p>
  {:else if rows === null}
    <Skeleton h="220px" r={16} />
  {:else}
    <section class="block cg-panel" aria-label="Giáo trình của từng học viên">
      <div class="cg-scroll">
        <table class="tbl">
          <thead><tr><th>Học viên</th><th>Giáo trình đang học</th><th>Trạng thái</th></tr></thead>
          <tbody>
            {#each rows as u (u.id)}
              <tr>
                <td>
                  <div class="cg-person">
                    <span class="ava">{initials(u.name)}</span>
                    <span class="cg-who"><b>{u.name}</b><small>{u.email}</small></span>
                  </div>
                </td>
                <td><div class="cg-select"><Select ariaLabel={`Giáo trình của ${u.name}`} bind:value={chosen[u.id]} {options} /></div></td>
                <td>
                  <span class="tag" class:tag-warn={chosen[u.id] !== saved[u.id]} class:tag-ok={chosen[u.id] === saved[u.id] && chosen[u.id] !== null}>
                    {chosen[u.id] !== saved[u.id] ? 'Chờ lưu' : chosen[u.id] !== null ? 'Đã gán' : 'Mặc định'}
                  </span>
                </td>
              </tr>
            {/each}
            {#if rows.length === 0}<tr><td colspan="3" class="cg-empty">Không tìm thấy học viên phù hợp.</td></tr>{/if}
          </tbody>
        </table>
      </div>
      {#if rows.length < total}
        <div class="cg-more"><button type="button" class="btn btn-sm" onclick={() => load(page + 1)}>Xem thêm ({num(total - rows.length)} học viên)</button></div>
      {/if}
      <footer class="cg-footer">
        <p class="muted">Đổi giáo trình thì học viên học bộ mới từ đầu, tiến độ bộ cũ vẫn giữ nếu gán lại.</p>
        <div>
          <button type="button" class="btn" disabled={changed.length === 0} onclick={reset}>Huỷ thay đổi</button>
          <button type="button" class="btn btn-son" disabled={changed.length === 0 || saving} onclick={save}>Lưu phân giáo trình</button>
        </div>
      </footer>
    </section>
  {/if}
</div>

<style>
  .cg-search {
    width: 100%;
    max-width: 420px;
  }
  .cg-shell {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .cg-panel {
    overflow: hidden;
  }
  .cg-scroll {
    padding: 8px 12px;
    overflow-x: auto;
  }
  .cg-select {
    min-width: 200px;
  }
  .cg-person {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 180px;
  }
  .cg-who b {
    font-size: 13px;
  }
  .cg-who small {
    display: block;
    font-size: 11px;
    color: var(--muted);
    margin-top: 3px;
  }
  .cg-person .ava {
    width: 38px;
    height: 38px;
    font-size: 12px;
    background: var(--troi);
  }
  .cg-empty {
    padding: 36px;
    text-align: center;
    color: var(--muted);
  }
  .cg-more {
    display: flex;
    justify-content: center;
    padding: 0 12px 12px;
  }
  .cg-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 14px 20px;
    background: var(--surface-2);
  }
  .cg-footer p {
    flex: 1 1 260px;
    font-size: 13px;
  }
  .cg-footer > div {
    display: flex;
    gap: 8px;
  }
  @media (max-width: 700px) {
    .cg-footer > div {
      width: 100%;
    }
    .cg-footer button {
      flex: 1;
    }
  }
</style>
