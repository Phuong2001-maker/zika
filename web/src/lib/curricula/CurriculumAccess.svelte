<script lang="ts">
  import { onMount } from 'svelte';
  import Select from '$lib/components/Select.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import { curricula, DEMO_LEARNERS } from './demo.svelte';

  let assignments = $state<Record<string, string>>({});
  let saved = $state<Record<string, string>>({});
  let search = $state('');
  const dirty = $derived(JSON.stringify(assignments) !== JSON.stringify(saved));
  const normalizeSearch = (value: string): string =>
    value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/gi, 'd')
      .toLowerCase()
      .trim();
  const rows = $derived(DEMO_LEARNERS.filter((u) => normalizeSearch(`${u.name} ${u.email}`).includes(normalizeSearch(search))));
  const options = $derived([
    { v: '', label: 'Chưa gán giáo trình' },
    ...curricula.items.map((c) => ({ v: c.id, label: c.name + (c.published ? '' : ' · Bản nháp') })),
  ]);
  function load(): void {
    assignments = Object.fromEntries(DEMO_LEARNERS.map((u) => [u.id, curricula.assignedTo(u.id)?.id ?? '']));
    saved = { ...assignments };
  }
  function save(): void {
    curricula.assignAll($state.snapshot(assignments));
    saved = { ...assignments };
    toast.ok('Đã lưu giáo trình cho học viên trong bản xem trước.');
  }
  onMount(() => {
    curricula.load();
    load();
  });
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
    /></span
  >
  <section class="block cg-panel" aria-label="Giáo trình của từng học viên">
    <div class="cg-scroll">
      <table class="tbl">
        <thead><tr><th>Học viên</th><th>Giáo trình đang được gán</th><th>Trạng thái</th></tr></thead><tbody>
          {#each rows as u}<tr
              ><td
                ><div class="cg-person"><span class="ava">{u.initials}</span><span><b>{u.name}</b><small>{u.email}</small></span></div></td
              ><td
                ><div class="cg-select"><Select ariaLabel={`Giáo trình của ${u.name}`} bind:value={assignments[u.id]} {options} /></div></td
              ><td
                ><span
                  class="tag"
                  class:tag-warn={assignments[u.id] !== saved[u.id]}
                  class:tag-ok={!!assignments[u.id] && assignments[u.id] === saved[u.id]}
                  >{assignments[u.id] !== saved[u.id] ? 'Chờ lưu' : assignments[u.id] ? 'Đã gán' : 'Chưa gán'}</span
                ></td
              ></tr
            >{/each}
          {#if rows.length === 0}<tr><td colspan="3" class="cg-empty">Không tìm thấy học viên phù hợp.</td></tr>{/if}
        </tbody>
      </table>
    </div>
    <footer class="cg-footer">
      <div>
        <button class="btn" disabled={!dirty} onclick={load}>Hủy thay đổi</button><button
          class="btn btn-son"
          disabled={!dirty}
          onclick={save}>Lưu phân giáo trình</button
        >
      </div>
    </footer>
  </section>
</div>

<style>
  .cg-search {
    width: 100%;
    max-width: 420px;
  }
  .cg-shell {
    display: flex;
    flex-direction: column;
    gap: 22px;
  }
  .cg-panel {
    overflow: hidden;
  }
  .cg-scroll {
    padding: 8px 12px;
    overflow-x: auto;
  }
  .cg-select {
    min-width: 240px;
  }
  .cg-person {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 230px;
  }
  .cg-person b {
    font-size: 13px;
  }
  .cg-person small {
    display: block;
    font-size: 11px;
    color: var(--muted);
    margin-top: 5px;
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
  .cg-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
    padding: 18px 24px;
    background: var(--surface-2);
  }
  .cg-footer > div {
    display: flex;
    gap: 8px;
  }
  @media (max-width: 700px) {
    .cg-footer {
      padding: 16px;
    }
    .cg-footer > div {
      width: 100%;
    }
    .cg-footer button {
      flex: 1;
    }
  }
</style>
