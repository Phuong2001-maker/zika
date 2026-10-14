<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
  import { completePart, lessonByNo, saveProgress } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Luyện viết từ mới (07 §12).
  interface Char {
    id: number;
    hanzi: string;
    pinyin: string;
    tone: number;
    hanViet: string;
    meaning: string;
    strokes: number;
    structure: string;
    strokeDataUrl: string | null;
    word: { hanzi: string; meaning: string } | null;
  }
  interface Config { startMode: 'demo' | 'trace' | 'free'; grid: 'tian' | 'mi'; passCount: number; showAnimation: boolean }
  interface Data { lesson: LessonRef; config: Config; chars: Char[]; next: NextLesson | null }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<Data | null>(null);
  let failed = $state(false);
  let ci = $state(0);
  let mode = $state<'demo' | 'trace' | 'free'>('trace');
  let shown = $state(0);                       // số nét đang hiện
  let strokes = $state<string[]>([]);          // path SVG của chữ hiện tại
  let loadingStrokes = $state(false);
  let passes = $state(0);
  let done = $state(false);

  const chars = $derived(data?.chars ?? []);
  const c = $derived<Char | null>(chars[ci] ?? null);
  const need = $derived(data?.config.passCount ?? 2);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) { failed = true; return; }
      const res = await api.get<Data>(`/learn/lessons/${l.id}/writing`);
      data = res;
      mode = res.config.startMode;
      ci = 0;
      done = false;
      await pickChar(0);
      heartbeat.setActivity({ part: 'lv', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => { void levelId; void lessonNo; void load(); });

  /** Tải dữ liệu nét của một chữ (định dạng hanzi-writer: toạ độ 1024, trục y lật). */
  async function pickChar(k: number): Promise<void> {
    ci = k;
    passes = 0;
    shown = 0;
    strokes = [];
    const ch = chars[k];
    if (!ch?.strokeDataUrl) return;

    loadingStrokes = true;
    try {
      const res = await fetch(ch.strokeDataUrl);
      const json = (await res.json()) as { strokes?: string[] };
      strokes = json.strokes ?? [];
      if (mode === 'demo' && data?.config.showAnimation) void animate();
    } catch {
      strokes = [];
    } finally {
      loadingStrokes = false;
    }

    if (data) void saveProgress(data.lesson.id, 'lv', k, chars.length).catch(() => {});
  }

  async function animate(): Promise<void> {
    shown = 0;
    for (let k = 1; k <= strokes.length; k++) {
      shown = k;
      await new Promise((r) => setTimeout(r, 420));
    }
  }

  function nextStroke(): void {
    if (shown < strokes.length) shown++;
  }

  /** Ghi nhận một lần viết đạt. */
  async function markPass(): Promise<void> {
    if (!c || !data) return;
    passes++;
    try {
      const res = await api.post<{ passes: number; charDone: boolean }>('/writing/result', {
        charId: c.id,
        lessonId: data.lesson.id,
        mode: mode === 'free' ? 'free' : 'trace',
        mistakes: 0,
        passed: true,
      });
      if (res.charDone || passes >= need) {
        if (ci + 1 < chars.length) {
          toast.ok(`Xong chữ ${c.hanzi}!`);
          await pickChar(ci + 1);
        } else {
          await finish();
        }
      } else {
        shown = 0;
      }
    } catch {
      toast.show('Chưa lưu được kết quả.');
    }
  }

  async function finish(): Promise<void> {
    if (!data) return;
    try {
      await completePart(data.lesson.id, 'lv');
      done = true;
      toast.ok('Đã viết xong cả bài.');
    } catch {
      toast.show('Chưa lưu được tiến độ.');
    }
  }

  function setMode(m: 'demo' | 'trace' | 'free'): void {
    mode = m;
    shown = m === 'free' ? 0 : shown;
    if (m === 'demo' && data?.config.showAnimation) void animate();
  }

  const hint = $derived(
    mode === 'demo'
      ? 'Xem thứ tự nét, rồi chuyển sang “Tô theo”.'
      : mode === 'trace'
        ? 'Bấm “Nét tiếp” để hiện từng nét và viết theo.'
        : 'Tự viết ra giấy rồi bấm “Hiện nét” để đối chiếu.',
  );
</script>

<svelte:head><title>Luyện viết · Zìkǎ</title></svelte:head>

{#snippet grid(size: number)}
  <div class="tian" class:mi={data?.config.grid === 'mi'} style="width: {size}px; height: {size}px">
    {#if strokes.length > 0}
      <svg viewBox="0 0 1024 1024" width={size} height={size} aria-label="Chữ {c?.hanzi}">
        <g transform="translate(0, 900) scale(1, -1)">
          <!-- Nét mờ của cả chữ, trừ chế độ Tự viết -->
          {#if mode !== 'free'}
            {#each strokes as d, k (k)}
              <path {d} fill="#E6DEF8" />
            {/each}
          {/if}
          <!-- Các nét đã hiện -->
          {#each strokes.slice(0, shown) as d, k (k)}
            <path {d} fill={k === shown - 1 ? 'var(--son)' : 'var(--text)'} />
          {/each}
        </g>
      </svg>
    {:else if loadingStrokes}
      <span class="muted" style="font-size: 13px">Đang tải nét…</span>
    {:else}
      <!-- Không có dữ liệu nét: hiện thẳng chữ để vẫn tập viết được -->
      <span class="hz" lang="zh" style="font-size: {size * 0.62}px; color: {mode === 'free' ? '#E6DEF8' : 'var(--text)'}">{c?.hanzi}</span>
    {/if}
  </div>
{/snippet}

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Bài này chưa có chữ để luyện viết.">
      {#snippet action()}<a href="/learn/writing/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if data === null}
    <Skeleton h="380px" r={20} />
  {:else if c}
    <!-- Danh sách chữ -->
    <div style="display: flex; gap: 6px; flex-wrap: wrap">
      {#each chars as ch, k (ch.id)}
        <button
          type="button"
          class="chip hz"
          lang="zh"
          class:on={k === ci}
          class:passed={k < ci}
          onclick={() => pickChar(k)}
        >{ch.hanzi}</button>
      {/each}
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">
      <!-- Ô viết -->
      <div style="flex: 0 0 auto; display: flex; flex-direction: column; gap: 12px; align-items: center">
        {@render grid(m ? 240 : 300)}

        <div class="seg">
          <button type="button" class:on={mode === 'demo'} onclick={() => setMode('demo')}>Xem mẫu</button>
          <button type="button" class:on={mode === 'trace'} onclick={() => setMode('trace')}>Tô theo</button>
          <button type="button" class:on={mode === 'free'} onclick={() => setMode('free')}>Tự viết</button>
        </div>

        <p class="muted" style="font-size: 13px; text-align: center; max-width: 280px">{hint}</p>

        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center">
          <button type="button" class="btn btn-sm" onclick={() => (shown = 0)}><Icon name="repeat" size={15} /> Xoá</button>
          <button type="button" class="btn btn-sm" disabled={shown >= strokes.length} onclick={nextStroke}>
            {mode === 'free' ? 'Hiện nét' : 'Nét tiếp'} ({shown}/{strokes.length || c.strokes})
          </button>
          {#if data.config.showAnimation}
            <button type="button" class="btn btn-sm" onclick={animate}><Icon name="play" size={15} /> Chạy lại</button>
          {/if}
        </div>

        <button type="button" class="btn btn-son btn-lg" onclick={markPass}>
          <Icon name="check" size={18} /> Viết đúng ({passes}/{need})
        </button>
      </div>

      <!-- Thông tin chữ -->
      <section class="block" style="flex: 1 1 280px; min-width: 0; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px">
        <div style="display: flex; align-items: center; gap: 10px">
          <span class="hz" lang="zh" style="font-size: 40px">{c.hanzi}</span>
          <div style="flex: 1">
            <b class="t{c.tone}" style="font-size: 20px">{c.pinyin}</b>
            {#if c.hanViet}<p class="muted" style="font-size: 12.5px; font-weight: 700; text-transform: uppercase">{c.hanViet}</p>{/if}
          </div>
          <button type="button" class="btn btn-bare" aria-label="Nghe" onclick={() => speak(c.hanzi)}><Icon name="speaker" size={18} /></button>
        </div>

        <p style="font-size: 15px">{c.meaning}</p>
        <hr class="rule" />
        <p class="muted" style="font-size: 13.5px"><b>{c.strokes || strokes.length} nét</b>{c.structure ? ` · ${c.structure}` : ''}</p>
        {#if c.word}
          <p style="font-size: 14px">Có trong từ: <b class="hz" lang="zh">{c.word.hanzi}</b> <span class="muted">{c.word.meaning}</span></p>
        {/if}
      </section>
    </div>

    <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap">
      {#if done}
        {#if data.next}
          <a href="/learn/writing/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son btn-lg">Bài tiếp: {data.next.title}</a>
        {:else}
          <a href="/learn/writing/hsk/{levelId}" class="btn btn-son btn-lg">Về danh sách bài</a>
        {/if}
      {:else if ci + 1 < chars.length}
        <button type="button" class="btn" onclick={() => pickChar(ci + 1)}>Bỏ qua chữ này <Icon name="right" size={16} /></button>
      {:else}
        <button type="button" class="btn" onclick={finish}>Đánh dấu xong bài</button>
      {/if}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/writing/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Luyện viết</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/writing/hsk/{levelId}">Luyện viết · HSK {levelId}</a> / <b>Bài {lessonNo}</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="lv" /><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 900px; display: flex; flex-direction: column; gap: 16px">
    <BackLink href="/learn/writing/hsk/{levelId}" label="Chọn bài" />
    {@render body(false)}</main>
{/if}

<style>
  .chip {
    min-width: 42px;
    height: 42px;
    padding: 0 8px;
    border: 1px solid var(--ink-strong);
    border-radius: var(--r-sm);
    background: var(--surface);
    font-size: 20px;
    cursor: pointer;
  }
  .chip.on {
    border-color: var(--son);
    background: var(--son-soft);
  }
  .chip.passed {
    background: var(--ngoc-soft);
    border-color: #cdebd6;
  }
  .tian.mi {
    background-image:
      linear-gradient(to right, transparent calc(50% - 0.5px), var(--son-line) calc(50% - 0.5px), var(--son-line) calc(50% + 0.5px), transparent calc(50% + 0.5px)),
      linear-gradient(to bottom, transparent calc(50% - 0.5px), var(--son-line) calc(50% - 0.5px), var(--son-line) calc(50% + 0.5px), transparent calc(50% + 0.5px)),
      linear-gradient(to bottom right, transparent calc(50% - 0.4px), var(--son-line) calc(50% - 0.4px), var(--son-line) calc(50% + 0.4px), transparent calc(50% + 0.4px)),
      linear-gradient(to bottom left, transparent calc(50% - 0.4px), var(--son-line) calc(50% - 0.4px), var(--son-line) calc(50% + 0.4px), transparent calc(50% + 0.4px));
  }
</style>
