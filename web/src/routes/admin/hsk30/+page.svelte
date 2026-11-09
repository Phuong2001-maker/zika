<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import Select from '$lib/components/Select.svelte';
  import { GOI_Y_CAP, moTaPhan, type SoCauPhan } from '$lib/learn/thi-hsk/cau-truc';

  // Quản trị · Đề HSK 3.0: kho đề dùng chung mọi giáo trình. Soạn câu ở trang từng đề.
  interface De {
    id: number;
    levelId: number;
    title: string;
    status: 'draft' | 'published';
    durationSec: number;
    questionCount: number;
    sections: SoCauPhan;
    attempts: number;
  }

  let ds = $state<De[] | null>(null);
  let loi = $state(false);
  let taoMo = $state(false);
  let dangTao = $state(false);
  let moiCap = $state(1);
  let moiTen = $state('');
  let moiPhut = $state(GOI_Y_CAP[1].phut);

  const cap = $derived(Number(page.url.searchParams.get('cap')) || 1);
  const deCap = $derived((ds ?? []).filter((d) => d.levelId === cap));

  async function tai(): Promise<void> {
    loi = false;
    try {
      ds = await api.get<De[]>('/admin/hsk30');
    } catch {
      loi = true;
    }
  }
  onMount(tai);

  function chonCap(n: number): void {
    void goto(`/admin/hsk30?cap=${n}`, { replaceState: true, noScroll: true, keepFocus: true });
  }

  function moTao(): void {
    moiCap = cap;
    moiTen = `HSK ${cap} · Đề ${(ds ?? []).filter((d) => d.levelId === cap).length + 1}`;
    moiPhut = GOI_Y_CAP[cap]?.phut ?? 60;
    taoMo = true;
  }

  async function tao(): Promise<void> {
    dangTao = true;
    try {
      const r = await api.post<{ id: number }>('/admin/hsk30', { levelId: moiCap, title: moiTen, durationSec: Math.round(moiPhut * 60) });
      taoMo = false;
      void goto(`/admin/hsk30/${r.id}`);
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không tạo được đề.');
    } finally {
      dangTao = false;
    }
  }

  async function doiTrangThai(d: De): Promise<void> {
    const moi = d.status === 'published' ? 'draft' : 'published';
    if (moi === 'published' && d.questionCount === 0) {
      toast.show('Đề chưa có câu nào, soạn câu trước khi công khai.');
      return;
    }
    try {
      await api.patch(`/admin/mock-tests/${d.id}`, { status: moi });
      ds = (ds ?? []).map((x) => (x.id === d.id ? { ...x, status: moi } : x));
      toast.ok(moi === 'published' ? 'Đã công khai đề.' : 'Đã chuyển về Nháp.');
    } catch {
      toast.show('Không đổi được trạng thái.');
    }
  }

  async function xoa(d: De): Promise<void> {
    if (!confirm(`Xoá đề “${d.title}” cùng ${d.questionCount} câu? Không khôi phục được.`)) return;
    try {
      await api.del(`/admin/mock-tests/${d.id}`);
      ds = (ds ?? []).filter((x) => x.id !== d.id);
      toast.ok('Đã xoá đề.');
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không xoá được.');
    }
  }
</script>

<svelte:head><title>Đề HSK 3.0 · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <h1>Đề HSK 3.0</h1>
  <button type="button" class="btn btn-son btn-sm" onclick={moTao}><Icon name="plus" size={15} /> Tạo đề</button>
</div>

<div class="tabs" style="margin-bottom: 16px" role="tablist" aria-label="Cấp HSK">
  {#each [1, 2, 3, 4, 5, 6] as n (n)}
    {@const so = (ds ?? []).filter((d) => d.levelId === n).length}
    <button type="button" class="tab" class:on={n === cap} role="tab" aria-selected={n === cap} onclick={() => chonCap(n)}>
      HSK {n}{#if so}<span class="muted num" style="font-weight: 500"> {so}</span>{/if}
    </button>
  {/each}
</div>

{#if loi}
  <EmptyState title="Không tải được danh sách đề.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={tai}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if ds === null}
  <Skeleton h="240px" r={16} />
{:else if deCap.length === 0}
  <EmptyState title="HSK {cap} chưa có đề nào.">
    {#snippet action()}<button type="button" class="btn btn-sm btn-son" onclick={moTao}>Tạo đề</button>{/snippet}
  </EmptyState>
{:else}
  <section class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead>
        <tr><th>Tên đề</th><th>Các phần</th><th class="r">Thời gian</th><th class="r">Lượt thi</th><th>Công khai</th><th></th></tr>
      </thead>
      <tbody>
        {#each deCap as d (d.id)}
          <tr>
            <td><a href="/admin/hsk30/{d.id}"><b>{d.title}</b></a><span class="muted num" style="display: block; font-size: 12.5px">{d.questionCount} câu</span></td>
            <td class="muted">{moTaPhan(d.sections) || 'Chưa có câu'}</td>
            <td class="r num">{Math.round(d.durationSec / 60)} phút</td>
            <td class="r num">{d.attempts}</td>
            <td>
              <label class="sw" title={d.status === 'published' ? 'Đang công khai' : 'Nháp'}>
                <input type="checkbox" checked={d.status === 'published'} onchange={() => doiTrangThai(d)} aria-label="Công khai {d.title}" />
                <span></span>
              </label>
            </td>
            <td style="white-space: nowrap">
              <a class="btn btn-sm btn-paper" href="/admin/hsk30/{d.id}"><Icon name="edit" size={15} /> Soạn câu</a>
              <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá {d.title}" onclick={() => xoa(d)}><Icon name="trash" size={15} /></button>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </section>
{/if}

{#if taoMo}
  <Modal title="Tạo đề HSK 3.0" onclose={() => (taoMo = false)} width={480}>
    <div class="field">
      <span class="lbl">Cấp</span>
      <Select bind:value={moiCap} ariaLabel="Chọn cấp" options={[1, 2, 3, 4, 5, 6].map((n) => ({ v: n, label: `HSK ${n}` }))}
        onchange={() => { moiPhut = GOI_Y_CAP[moiCap]?.phut ?? moiPhut; }} />
    </div>
    <div class="field">
      <label class="lbl" for="hs-ten">Tên đề</label>
      <input id="hs-ten" class="inp" bind:value={moiTen} maxlength="80" />
    </div>
    <div class="field">
      <label class="lbl" for="hs-phut">Thời gian làm bài (phút)</label>
      <input id="hs-phut" class="inp num" type="number" min="1" max="180" bind:value={moiPhut} />
      <p class="hint">Gợi ý theo cấu trúc đề HSK {moiCap}: {GOI_Y_CAP[moiCap]?.cau} câu, {GOI_Y_CAP[moiCap]?.phut} phút.</p>
    </div>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (taoMo = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={dangTao || moiTen.trim() === ''} onclick={tao}>Tạo và soạn câu</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
  .head h1 { font-size: 28px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
</style>
