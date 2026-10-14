<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
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

<svelte:head><title>Ôn viết HSK {levelId} · Zìkǎ</title></svelte:head>

{#snippet body(_m: boolean)}
  {#if failed}
    <EmptyState title="Cấp này chưa có chữ nào để luyện viết.">
      {#snippet action()}<a href="/learn/writing/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if chars === null}
    <Skeleton h="300px" r={16} />
  {:else}
    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center">
      <span class="ico-in" style="flex: 1 1 220px">
        <Icon name="search" size={16} />
        <input class="inp" bind:value={q} placeholder="Tìm chữ, pinyin, nghĩa…" />
      </span>
      <button type="button" class="btn btn-sm" class:apps-on={onlyWeak} disabled={weak.size === 0} onclick={() => (onlyWeak = !onlyWeak)}>
        Chỉ chữ hay sai ({weak.size})
      </button>
    </div>

    <p class="muted" style="font-size: 13.5px">{shown.length} chữ · bấm vào chữ để nghe</p>

    {#if shown.length === 0}
      <EmptyState title="Không có chữ nào khớp." />
    {:else}
      <div class="grid">
        {#each shown as c (c.id)}
          <article class="paper card" class:weak={weak.has(c.id)}>
            <button type="button" class="tian hz" lang="zh" style="width: 72px; height: 72px; font-size: 44px; cursor: pointer"
                    onclick={() => speak(c.hanzi)} aria-label="Nghe {c.hanzi}">{c.hanzi}</button>
            <div style="flex: 1; min-width: 0">
              <b class="t{c.tone}" style="font-size: 16px">{c.pinyin}</b>
              {#if c.hanViet}<p class="muted" style="font-size: 12px; font-weight: 700; text-transform: uppercase">{c.hanViet}</p>{/if}
              <p style="font-size: 13.5px; overflow: hidden; text-overflow: ellipsis">{c.meaning}</p>
              <p class="muted num" style="font-size: 12px">{c.strokes} nét{c.lessonNo ? ` · Bài ${c.lessonNo}` : ''}</p>
            </div>
            {#if c.lessonNo}
              <a href="/learn/writing/hsk/{levelId}/lesson/{c.lessonNo}" class="btn btn-bare btn-sm" aria-label="Tập viết chữ này">
                <Icon name="brush" size={16} />
              </a>
            {/if}
          </article>
        {/each}
      </div>
    {/if}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/writing/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Ôn viết HSK {levelId}</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/writing/hsk/{levelId}">Luyện viết · HSK {levelId}</a> / <b>Ôn cả cấp</b></nav>
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 920px; display: flex; flex-direction: column; gap: 16px">
    <BackLink href="/learn/writing/hsk/{levelId}" label="Chọn bài" />
    
    <h1 style="font-size: 28px">Ôn viết cả cấp HSK {levelId}</h1>
    {@render body(false)}
  </main>
{/if}

<style>
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr));
    gap: 12px;
  }
  .card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
  }
  .card.weak {
    border-color: var(--son-line);
    background: var(--son-soft);
  }
</style>
