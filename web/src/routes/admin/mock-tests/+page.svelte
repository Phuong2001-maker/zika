<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Quản trị · Đề thi thử (08 §0, bổ sung).
  interface Mock {
    id: number; title: string; status: 'draft' | 'published';
    questionCount: number; durationSec: number; maxPoints: number; passPoints: number; listenLimit: number;
  }

  const levelId = $derived(Number(page.url.searchParams.get('level')) || 2);

  let list = $state<Mock[] | null>(null);
  let failed = $state(false);
  let addOpen = $state(false);
  let saving = $state(false);

  let title = $state('Đề 1');
  let minutes = $state(55);
  let maxPoints = $state(200);
  let passPoints = $state(120);
  let copyCount = $state(60);
  let doCopy = $state(true);

  async function load(): Promise<void> {
    failed = false;
    list = null;
    try {
      list = await api.get<Mock[]>(`/admin/levels/${levelId}/mock-tests`);
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => {
    void levelId;
    void load();
  });

  async function create(): Promise<void> {
    saving = true;
    try {
      const res = await api.post<{ id: number; copied: number }>(`/admin/levels/${levelId}/mock-tests`, {
        title,
        durationSec: minutes * 60,
        maxPoints,
        passPoints,
        copyFromTests: doCopy ? { count: copyCount } : undefined,
      });
      addOpen = false;
      toast.ok(res.copied > 0 ? `Đã tạo đề và lấy ${res.copied} câu.` : 'Đã tạo đề.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không tạo được đề.');
    } finally {
      saving = false;
    }
  }

  async function toggleStatus(m: Mock): Promise<void> {
    try {
      await api.patch(`/admin/mock-tests/${m.id}`, { status: m.status === 'published' ? 'draft' : 'published' });
      await load();
    } catch {
      toast.show('Không đổi được trạng thái.');
    }
  }

  async function addMore(m: Mock): Promise<void> {
    const n = Number(prompt('Lấy thêm bao nhiêu câu từ các bài kiểm tra của cấp?', '10'));
    if (!n || n <= 0) return;
    try {
      const res = await api.post<{ added: number }>(`/admin/mock-tests/${m.id}/copy-from-tests`, { count: n });
      toast.ok(`Đã thêm ${res.added} câu.`);
      await load();
    } catch {
      toast.show('Không thêm được câu.');
    }
  }

  async function remove(m: Mock): Promise<void> {
    if (!confirm(`Xoá đề “${m.title}”?`)) return;
    try {
      await api.del(`/admin/mock-tests/${m.id}`);
      await load();
      toast.ok('Đã xoá đề.');
    } catch {
      toast.show('Không xoá được.');
    }
  }
</script>

<svelte:head><title>Đề thi thử · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Đề thi thử HSK {levelId}</h1>
    <p class="muted" style="font-size: 14px">Đề cuối cấp, câu lấy từ các bài kiểm tra của cấp.</p>
  </div>
  <button type="button" class="btn btn-son btn-sm" onclick={() => (addOpen = true)}><Icon name="plus" size={15} /> Tạo đề</button>
</div>

<div class="lv-tabs">
  {#each [1, 2, 3, 4, 5, 6] as l (l)}
    <button type="button" class="btn btn-sm {l === levelId ? 'btn-muc' : 'btn-paper'}"
            onclick={() => goto(`/admin/mock-tests?level=${l}`, { noScroll: true })}>HSK {l}</button>
  {/each}
</div>

{#if failed}
  <EmptyState title="Không tải được danh sách đề.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if list === null}
  <Skeleton h="220px" r={16} />
{:else if list.length === 0}
  <EmptyState title="Cấp này chưa có đề thi thử." desc="Tạo đề rồi lấy câu ngẫu nhiên từ các bài kiểm tra.">
    {#snippet action()}<button type="button" class="btn btn-son btn-sm" onclick={() => (addOpen = true)}>Tạo đề</button>{/snippet}
  </EmptyState>
{:else}
  <div class="grid">
    {#each list as m (m.id)}
      <article class="block card">
        <div style="display: flex; align-items: center; gap: 10px">
          <b style="flex: 1; font-size: 17px">{m.title}</b>
          <span class="tag {m.status === 'published' ? 'tag-ok' : ''}">{m.status === 'published' ? 'Đang hiện' : 'Nháp'}</span>
        </div>
        <p class="muted num" style="font-size: 13.5px">
          {num(m.questionCount)} câu · {Math.round(m.durationSec / 60)} phút · đạt từ {m.passPoints}/{m.maxPoints} điểm
        </p>
        {#if m.questionCount === 0}
          <p class="err">Đề chưa có câu nào, người học sẽ không thấy.</p>
        {/if}
        <div style="display: flex; gap: 8px; flex-wrap: wrap">
          <button type="button" class="btn btn-sm" onclick={() => addMore(m)}><Icon name="download" size={15} /> Lấy thêm câu</button>
          <button type="button" class="btn btn-sm" onclick={() => toggleStatus(m)}>
            {m.status === 'published' ? 'Chuyển về nháp' : 'Cho hiện'}
          </button>
          <span style="flex: 1"></span>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá đề" onclick={() => remove(m)}><Icon name="trash" size={15} /></button>
        </div>
      </article>
    {/each}
  </div>
{/if}

{#if addOpen}
  <Modal title="Tạo đề thi thử HSK {levelId}" onclose={() => (addOpen = false)}>
    <div class="field"><label class="lbl" for="m-title">Tên đề</label><input id="m-title" class="inp" bind:value={title} /></div>
    <div class="grid2">
      <div class="field"><label class="lbl" for="m-min">Thời gian (phút)</label><input id="m-min" class="inp" type="number" min="5" max="180" bind:value={minutes} /></div>
      <div class="field"><label class="lbl" for="m-max">Điểm tối đa</label><input id="m-max" class="inp" type="number" min="1" bind:value={maxPoints} /></div>
      <div class="field"><label class="lbl" for="m-pass">Điểm đạt</label><input id="m-pass" class="inp" type="number" min="0" bind:value={passPoints} /></div>
    </div>
    <label class="sw"><input type="checkbox" bind:checked={doCopy} /><span></span> Lấy câu từ các bài kiểm tra của cấp</label>
    {#if doCopy}
      <div class="field"><label class="lbl" for="m-count">Số câu lấy</label><input id="m-count" class="inp" type="number" min="1" max="200" bind:value={copyCount} /></div>
    {/if}
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (addOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={create}>Tạo đề</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
  }
  .head h1 {
    font-size: 28px;
  }
  .lv-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr));
    gap: 14px;
  }
  .card {
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(140px, 100%), 1fr));
    gap: 12px;
  }
</style>
