<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { api } from '$lib/api/client';
  import type { FlashcardsData, LevelLessons, LevelProgress, ProgressRow } from '$lib/api/types';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { num } from '$lib/utils/format';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import LessonCard from '$lib/components/LessonCard.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import BackLink from '$lib/components/BackLink.svelte';

  // Chọn bài — Lật thẻ (07 §4.2).
  const level = $derived(Number(page.params.level));

  let content = $state<LevelLessons | null>(null);
  let prog = $state<LevelProgress | null>(null);
  let failed = $state(false);
  let notFound = $state(false);
  /** 'all' = Tổng hợp; số = lessonId. */
  let selected = $state<number | 'all' | null>(null);
  let fromStart = $state(false);
  let shuffle = $state(settings.value.shuffle);
  let starting = $state(false);
  const wordsCache = $state<Record<number, FlashcardsData>>({});

  $effect(() => {
    heartbeat.setActivity({ part: 'lt', screen: 'level', level });
  });

  async function load(): Promise<void> {
    failed = false;
    notFound = false;
    try {
      const [c, p] = await Promise.all([
        api.get<LevelLessons>(`/learn/levels/${level}/lessons`),
        api.get<LevelProgress>(`/progress/levels/${level}`, { query: { part: 'lt' } }),
      ]);
      content = c;
      prog = p;
      selected = pickDefault();
    } catch (e) {
      if ((e as { status?: number })?.status === 404) notFound = true;
      else failed = true;
    }
  }
  onMount(load);

  const loading = $derived(!failed && !notFound && (content === null || prog === null));
  const rowOf = (lessonId: number): ProgressRow | undefined => prog?.lessons.find((r) => r.lessonId === lessonId);

  function pickDefault(): number | 'all' {
    if (page.url.searchParams.get('review') === '1') return 'all';
    const avail = (content?.lessons ?? []).filter((l) => l.counts.lt > 0);
    const byNo = page.url.searchParams.get('lesson');
    if (byNo) {
      const hit = avail.find((l) => l.no === Number(byNo));
      if (hit) return hit.id;
    }
    const doing = avail.find((l) => (rowOf(l.id)?.position ?? 0) > 0);
    if (doing) return doing.id;
    const unfinished = avail.find((l) => rowOf(l.id)?.status !== 'done');
    return (unfinished ?? avail[0])?.id ?? 'all';
  }

  // Khi chọn một bài: tải từ vựng để xem trước, và chọn mặc định chế độ lật.
  $effect(() => {
    const sel = selected;
    if (typeof sel === 'number') {
      const resumable = (rowOf(sel)?.position ?? 0) > 0;
      fromStart = !resumable;
      if (!wordsCache[sel]) {
        api
          .get<FlashcardsData>(`/learn/lessons/${sel}/flashcards`)
          .then((d) => (wordsCache[sel] = d))
          .catch(() => {});
      }
    } else if (sel === 'all') {
      fromStart = (prog?.review.position ?? 0) === 0;
    }
  });

  // ---- Dữ liệu dẫn xuất cho khối bên phải / tấm dưới ----
  interface PanelData {
    kicker: string;
    title: string;
    countText: string;
    status: string;
    ppct: number;
    wordsLabel: string;
    chips: { hz: string; vi: string }[];
    more: number;
    canResume: boolean;
    at: number;
  }

  const panel = $derived.by<PanelData | null>(() => {
    if (!content) return null;
    if (selected === 'all') {
      const r = prog?.review;
      const n = content.lessons.length;
      const chips = content.lessons.slice(0, 6).map((l) => ({ hz: (l.sampleText || '').split('·')[0].trim(), vi: `Bài ${l.no}` }));
      return {
        kicker: `HSK ${level} · ${n} bài`,
        title: `Tổng hợp HSK ${level}`,
        countText: num(content.review.lt.wordCount),
        status: `${prog?.doneCount ?? 0} / ${prog?.lessonCount ?? 0} bài đã học xong`,
        ppct: (r?.position ?? 0) > 0 && (r?.total ?? 0) > 0 ? ((r!.position / r!.total) * 100) : 0,
        wordsLabel: `Gồm từ của Bài 1 → Bài ${n}`,
        chips,
        more: Math.max(0, content.review.lt.wordCount - chips.length),
        canResume: (r?.position ?? 0) > 0,
        at: (r?.position ?? 0) + 1,
      };
    }
    if (typeof selected === 'number') {
      const l = content.lessons.find((x) => x.id === selected);
      if (!l) return null;
      const r = rowOf(selected);
      const done = r?.status === 'done';
      const pos = r?.position ?? 0;
      const total = l.counts.lt;
      const words = wordsCache[selected]?.words ?? [];
      const chips = words.slice(0, 6).map((w) => ({ hz: w.hanzi, vi: w.meaning }));
      return {
        kicker: `HSK ${level} · Bài ${l.no}`,
        title: l.title,
        countText: num(total),
        status: done ? 'Đã học xong · lật lại để ôn' : pos > 0 ? `Đang ở thẻ ${pos + 1} / ${total}` : 'Chưa học',
        ppct: done ? 100 : pos > 0 ? (pos / Math.max(1, total)) * 100 : 0,
        wordsLabel: 'Từ trong bài',
        chips,
        more: Math.max(0, total - chips.length),
        canResume: pos > 0,
        at: pos + 1,
      };
    }
    return null;
  });

  const selLesson = $derived(typeof selected === 'number' ? content?.lessons.find((l) => l.id === selected) : undefined);

  async function start(): Promise<void> {
    if (!panel || starting) return;
    starting = true;
    try {
      if (selected === 'all') {
        if (fromStart && (prog?.review.position ?? 0) > 0) {
          // Tổng hợp không có restart riêng; bắt đầu lại từ đầu bằng cách bỏ qua lượt dở ở màn lật.
        }
        await goto(`/learn/flashcards/hsk/${level}/review`, { state: { shuffle, fromStart } });
      } else if (selLesson) {
        if (fromStart && (rowOf(selLesson.id)?.position ?? 0) > 0) {
          await api.post(`/progress/lessons/${selLesson.id}/lt/restart`).catch(() => {});
        }
        await goto(`/learn/flashcards/hsk/${level}/lesson/${selLesson.no}`, { state: { shuffle, fromStart } });
      }
    } finally {
      starting = false;
    }
  }
</script>

<svelte:head><title>Chọn bài · HSK {level} · Zìkǎ</title></svelte:head>

{#snippet lessonGrid(mobile: boolean)}
  {#each content?.lessons ?? [] as l (l.id)}
    {@const r = rowOf(l.id)}
    {@const done = r?.status === 'done'}
    {@const doing = (r?.position ?? 0) > 0}
    {@const soon = l.counts.lt === 0}
    <LessonCard
      no={l.no}
      title={l.title}
      sample={l.sampleText}
      sub="{num(l.counts.lt)} từ"
      {done}
      {soon}
      {mobile}
      pct={done ? 100 : doing ? (r!.position / Math.max(1, l.counts.lt)) * 100 : 0}
      on={selected === l.id}
      tag={doing ? 'Đang học' : ''}
      tagClass="tag tag-g"
      onpick={() => (selected = l.id)}
    />
  {/each}
{/snippet}

{#snippet allCard(mobile: boolean)}
  {#if content && content.lessons.length > 0}
    <button type="button" class="les les-all" class:on={selected === 'all'} aria-pressed={selected === 'all'} onclick={() => (selected = 'all')}>
      <span class="ib" style="width: {mobile ? 44 : 56}px; height: {mobile ? 44 : 56}px; background: var(--surface); border-radius: 14px; border-width: 2px"><Icon name="cards" size={mobile ? 22 : 26} /></span>
      <span style="flex: 1 1 240px; min-width: 0; display: flex; flex-direction: column; gap: 2px">
        <b class="disp" style="font-size: {mobile ? 16 : 18}px; font-weight: 800">Tổng hợp HSK {level}</b>
        <span style="font-size: 13.5px">Ôn toàn bộ {num(content.review.lt.wordCount)} từ của {content.lessons.length} bài.{mobile ? '' : ' Nên lật sau khi học xong các bài.'}</span>
      </span>
      {#if !mobile}
        <span style="flex: 0 1 200px; display: flex; flex-direction: column; gap: 6px">
          <span class="num" style="font-size: 13px; font-weight: 700">{prog?.doneCount ?? 0} / {prog?.lessonCount ?? 0} bài đã học xong</span>
          <Bar value={prog?.doneCount ?? 0} total={prog?.lessonCount ?? 0} trackStyle="background: rgba(255,255,255,.7)" />
        </span>
      {/if}
    </button>
  {/if}
{/snippet}

{#snippet chips()}
  {#if panel}
    <div>
      <p class="lbl">{panel.wordsLabel}</p>
      <ul style="display: flex; flex-wrap: wrap; gap: 6px">
        {#each panel.chips as c, i (i)}
          <li class="chip"><span class="hz" lang="zh" style="font-size: 16px">{c.hz}</span><span style="font-size: 12px; color: var(--text-3)">{c.vi}</span></li>
        {/each}
        {#if panel.more > 0}<li class="muted" style="font-size: 12.5px; font-weight: 600; align-self: center; padding: 0 4px">+{num(panel.more)} từ</li>{/if}
      </ul>
    </div>
  {/if}
{/snippet}

{#if notFound}
  <Topbar current="lt" />
  <main class="page">
    <EmptyState title="Cấp này chưa có hoặc đã bị ẩn.">
      {#snippet action()}<a href="/learn/flashcards" class="btn">Về Bàn học</a>{/snippet}
    </EmptyState>
  </main>
{:else if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards" class="btn btn-bare" aria-label="Về Bàn học"><Icon name="left" size={22} /></a>
      <span class="lv{level}" style="padding: 4px 10px; border: 1px solid var(--line); border-radius: 10px; font-weight: 800; font-size: 13px">HSK {level}</span>
      <b style="font-size: 15px">Chọn bài</b>
      <span style="margin-left: auto"><Switcher current="lt" mobile /></span>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">
      {#if failed}
        <EmptyState title="Không tải được dữ liệu.">
          {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
        </EmptyState>
      {:else if loading}
        <Skeleton h="64px" r={16} /><Skeleton h="72px" r={16} /><Skeleton h="72px" r={16} /><Skeleton h="72px" r={16} />
      {:else if content}
        <div class="lv{level}" style="border: 1px solid var(--line); border-radius: 16px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px">
          <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 700">
            <span class="num">{content.lessons.length} bài · {num(content.review.lt.wordCount)} từ</span>
            <span class="num">{prog?.doneCount ?? 0}/{prog?.lessonCount ?? 0} bài đã học</span>
          </div>
          <Bar value={prog?.doneCount ?? 0} total={prog?.lessonCount ?? 0} trackStyle="background: rgba(255,255,255,.7)" />
        </div>
        <div style="display: flex; justify-content: space-between; align-items: baseline">
          <h2 class="h2">Các bài</h2>
          <span class="muted num" style="font-size: 13px; font-weight: 600">{prog?.doneCount ?? 0}/{prog?.lessonCount ?? 0} bài xong</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px">
          {@render lessonGrid(true)}
          {@render allCard(true)}
        </div>
      {/if}
    </main>
    {#if panel}
      <div class="msheet">
        <div style="display: flex; align-items: center; gap: 12px">
          <span class="ib lv{level}" style="width: 46px; height: 46px; border-radius: 12px"><span class="bignum" style="font-size: 22px">{level}</span></span>
          <div style="flex: 1 1 auto; min-width: 0">
            <p class="muted" style="font-size: 12px; font-weight: 700">{panel.kicker}</p>
            <b style="font-size: 16px">{panel.title}</b>
          </div>
          <span class="num muted" style="font-size: 13px; font-weight: 700; white-space: nowrap">{panel.countText} từ</span>
        </div>
        <div class="row" style="justify-content: space-between; gap: 10px">
          {#if panel.canResume}
            <div class="seg">
              <button type="button" class:on={!fromStart} onclick={() => (fromStart = false)}>Lật tiếp</button>
              <button type="button" class:on={fromStart} onclick={() => (fromStart = true)}>Từ đầu</button>
            </div>
          {/if}
          <label class="sw" style="margin-left: auto"><input type="checkbox" bind:checked={shuffle} /><span></span>Trộn</label>
        </div>
        <button type="button" class="btn btn-son btn-lg" style="width: 100%" disabled={starting} onclick={start}>Bắt đầu lật</button>
      </div>
    {/if}
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="lt" />
  <main class="page page-wide" style="max-width: 1240px; display: flex; flex-direction: column; gap: 24px">
    <nav aria-label="Đường dẫn" class="crumb">
      <a href="/learn/flashcards">Bàn học</a><span>/</span><b>HSK {level}</b>
    </nav>

    {#if failed}
      <EmptyState title="Không tải được dữ liệu.">
        {#snippet action()}<button type="button" class="btn" onclick={load}>Thử lại</button>{/snippet}
      </EmptyState>
    {:else if loading}
      <Skeleton h="130px" r={24} />
      <Skeleton h="400px" r={22} />
    {:else if content}
      <section class="lv{level}" style="border: 1px solid var(--line); border-radius: 24px; box-shadow: var(--sh-md); padding: 22px 26px; display: flex; flex-wrap: wrap; gap: 18px 32px; align-items: center">
        <BackLink href="/learn/flashcards" label="Bàn học" />
        <div style="display: flex; align-items: baseline; gap: 6px"><span class="disp" style="font-size: 20px; font-weight: 800">HSK</span><span class="bignum" style="font-size: 72px">{level}</span></div>
        <div style="flex: 1 1 300px; display: flex; flex-direction: column; gap: 8px">
          <h1 style="font-size: 30px; line-height: 1.2">Chọn bài để lật</h1>
          <p class="num" style="font-weight: 600">{content.lessons.length} bài · {num(content.review.lt.wordCount)} từ</p>
          <Bar value={prog?.doneCount ?? 0} total={prog?.lessonCount ?? 0} trackStyle="background: rgba(255,255,255,.7); max-width: 480px" />
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px">
          <span class="bignum" style="font-size: 44px">{prog?.doneCount ?? 0}/{prog?.lessonCount ?? 0}</span>
          <span style="font-size: 13px; font-weight: 700">bài đã học xong</span>
        </div>
      </section>

      <div style="display: flex; flex-wrap: wrap; gap: 28px; align-items: flex-start">
        <section style="flex: 999 1 620px; min-width: 0; display: flex; flex-direction: column; gap: 14px">
          <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px">
            <h2 class="h2">Các bài</h2>
            <span class="muted num" style="font-size: 14px; font-weight: 600">{prog?.doneCount ?? 0} / {prog?.lessonCount ?? 0} bài đã học xong</span>
          </div>
          <div class="les-grid">
            {@render lessonGrid(false)}
            {@render allCard(false)}
          </div>
        </section>

        <aside class="block" style="flex: 1 1 340px; min-width: 0; padding: 22px 24px; display: flex; flex-direction: column; gap: 16px">
          {#if panel}
            <div style="display: flex; align-items: center; gap: 14px">
              <span class="ib lv{level}" style="width: 60px; height: 60px; border-radius: 16px"><span class="bignum" style="font-size: 30px">{level}</span></span>
              <div style="min-width: 0">
                <p class="muted" style="font-size: 13px; font-weight: 700">{panel.kicker}</p>
                <h2 style="font-size: 24px; font-weight: 800; line-height: 1.15">{panel.title}</h2>
                <p class="num" style="font-size: 14px; font-weight: 600">{panel.countText} từ</p>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; background: var(--surface-2); border-radius: 14px">
              <span class="num" style="font-size: 13.5px; font-weight: 700">{panel.status}</span>
              <Bar pct={panel.ppct} />
            </div>

            {@render chips()}

            <fieldset style="border: 0; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px">
              <legend class="lbl" style="padding: 0; margin-bottom: 8px">Lật những thẻ nào?</legend>
              {#if panel.canResume}
                <label class="chk chk-box"><input type="radio" name="d-mode" checked={!fromStart} onchange={() => (fromStart = false)} /> Lật tiếp từ thẻ {panel.at}</label>
              {/if}
              <label class="chk chk-box"><input type="radio" name="d-mode" checked={fromStart} onchange={() => (fromStart = true)} /> Từ đầu · {panel.countText} từ</label>
            </fieldset>

            <label class="sw"><input type="checkbox" bind:checked={shuffle} /><span></span>Trộn thứ tự thẻ</label>

            <button type="button" class="btn btn-son btn-lg" style="width: 100%" disabled={starting} onclick={start}>Bắt đầu lật</button>
          {/if}
        </aside>
      </div>
    {/if}
  </main>
{/if}

<style>
  .les-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(180px, 100%), 1fr));
    gap: 12px;
  }
  .chip {
    display: flex;
    align-items: baseline;
    gap: 6px;
    padding: 5px 10px;
    background: var(--surface-2);
    border: 1.5px solid var(--line);
    border-radius: 10px;
  }
  .chk-box {
    padding: 0 12px;
    border: 1.5px solid var(--line-3);
    border-radius: 12px;
    min-height: 46px;
  }
</style>
