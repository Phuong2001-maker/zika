<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
  import { completeReview, saveReviewProgress, seededShuffle } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';
  import type { Word } from '$lib/api/types';

  // Lật thẻ · Tổng hợp cả cấp (04 §4.9 part = lt).
  const levelId = $derived(Number(page.params.level));

  let words = $state<Word[] | null>(null);
  let order = $state<number[]>([]);
  let failed = $state(false);
  let i = $state(0);
  let flipped = $state(false);
  let known = $state(0);
  let finished = $state(false);

  const total = $derived(order.length);
  const card = $derived<Word | null>(words?.[order[i]] ?? null);

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.get<{ level: { name: string }; words: Word[] }>(`/learn/levels/${levelId}/review/lt`);
      words = res.words;
      const idx = res.words.map((_, k) => k);
      order = settings.value.shuffle ? seededShuffle(idx, levelId * 7919) : idx;
      i = 0;
      known = 0;
      flipped = false;
      finished = res.words.length === 0;
      heartbeat.setActivity({ part: 'lt', screen: 'review', level: levelId });
    } catch {
      failed = true;
    }
  }
  onMount(load);

  function flip(): void {
    flipped = !flipped;
    if (flipped && settings.value.autoSpeak && card) void speak(card.hanzi);
  }

  async function next(mark: boolean): Promise<void> {
    if (mark) known++;
    flipped = false;
    const at = i + 1;
    if (at >= total) {
      finished = true;
      try {
        await completeReview(levelId, 'lt');
      } catch {
        toast.show('Xong rồi nhưng chưa lưu được tiến độ.');
      }
      return;
    }
    i = at;
    void saveReviewProgress(levelId, 'lt', at, total).catch(() => {});
  }
</script>

<svelte:head><title>Tổng hợp HSK {levelId} · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Cấp này chưa có từ nào.">
      {#snippet action()}<a href="/learn/flashcards/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if words === null}
    <Skeleton h="340px" r={20} />
  {:else if finished}
    <section class="block" style="padding: {m ? 22 : 32}px; display: flex; flex-direction: column; align-items: center; gap: 14px; text-align: center">
      <span class="stamp" style="font-size: {m ? 26 : 34}px">完成</span>
      <h1 style="font-size: {m ? 24 : 30}px">Xong Tổng hợp HSK {levelId}</h1>
      <p class="muted">Bạn đã lật hết {total} thẻ · nhớ được {known} từ.</p>
      <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center">
        <button type="button" class="btn" onclick={load}><Icon name="repeat" size={16} /> Lật lại</button>
        <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
      </div>
    </section>
  {:else if card}
    <div style="display: flex; align-items: center; gap: 14px">
      <span class="num" style="font-weight: 700">Thẻ {i + 1} / {total}</span>
      <span style="flex: 1"><Bar value={i} {total} /></span>
      {#if card.lessonNo}<span class="tag">Bài {card.lessonNo}</span>{/if}
    </div>

    <div class="deckwrap" style="height: {m ? 290 : 360}px">
      <span class="stk stk-a"></span>
      <span class="stk stk-b"></span>
      <button type="button" class={flipped ? 'flip is-back' : 'flip'} onclick={flip} aria-label="Lật thẻ">
        <span class="flip-inner">
          <span class="face face-front">
            <span class="kicker">Nghĩa tiếng Việt</span>
            <span style="font-size: {m ? 26 : 34}px; font-weight: 800">{card.meaning}</span>
          </span>
          <span class="face face-back">
            <span class="num" style="font-size: {m ? 17 : 21}px; font-weight: 600" class:no-tone={!settings.value.toneColors}>
              {#each toneParts(card.pinyin) as p (p.text + p.tone)}<span class="t{p.tone}">{p.text}</span>{/each}
            </span>
            <span class="hz" lang="zh" style="font-size: {m ? 54 : 72}px">{card.hanzi}</span>
            {#if settings.value.showHanViet && card.hanViet}
              <span class="muted" style="font-size: 12.5px; font-weight: 700; text-transform: uppercase">{card.hanViet}</span>
            {/if}
          </span>
        </span>
      </button>
    </div>

    <div style="display: flex; gap: 10px">
      <button type="button" class="btn btn-lg grow" onclick={() => next(false)}>Chưa thuộc</button>
      <button type="button" class="btn btn-son btn-lg grow" onclick={() => next(true)}>Đã thuộc</button>
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Tổng hợp HSK {levelId}</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/flashcards/hsk/{levelId}">HSK {levelId}</a> / <b>Tổng hợp</b></nav>
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 760px; display: flex; flex-direction: column; gap: 18px">
    <BackLink href="/learn/flashcards/hsk/{levelId}" label="Chọn bài" />
    {@render body(false)}</main>
{/if}
