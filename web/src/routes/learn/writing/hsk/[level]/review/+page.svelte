<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import { speak } from '$lib/learn/speech';

  // Ôn viết cả cấp (04 §4.9 part = lv). Có lọc “chỉ chữ hay sai”.
  interface Char {
    id: number; hanzi: string; pinyin: string; tone: number; hanViet: string; meaning: string;
    strokes: number; structure: string; lessonNo?: number;
  }

  const levelId = $derived(Number(page.params.level));

  let chars = $state<Char[] | null>(null);
  let weak = $state<Set<number>>(new Set());
  let onlyWeak = $state(false);
  let failed = $state(false);
  let q = $state('');

  async function load(): Promise<void> {
    failed = false;
    try {
      const [res, w] = await Promise.all([
        api.get<{ chars: Char[] }>(`/learn/levels/${levelId}/review/lv`),
        api.get<{ charIds: number[] }>('/writing/weak', { query: { levelId } }).catch(() => ({ charIds: [] })),
      ]);
      chars = res.chars;
      weak = new Set(w.charIds);
      heartbeat.setActivity({ part: 'lv', screen: 'review', level: levelId });
    } catch {
      failed = true;
    }
  }
  onMount(load);

  const shown = $derived.by(() => {
    let list = chars ?? [];
    if (onlyWeak) list = list.filter((c) => weak.has(c.id));
    const k = q.trim().toLowerCase();
    if (k !== '') {
      list = list.filter((c) => c.hanzi.includes(k) || c.pinyin.toLowerCase().includes(k) || c.meaning.toLowerCase().includes(k));
    }
    return list;
  });
</script>

<svelte:head><title>Ôn viết {cap.ten(levelId)} · Zìkǎ</title></svelte:head>

<!-- Lọc “Tất cả / Chữ hay sai” dùng kiểu nút chọn chung của khung học thay cho một nút bật tắt. -->
{#snippet loc(m: boolean)}
  <div class="zk-tabs" class:zk-tabs-deu={m} class:vt-on-loc-m={m} role="tablist" aria-label="Lọc chữ">
    <button type="button" role="tab" class="zk-tab" aria-selected={!onlyWeak} onclick={() => (onlyWeak = false)}>
      Tất cả ({chars?.length ?? 0})
    </button>
    <button type="button" role="tab" class="zk-tab" aria-selected={onlyWeak} disabled={weak.size === 0} onclick={() => (onlyWeak = true)}>
      Chữ hay sai ({weak.size})
    </button>
  </div>
{/snippet}

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="{cap.tuHoa} này chưa có chữ nào để luyện viết.">
      {#snippet action()}<a href="/learn/writing/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if chars === null}
    <Skeleton h="300px" r={16} />
  {:else}
    <!-- Một khung chứa trọn màn ôn: lọc, tìm và lưới chữ (khuôn chung với Lật thẻ). -->
    <section class="block zk-khung" class:zk-khung-m={m}>
      <div class="zk-khung-dau">
        {#if !m}<h2 class="zk-khung-ten">Chữ của {cap.tu}</h2>{/if}
        {@render loc(m)}
      </div>

      <div class="vt-on-tim">
        <span class="ico-in">
          <Icon name="search" size={16} />
          <input class="inp" bind:value={q} placeholder="Tìm chữ, pinyin, nghĩa…" aria-label="Tìm chữ" />
        </span>
        <p class="vt-on-dem num">{shown.length} chữ · bấm vào chữ để nghe</p>
      </div>

      {#if shown.length === 0}
        <EmptyState title="Không có chữ nào khớp." />
      {:else}
        <div class="vt-on-luoi">
          {#each shown as c (c.id)}
            <article class="zk-o vt-on-o" class:vt-on-sai={weak.has(c.id)}>
              <button type="button" class="tian hz vt-on-chu" lang="zh" onclick={() => speak(c.hanzi)} aria-label="Nghe {c.hanzi}">{c.hanzi}</button>
              <div style="flex: 1; min-width: 0">
                <b class="t{c.tone}" style="font-size: 16px">{c.pinyin}</b>
                {#if c.hanViet}<p class="vt-on-hv">{c.hanViet}</p>{/if}
                <p style="font-size: 13.5px; overflow: hidden; text-overflow: ellipsis">{c.meaning}</p>
                <p class="muted num" style="font-size: 12px">{c.strokes} nét{c.lessonNo ? ` · Bài ${c.lessonNo}` : ''}</p>
              </div>
              {#if c.lessonNo}
                <a href="/learn/writing/hsk/{levelId}/lesson/{c.lessonNo}" class="vt-on-viet" aria-label="Tập viết chữ {c.hanzi}">
                  <Icon name="brush" size={17} />
                </a>
              {/if}
            </article>
          {/each}
        </div>
      {/if}
    </section>
  {/if}
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <MTopbar current="lv" back="/learn/writing/hsk/{levelId}" backLabel="Về danh sách bài" title="Ôn viết {cap.ten(levelId)}" sub="Luyện viết · Ôn cả {cap.tu}" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">{@render body(true)}</main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="lv">
    <nav class="crumb"><a href="/learn/writing/hsk/{levelId}">Luyện viết · {cap.ten(levelId)}</a> / <b>Ôn cả {cap.tu}</b></nav>
  </Topbar>
  <main class="page zk-cot-rong" style="display: flex; flex-direction: column; gap: 18px">
    <DauBai
      href="/learn/writing/hsk/{levelId}"
      kicker="{cap.ten(levelId)} · Ôn cả {cap.tu}{chars ? ` · ${chars.length} chữ` : ''}"
      title="Ôn viết {cap.caTen(levelId)}"
    />
    {@render body(false)}
  </main>
{/if}

<style>
  /* Điện thoại: hai nút lọc chia đôi bề ngang, cao 44px cho ngón tay. */
  .vt-on-loc-m {
    width: 100%;
  }
  .vt-on-loc-m .zk-tab {
    height: 44px;
    font-size: 13.5px;
  }
  .vt-on-tim {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .vt-on-dem {
    font-size: 13.5px;
    color: var(--text-2);
  }
  .vt-on-luoi {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr));
    gap: 10px;
  }
  .vt-on-o {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
  }
  .vt-on-o.vt-on-sai {
    border-color: var(--son-line);
    background: var(--son-soft);
  }
  .vt-on-chu {
    width: 72px;
    height: 72px;
    font-size: 44px;
    color: var(--text);
    cursor: pointer;
  }
  .vt-on-chu:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  .vt-on-hv {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-2);
  }
  /* Nút sang bài để tập viết: 44px đủ cho ngón tay. */
  .vt-on-viet {
    flex: none;
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    color: var(--text-2);
    text-decoration: none;
  }
  .vt-on-viet:hover {
    background: var(--surface);
    color: var(--son-text);
  }
  .vt-on-viet:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
</style>
