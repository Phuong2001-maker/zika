<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import type { FlashcardsData, Word } from '$lib/api/types';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { completePart, lessonByNo, saveProgress, seededShuffle } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';

  // Lật thẻ — màn học chính (07 §5.1).
  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<FlashcardsData | null>(null);
  let failed = $state(false);
  let order = $state<number[]>([]);   // chỉ số thẻ theo thứ tự học
  let i = $state(0);                  // đang ở thẻ thứ mấy
  let flipped = $state(false);
  let known = $state(0);
  let notYet = $state(0);
  let finished = $state(false);

  const cards = $derived(data?.words ?? []);
  const total = $derived(order.length);
  const card = $derived<Word | null>(cards[order[i]] ?? null);
  const pct = $derived(total > 0 ? Math.round((i / total) * 100) : 0);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (l === null) {
        failed = true;
        return;
      }
      const d = await api.get<FlashcardsData>(`/learn/lessons/${l.id}/flashcards`);
      data = d;

      const idx = d.words.map((_, k) => k);
      order = settings.value.shuffle ? seededShuffle(idx, l.id) : idx;
      i = 0;
      known = 0;
      notYet = 0;
      flipped = false;
      finished = d.words.length === 0;
      heartbeat.setActivity({ part: 'lt', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);

  // Đổi bài trên URL → tải lại từ đầu.
  $effect(() => {
    void levelId;
    void lessonNo;
    void load();
  });

  function flip(): void {
    flipped = !flipped;
    if (flipped && settings.value.autoSpeak && card) void speak(card.hanzi);
  }

  /** Sang thẻ sau; hết thẻ thì vào màn tổng kết và ghi “đã học xong”. */
  async function next(mark: 'known' | 'notYet' | 'skip'): Promise<void> {
    if (!data || !card) return;
    if (mark === 'known') known++;
    if (mark === 'notYet') notYet++;

    const at = i + 1;
    flipped = false;

    if (at >= total) {
      finished = true;
      try {
        await completePart(data.lesson.id, 'lt');
      } catch {
        toast.show('Đã học xong nhưng chưa lưu được tiến độ.');
      }
      return;
    }

    i = at;
    // Lưu vị trí để lần sau mở lại đúng chỗ; lỗi mạng thì bỏ qua, không chặn người học.
    void saveProgress(data.lesson.id, 'lt', at, total, { order, known, notYet }).catch(() => {});
  }

  function prev(): void {
    if (i > 0) {
      i--;
      flipped = false;
    }
  }

  function restart(): void {
    i = 0;
    known = 0;
    notYet = 0;
    flipped = false;
    finished = false;
  }

  // Phím tắt: cách = lật, ← → = lùi/tiếp, 1 = chưa thuộc, 2 = đã thuộc.
  function onKey(e: KeyboardEvent): void {
    if (finished || !card) return;
    const tag = (e.target as HTMLElement | null)?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); flip(); }
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'ArrowRight') void next('skip');
    else if (e.key === '1') void next('notYet');
    else if (e.key === '2') void next('known');
  }

  const frontText = $derived.by(() => {
    if (!card) return '';
    return settings.value.cardFront === 'hanzi' ? card.hanzi : settings.value.cardFront === 'pinyin' ? card.pinyin : card.meaning;
  });
  const frontLabel = $derived(
    settings.value.cardFront === 'hanzi' ? 'Chữ Hán' : settings.value.cardFront === 'pinyin' ? 'Pinyin' : 'Nghĩa tiếng Việt',
  );
</script>

<svelte:head><title>{data ? `${data.lesson.title} · Lật thẻ` : 'Lật thẻ'} · Zìkǎ</title></svelte:head>
<svelte:window onkeydown={onKey} />

{#snippet pinyinRow(py: string, size: number)}
  <span class="num" style="font-size: {size}px; font-weight: 600" class:no-tone={!settings.value.toneColors}>
    {#each toneParts(py) as p (p.text + p.tone)}<span class="t{p.tone}">{p.text}</span>{/each}
  </span>
{/snippet}

{#snippet cardFace(m: boolean)}
  {#if card}
    <div class="deckwrap" style="height: {m ? 300 : 380}px">
      <span class="stk stk-a"></span>
      <span class="stk stk-b"></span>
      <button type="button" class={flipped ? 'flip is-back' : 'flip'} onclick={flip} aria-label={flipped ? 'Bấm để xem lại mặt trước' : 'Lật thẻ'}>
        <span class="flip-inner">
          <span class="face face-front">
            <span class="kicker">{frontLabel}</span>
            <span class={settings.value.cardFront === 'hanzi' ? 'hz' : ''} lang={settings.value.cardFront === 'hanzi' ? 'zh' : 'vi'}
                  style="font-size: {settings.value.cardFront === 'hanzi' ? (m ? 56 : 76) : (m ? 28 : 36)}px; font-weight: 800; line-height: 1.15">
              {frontText}
            </span>
            <span class="muted" style="font-size: 13px">Bấm để lật</span>
          </span>
          <span class="face face-back">
            {@render pinyinRow(card.pinyin, m ? 18 : 22)}
            <span class="hz" lang="zh" style="font-size: {m ? 56 : 76}px; line-height: 1.1">{card.hanzi}</span>
            {#if settings.value.showHanViet && card.hanViet}
              <span class="muted" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em">{card.hanViet}</span>
            {/if}
            <span style="font-size: {m ? 17 : 19}px; font-weight: 600">{card.meaning}</span>
          </span>
        </span>
      </button>
    </div>
  {/if}
{/snippet}

{#snippet controls(m: boolean)}
  <div class="ctrl">
    <button type="button" class="btn btn-icon" aria-label="Thẻ trước" disabled={i === 0} onclick={prev}><Icon name="left" size={18} /></button>
    <button type="button" class="btn btn-lg grow" onclick={() => next('notYet')}>Chưa thuộc</button>
    <button type="button" class="btn btn-son btn-lg grow" onclick={() => next('known')}>Đã thuộc</button>
    <button type="button" class="btn btn-icon" aria-label="Bỏ qua thẻ này" onclick={() => next('skip')}><Icon name="right" size={18} /></button>
  </div>
  {#if !m}
    <p class="muted" style="font-size: 12.5px; text-align: center">
      Phím tắt: <span class="kbd">cách</span> lật · <span class="kbd">1</span> chưa thuộc · <span class="kbd">2</span> đã thuộc · <span class="kbd">←</span> <span class="kbd">→</span> chuyển thẻ
    </p>
  {/if}
{/snippet}

{#snippet examples()}
  {#if card && settings.value.showExamples && card.examples.length > 0}
    <section class="block" style="padding: 18px 20px; display: flex; flex-direction: column; gap: 14px">
      <p class="kicker">Câu ví dụ</p>
      {#each card.examples as ex (ex.zh)}
        <div style="display: flex; flex-direction: column; gap: 4px">
          <div style="display: flex; align-items: flex-start; gap: 8px">
            <span class="hz" lang="zh" style="font-size: 19px; flex: 1">
              {#if ex.highlight && ex.zh.includes(ex.highlight)}
                {@const parts = ex.zh.split(ex.highlight)}
                {parts[0]}<b class="hl">{ex.highlight}</b>{parts.slice(1).join(ex.highlight)}
              {:else}{ex.zh}{/if}
            </span>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Nghe câu" onclick={() => speak(ex.zh)}><Icon name="speaker" size={17} /></button>
          </div>
          {#if ex.pinyin}<span class="muted" style="font-size: 13.5px">{ex.pinyin}</span>{/if}
          {#if ex.vi}<span style="font-size: 14px">{ex.vi}</span>{/if}
        </div>
      {/each}
    </section>
  {/if}
{/snippet}

{#snippet done(m: boolean)}
  <section class="block" style="padding: {m ? 22 : 32}px; display: flex; flex-direction: column; align-items: center; gap: 16px; text-align: center">
    <span class="stamp" style="font-size: {m ? 26 : 34}px">完成</span>
    <h1 style="font-size: {m ? 24 : 30}px">Xong bài {data?.lesson.no}!</h1>
    <p class="muted">Bạn đã lật hết {total} thẻ của bài “{data?.lesson.title}”.</p>
    <div class="sum">
      <span><b class="bignum" style="font-size: 28px; color: var(--green-text)">{known}</b><span class="muted">đã thuộc</span></span>
      <span><b class="bignum" style="font-size: 28px; color: var(--son-text)">{notYet}</b><span class="muted">chưa thuộc</span></span>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center">
      <button type="button" class="btn" onclick={restart}><Icon name="repeat" size={16} /> Lật lại</button>
      {#if data?.next}
        <a href="/learn/flashcards/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son">Bài tiếp: {data.next.title}</a>
      {:else}
        <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
      {/if}
    </div>
  </section>
{/snippet}

{#if failed}
  <main class="page">
    <EmptyState title="Không mở được bài này." desc="Bài có thể đang ẩn hoặc đã bị xoá.">
      {#snippet action()}<a href="/learn/flashcards/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  </main>
{:else if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <span style="flex: 1; min-width: 0; text-align: center">
        <b style="display: block; font-size: 15px">{data?.lesson.title ?? '…'}</b>
        <span class="muted num" style="font-size: 12px">Thẻ {Math.min(i + 1, total)} / {total}</span>
      </span>
      <Avatar size={36} />
    </header>
    <div style="padding: 0 16px"><Bar value={i} {total} /></div>

    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">
      {#if data === null}
        <Skeleton h="300px" r={20} />
      {:else if finished}
        {@render done(true)}
      {:else}
        {@render cardFace(true)}
        {@render examples()}
      {/if}
    </main>

    {#if data !== null && !finished}
      <div class="msheet">{@render controls(true)}</div>
    {/if}
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px">
      <a href="/learn/flashcards/hsk/{levelId}">HSK {levelId}</a> / <b>Bài {lessonNo}{data ? ` · ${data.lesson.title}` : ''}</b>
    </nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px">
      <Switcher current="lt" />
      <Avatar size={44} />
    </div>
  </header>

  <main class="page" style="display: flex; flex-direction: column; gap: 20px">
    {#if data === null}
      <Skeleton h="420px" r={22} />
    {:else if finished}
      {@render done(false)}
    {:else}
      <div style="display: flex; align-items: center; gap: 16px">
        <span class="num" style="font-weight: 700">Thẻ {Math.min(i + 1, total)} / {total}</span>
        <span style="flex: 1"><Bar value={i} {total} /></span>
        <span class="muted num" style="font-size: 13px">{pct}%</span>
      </div>

      <div style="display: flex; flex-wrap: wrap; gap: 28px; align-items: flex-start">
        <div style="flex: 999 1 520px; min-width: 0; display: flex; flex-direction: column; gap: 18px">
          {@render cardFace(false)}
          {@render controls(false)}
        </div>
        <aside style="flex: 1 1 320px; min-width: 0; display: flex; flex-direction: column; gap: 16px">
          {@render examples()}
          <section class="paper" style="padding: 14px 16px; display: flex; gap: 18px; justify-content: center">
            <span style="text-align: center"><b class="bignum" style="font-size: 22px; color: var(--green-text)">{known}</b><br><span class="muted" style="font-size: 12.5px">đã thuộc</span></span>
            <span style="text-align: center"><b class="bignum" style="font-size: 22px; color: var(--son-text)">{notYet}</b><br><span class="muted" style="font-size: 12.5px">chưa thuộc</span></span>
          </section>
        </aside>
      </div>
    {/if}
  </main>
{/if}

<style>
  .ctrl {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .sum {
    display: flex;
    gap: 28px;
  }
  .sum span {
    display: flex;
    flex-direction: column;
    align-items: center;
    font-size: 13px;
  }
</style>
