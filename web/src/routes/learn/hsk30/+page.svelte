<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import { PHAN, type SoCauPhan } from '$lib/learn/thi-hsk/cau-truc';

  // Thi thử HSK 3.0 · danh sách đề theo cấp. Kho đề chung, học viên học giáo trình nào cũng thấy như nhau.
  interface De {
    id: number;
    title: string;
    durationSec: number;
    questionCount: number;
    sections: SoCauPhan;
    best: { percent: number | null; attempts: number } | null;
    open: { exam: boolean; practice: boolean };
  }
  interface Cap { level: number; exams: De[] }

  let ds = $state<Cap[] | null>(null);
  let loi = $state(false);

  const capChon = $derived(Number(page.url.searchParams.get('cap')) || ds?.[0]?.level || 1);
  const capHien = $derived(ds?.find((c) => c.level === capChon) ?? ds?.[0] ?? null);

  async function tai(): Promise<void> {
    loi = false;
    try {
      ds = (await api.get<{ levels: Cap[] }>('/learn/hsk30')).levels;
    } catch {
      loi = true;
    }
  }
  onMount(tai);

  function chonCap(n: number): void {
    void goto(`/learn/hsk30?cap=${n}`, { replaceState: true, noScroll: true, keepFocus: true });
  }
</script>

<svelte:head><title>Thi thử HSK 3.0 · Zìkǎ</title></svelte:head>

{#snippet theDe(d: De)}
  <article class="zk-o hs-de">
    <h3 class="hs-de-ten">{d.title}</h3>
    <p class="muted num hs-de-phu">{d.questionCount} câu · {Math.round(d.durationSec / 60)} phút</p>
    <ul class="hs-phan">
      {#each PHAN.filter((p) => (d.sections[p.code] ?? 0) > 0) as p (p.code)}
        <li><Icon name={p.icon} size={15} /> {p.ten} <b class="num">{d.sections[p.code]}</b></li>
      {/each}
    </ul>
    {#if d.best}
      <p class="hs-diem">Thi thật tốt nhất: <b class="num">{d.best.percent ?? 0}%</b> · đã thi {d.best.attempts} lần</p>
    {/if}
    <div class="hs-nut">
      <a class="btn" href="/learn/hsk30/{d.id}?mode=practice">
        <Icon name="book" size={16} /> {d.open.practice ? 'Luyện tiếp' : 'Luyện tập'}
      </a>
      <a class="btn btn-son" href="/learn/hsk30/{d.id}">
        <Icon name="clock" size={16} /> {d.open.exam ? 'Thi tiếp' : 'Thi thật'}
      </a>
    </div>
  </article>
{/snippet}

{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if ds && ds.length === 0}
      <EmptyState title="Chưa có đề thi thử HSK 3.0 nào." desc="Admin đang soạn đề, bạn quay lại sau nhé." />
    {:else if ds && capHien}
      <div class="zk-khung-dau">
        <h2 class="zk-khung-ten">Chọn cấp</h2>
        <span class="zk-so">{capHien.exams.length} đề</span>
      </div>
      <div class="zk-tabs" role="tablist" aria-label="Cấp HSK">
        {#each ds as c (c.level)}
          <button type="button" class="zk-tab" role="tab" aria-selected={c.level === capHien.level} onclick={() => chonCap(c.level)}>HSK {c.level}</button>
        {/each}
      </div>

      <div class="hs-che-do" class:hs-che-do-m={m}>
        <p><b><Icon name="book" size={15} /> Luyện tập</b> Không tính giờ, bấm Kiểm tra để xem đáp án và giải thích từng câu.</p>
        <p><b><Icon name="clock" size={15} /> Thi thật</b> Tính giờ như đề thật, nghe tối đa 2 lần, nộp bài mới có điểm từng phần.</p>
      </div>

      <div class="hs-ds">
        {#each capHien.exams as d (d.id)}{@render theDe(d)}{/each}
      </div>
      <p class="muted hs-ghi-chu"><Icon name="mic" size={14} /> Phần nói ghi âm ngay trên máy bạn để tự nghe lại, không gửi lên máy chủ.</p>
    {/if}
  </section>
{/snippet}

{#snippet body(m: boolean)}
  {#if loi}
    <EmptyState title="Không tải được danh sách đề.">
      {#snippet action()}<button type="button" class="btn btn-sm" onclick={tai}>Thử lại</button>{/snippet}
    </EmptyState>
  {:else if ds === null}
    <Skeleton h="320px" r={22} />
  {:else}
    {@render khung(m)}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <MTopbar current="hsk30" title="Thi thử HSK 3.0" sub="Đề luyện thi định dạng mới" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">{@render body(true)}</main>
  </div>
{:else}
  <Topbar current="hsk30">
    <nav class="crumb"><b>Thi thử HSK 3.0</b></nav>
  </Topbar>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    <DauBai href="/learn/flashcards" hideBack kicker="Đề luyện thi định dạng mới · dùng chung mọi giáo trình" title="Thi thử HSK 3.0" />
    {@render body(false)}
  </main>
{/if}

<style>
  .hs-che-do { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
  .hs-che-do p { display: flex; flex-direction: column; gap: 4px; padding: 12px 14px; border: 1px dashed var(--line-3); border-radius: 14px; font-size: 13.5px; color: var(--text-2); }
  .hs-che-do b { display: inline-flex; align-items: center; gap: 6px; color: var(--text); font-size: 14.5px; }
  .hs-che-do-m { grid-template-columns: 1fr; }
  .hs-ds { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 14px; }
  .hs-de { display: flex; flex-direction: column; gap: 10px; padding: 16px 18px; }
  .hs-de-ten { font-size: 17px; font-weight: 800; overflow-wrap: break-word; }
  .hs-de-phu { font-size: 14px; }
  .hs-phan { display: flex; flex-wrap: wrap; gap: 6px; }
  .hs-phan li { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background: var(--surface); border: 1px solid var(--line); font-size: 13px; }
  .hs-diem { font-size: 14px; color: var(--green-text); }
  .hs-nut { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: auto; }
  .hs-ghi-chu { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
</style>
