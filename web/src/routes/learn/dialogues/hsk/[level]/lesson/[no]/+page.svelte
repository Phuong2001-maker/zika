<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { completePart, lessonByNo, saveProgress } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Đọc hội thoại (07 §10).
  interface Speaker { id: number; name: string; avatarChar: string; color: string; roleVi: string }
  interface Line { id: number; speakerId: number; zh: string; highlight: string | null; pinyin: string; vi: string; audioUrl: string | null }
  interface Quiz { prompt: string; options: string[]; answer: number }
  interface Data {
    lesson: LessonRef;
    dialogue: { title: string; scene: string; speakers: Speaker[]; lines: Line[]; questions: Quiz[] } | null;
    newWords: { hanzi: string; meaning: string }[];
    next: NextLesson | null;
  }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<Data | null>(null);
  let failed = $state(false);
  let now = $state(0);          // câu đang đọc
  let role = $state<number | null>(null);
  let showPy = $state(true);
  let showVi = $state(true);
  let quizPick = $state<Record<number, number>>({});
  let done = $state(false);

  const d = $derived(data?.dialogue ?? null);
  const lines = $derived(d?.lines ?? []);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) { failed = true; return; }
      const res = await api.get<Data>(`/learn/lessons/${l.id}/dialogue`);
      data = res;
      now = 0;
      quizPick = {};
      done = false;
      role = res.dialogue?.speakers[1]?.id ?? res.dialogue?.speakers[0]?.id ?? null;
      showPy = settings.value.dialogue.showPinyin;
      showVi = settings.value.dialogue.showVi;
      heartbeat.setActivity({ part: 'ht', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => { void levelId; void lessonNo; void load(); });

  function playLine(k: number): void {
    now = k;
    const line = lines[k];
    if (line) void speak(line.zh, line.audioUrl);
    if (data) void saveProgress(data.lesson.id, 'ht', k + 1, lines.length).catch(() => {});
  }

  async function playAll(): Promise<void> {
    for (let k = 0; k < lines.length; k++) {
      now = k;
      await speak(lines[k].zh, lines[k].audioUrl);
      await new Promise((r) => setTimeout(r, 900));
    }
  }

  async function finish(): Promise<void> {
    if (!data) return;
    try {
      await completePart(data.lesson.id, 'ht');
      done = true;
      toast.ok('Đã xong bài hội thoại.');
    } catch {
      toast.show('Chưa lưu được tiến độ.');
    }
  }

  const speakerOf = (id: number): Speaker | undefined => d?.speakers.find((s) => s.id === id);

  /** Tô sáng từ mới trong câu. */
  function parts(line: Line): [string, string, string] {
    if (!line.highlight || !line.zh.includes(line.highlight)) return [line.zh, '', ''];
    const i = line.zh.indexOf(line.highlight);
    return [line.zh.slice(0, i), line.highlight, line.zh.slice(i + line.highlight.length)];
  }
</script>

<svelte:head><title>{d ? `${d.title} · Hội thoại` : 'Hội thoại'} · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Bài này chưa có hội thoại.">
      {#snippet action()}<a href="/learn/dialogues/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if data === null}
    <Skeleton h="360px" r={20} />
  {:else if d === null}
    <EmptyState title="Bài này chưa có hội thoại." />
  {:else}
    <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap">
      <div>
        <h1 style="font-size: {m ? 20 : 26}px">{d.title}</h1>
        {#if d.scene}<p class="muted" style="font-size: 14px">{d.scene}</p>{/if}
      </div>
      <div style="display: flex; gap: 8px; flex-wrap: wrap">
        <button type="button" class="btn btn-sm" class:apps-on={showPy} onclick={() => (showPy = !showPy)}>Pinyin</button>
        <button type="button" class="btn btn-sm" class:apps-on={showVi} onclick={() => (showVi = !showVi)}>Nghĩa</button>
        <button type="button" class="btn btn-sm" onclick={playAll}><Icon name="play" size={15} /> Nghe cả bài</button>
      </div>
    </div>

    <!-- Chọn vai -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
      <span class="muted" style="font-size: 13.5px">Bạn đóng vai:</span>
      {#each d.speakers as s (s.id)}
        <label class="pick">
          <input type="radio" name="role" checked={role === s.id} onchange={() => (role = s.id)} />
          <span><span class="hz" lang="zh">{s.name}</span>{s.roleVi ? ` · ${s.roleVi}` : ''}</span>
        </label>
      {/each}
    </div>

    <!-- Lời thoại -->
    <ul style="display: flex; flex-direction: column; gap: 12px">
      {#each lines as line, k (line.id)}
        {@const sp = speakerOf(line.speakerId)}
        {@const mine = role === line.speakerId}
        {@const [pre, hl, post] = parts(line)}
        <li style="display: flex; gap: 10px; flex-direction: {mine ? 'row-reverse' : 'row'}">
          <span class="who hz" lang="zh" style="background: {sp?.color ?? 'var(--surface)'}">{sp?.avatarChar ?? '?'}</span>
          <button
            type="button"
            class="bub"
            class:me={mine}
            class:now={now === k}
            style="flex: 1 1 auto; max-width: 80%; text-align: {mine ? 'right' : 'left'}; cursor: pointer"
            onclick={() => playLine(k)}
          >
            <span class="hz" lang="zh" style="font-size: {m ? 19 : 22}px">{pre}{#if hl}<b class="hl">{hl}</b>{/if}{post}</span>
            {#if showPy && line.pinyin}<span class="muted" style="font-size: 13.5px">{line.pinyin}</span>{/if}
            {#if showVi && line.vi}<span style="font-size: 14px">{line.vi}</span>{/if}
          </button>
        </li>
      {/each}
    </ul>

    <!-- Câu hỏi hiểu bài -->
    {#each d.questions as quiz, qi (qi)}
      <section class="block" style="padding: 18px 20px; display: flex; flex-direction: column; gap: 10px">
        <b class="hz" lang="zh" style="font-size: 18px">{quiz.prompt}</b>
        {#each quiz.options as o, oi (oi)}
          <button
            type="button"
            class="opt"
            class:right={quizPick[qi] !== undefined && oi === quiz.answer}
            class:wrong={quizPick[qi] === oi && oi !== quiz.answer}
            disabled={quizPick[qi] !== undefined}
            onclick={() => (quizPick = { ...quizPick, [qi]: oi })}
          >
            <span class="k">{String.fromCharCode(65 + oi)}</span>
            <span class="hz" lang="zh">{o}</span>
          </button>
        {/each}
      </section>
    {/each}

    <!-- Từ mới -->
    {#if data.newWords.length > 0}
      <section class="paper" style="padding: 16px 18px; display: flex; flex-direction: column; gap: 8px">
        <p class="kicker">Từ mới trong bài</p>
        <div style="display: flex; flex-wrap: wrap; gap: 8px">
          {#each data.newWords as w (w.hanzi)}
            <button type="button" class="tag" style="height: auto; padding: 6px 10px" onclick={() => speak(w.hanzi)}>
              <span class="hz" lang="zh" style="font-size: 15px">{w.hanzi}</span>
              <span class="muted">{w.meaning}</span>
            </button>
          {/each}
        </div>
      </section>
    {/if}

    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end">
      {#if done}
        {#if data.next}
          <a href="/learn/dialogues/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son btn-lg">Bài tiếp: {data.next.title}</a>
        {:else}
          <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-son btn-lg">Về danh sách bài</a>
        {/if}
      {:else}
        <button type="button" class="btn btn-son btn-lg" onclick={finish}><Icon name="check" size={18} /> Đã đọc xong</button>
      {/if}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Hội thoại</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/dialogues/hsk/{levelId}">Hội thoại · HSK {levelId}</a> / <b>Bài {lessonNo}</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="ht" /><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 820px; display: flex; flex-direction: column; gap: 16px">{@render body(false)}</main>
{/if}
