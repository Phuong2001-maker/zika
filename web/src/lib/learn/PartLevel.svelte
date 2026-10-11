<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import type { LessonItem, LevelLessons, LevelProgress, Part, ProgressRow } from '$lib/api/types';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { num } from '$lib/utils/format';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { PART, PART_TEXTS } from '$lib/parts';
  import { levelLessons, levelProgress, progressOf } from '$lib/learn/lesson';

  // Khuôn “Chọn bài” dùng chung cho 5 phần (07 §5.3).
  let { part }: { part: Exclude<Part, 'lt'> } = $props();

  const info = $derived(PART[part]);
  const texts = $derived(PART_TEXTS[part]);
  const levelId = $derived(Number(page.params.level));

  let data = $state<LevelLessons | null>(null);
  let prog = $state<LevelProgress | null>(null);
  let failed = $state(false);
  let picked = $state<number | 'all' | null>(null);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const [d, p] = await Promise.all([levelLessons(levelId, true), levelProgress(levelId, part)]);
      data = d;
      prog = p;
      // Mặc định chọn bài đang dở, không có thì bài đầu chưa xong.
      const doing = d.lessons.find((l) => (progressOf(p, l.id)?.position ?? 0) > 0);
      const todo = d.lessons.find((l) => progressOf(p, l.id)?.status !== 'done');
      picked = (doing ?? todo ?? d.lessons[0])?.id ?? null;
    } catch {
      failed = true;
    }
  }

  onMount(() => {
    heartbeat.setActivity({ part, screen: 'level', level: levelId });
    void load();
  });

  $effect(() => {
    void levelId;
    void load();
  });

  /** Số lượng của phần này trong một bài (từ / câu / chữ / điểm). */
  const countOf = (l: LessonItem): number => l.counts[part] ?? 0;

  const lessons = $derived(data?.lessons ?? []);
  const doneCount = $derived(lessons.filter((l) => progressOf(prog, l.id)?.status === 'done').length);
  const current = $derived(picked === 'all' ? null : lessons.find((l) => l.id === picked) ?? null);
  const currentProg = $derived<ProgressRow | null>(current ? progressOf(prog, current.id) : null);

  /** Dòng mô tả dưới tên bài, theo từng phần. */
  function subOf(l: LessonItem): string {
    return l.sub?.[part] ?? `${countOf(l)} ${{ bt: 'câu', kt: 'câu', lv: 'chữ', ht: 'câu', np: 'điểm' }[part]}`;
  }

  function statusOf(l: LessonItem): { label: string; cls: string } {
    const p = progressOf(prog, l.id);
    if (countOf(l) === 0) return { label: 'Sắp có', cls: 'tag' };
    if (part === 'kt' && p?.bestScore != null) {
      return { label: `Điểm ${num(p.bestScore)}/${num(p.maxScore ?? 0)}`, cls: 'tag tag-ok' };
    }
    if (p?.status === 'done') return { label: 'Đã xong', cls: 'tag tag-ok' };
    if ((p?.position ?? 0) > 0 && texts.doingTag) return { label: texts.doingTag, cls: 'tag tag-g' };
    return { label: '', cls: '' };
  }

  const startHref = $derived(
    picked === 'all'
      ? `/learn/${info.slug}/hsk/${levelId}/review`
      : current
        ? `/learn/${info.slug}/hsk/${levelId}/lesson/${current.no}`
        : '',
  );

  /** Số liệu khối Tổng hợp của cấp, mỗi phần một kiểu. */
  const reviewCount = $derived.by(() => {
    const rv = data?.review;
    if (!rv) return 0;
    return part === 'bt' ? rv.bt.count : part === 'lv' ? rv.lv.charCount : part === 'ht' ? rv.ht.dialogueCount : part === 'np' ? rv.np.pointCount : 0;
  });
</script>

<svelte:head><title>{info.name} · HSK {levelId} · Zìkǎ</title></svelte:head>

{#snippet lessonCard(l: LessonItem)}
  {@const st = statusOf(l)}
  {@const p = progressOf(prog, l.id)}
  <button
    type="button"
    class="les"
    class:on={picked === l.id}
    class:done={p?.status === 'done'}
    class:soon={countOf(l) === 0}
    disabled={countOf(l) === 0}
    onclick={() => (picked = l.id)}
  >
    <span style="display: flex; align-items: baseline; gap: 8px">
      <b class="num" style="font-size: 13px; color: var(--text-3)">Bài {l.no}</b>
      <b style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{l.title}</b>
      {#if st.label}<span class={st.cls}>{st.label}</span>{/if}
    </span>
    <span class="muted" style="font-size: 13px">{subOf(l)}</span>
    {#if p && p.total > 0 && p.status !== 'done' && p.position > 0}
      <Bar value={p.position} total={p.total} />
    {/if}
  </button>
{/snippet}

{#snippet panel(m: boolean)}
  <section class="block" style="padding: {m ? 18 : 22}px {m ? 18 : 24}px; display: flex; flex-direction: column; gap: 14px">
    {#if picked === 'all'}
      <p class="kicker">HSK {levelId} · cuối cấp</p>
      <h2 style="font-size: {m ? 20 : 24}px; font-weight: 800">{texts.allTitle(levelId)}</h2>
      <p class="muted" style="font-size: 14px">{texts.allDesc(levelId, reviewCount, lessons.length)}</p>
      <ul style="display: flex; flex-direction: column; gap: 6px">
        {#each texts.allLines(reviewCount, lessons.length, { minutes: Math.round((data?.review.kt.mockTests[0]?.durationSec ?? 3300) / 60) }) as line (line)}
          <li style="display: flex; gap: 8px; font-size: 14px"><Icon name="check" size={16} /> {line}</li>
        {/each}
      </ul>
      <a href={startHref} class="btn btn-son btn-lg" style="width: 100%">{texts.allStart}</a>
    {:else if current}
      <p class="kicker">HSK {levelId} · Bài {current.no}</p>
      <h2 style="font-size: {m ? 20 : 24}px; font-weight: 800">{current.title}</h2>
      {#if current.sampleText}<p class="hz" lang="zh" style="font-size: 16px; color: var(--text-2)">{current.sampleText}</p>{/if}
      <p class="muted" style="font-size: 14px">{subOf(current)}</p>

      {#if part === 'kt' && currentProg?.bestScore != null}
        <p style="font-size: 14px; font-weight: 700">Điểm cao nhất: {num(currentProg.bestScore)} / {num(currentProg.maxScore ?? 0)}</p>
      {:else if currentProg && currentProg.position > 0 && currentProg.total > 0}
        <div style="display: flex; flex-direction: column; gap: 6px">
          <span style="font-size: 13px; font-weight: 700">Đang ở {currentProg.position + 1} / {currentProg.total}</span>
          <Bar value={currentProg.position} total={currentProg.total} />
        </div>
      {:else if currentProg?.status === 'done'}
        <p style="font-size: 14px; font-weight: 700; color: var(--green-text)">Đã {texts.past} xong · làm lại để ôn</p>
      {:else}
        <p class="muted" style="font-size: 14px">Chưa {texts.past}</p>
      {/if}

      {#if countOf(current) === 0}
        <p class="muted" style="font-size: 14px">Phần này của bài chưa soạn xong.</p>
      {:else}
        <a href={startHref} class="btn btn-son btn-lg" style="width: 100%">
          {currentProg && currentProg.position > 0 ? texts.contStart : texts.nextStart}
        </a>
      {/if}
    {/if}
  </section>
{/snippet}

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Không tải được danh sách bài.">
      {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
    </EmptyState>
  {:else if data === null}
    <Skeleton h="80px" r={16} />
    <Skeleton h="320px" r={16} />
  {:else if lessons.length === 0}
    <EmptyState title="Cấp này chưa có bài nào." desc="Admin đang soạn, bạn quay lại sau nhé." />
  {:else}
    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap">
      <span class="ib lv{levelId}" style="width: 52px; height: 52px; border-radius: 14px"><span class="bignum" style="font-size: 26px">{levelId}</span></span>
      <div style="flex: 1; min-width: 160px">
        <h1 style="font-size: {m ? 20 : 26}px">{info.name} · HSK {levelId}</h1>
        <p class="muted" style="font-size: 13.5px">{doneCount} / {lessons.length} bài đã {texts.past}</p>
      </div>
      <span style="flex: 1 1 180px"><Bar value={doneCount} total={lessons.length} /></span>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: {m ? 16 : 24}px; align-items: flex-start">
      <section class="les-grid" style="flex: 999 1 {m ? 260 : 420}px; min-width: 0" aria-label="Các bài">
        {#each lessons as l (l.id)}{@render lessonCard(l)}{/each}

        {#if reviewCount > 0 || part === 'kt'}
          <button type="button" class="les les-all" class:on={picked === 'all'} onclick={() => (picked = 'all')}>
            <span class="ib" style="width: 38px; height: 38px; border-radius: 10px; background: var(--tim)"><Icon name="cards" size={18} /></span>
            <span style="flex: 1; min-width: 0">
              <b style="display: block">{texts.allTitle(levelId)}</b>
              <span class="muted" style="font-size: 13px">{texts.allDesc(levelId, reviewCount, lessons.length)}</span>
            </span>
          </button>
        {/if}
      </section>
      <aside style="flex: 1 1 320px; min-width: 0">{@render panel(m)}</aside>
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/{info.slug}" class="btn btn-bare" aria-label="Về chọn cấp"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">{info.name}</b>
      <Switcher current={part} mobile />
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">{@render body(true)}</main>
  </div>
{:else}
  <Topbar current={part} />
  <main class="page" style="display: flex; flex-direction: column; gap: 22px">
    <nav class="crumb"><a href="/learn/{info.slug}">{info.name}</a> / <b>HSK {levelId}</b></nav>
    {@render body(false)}
  </main>
{/if}

<style>
  .les-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(240px, 100%), 1fr));
    gap: 10px;
    align-content: start;
  }
</style>
