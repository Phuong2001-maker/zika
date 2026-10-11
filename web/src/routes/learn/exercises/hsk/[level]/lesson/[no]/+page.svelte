<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import QuestionView from '$lib/learn/QuestionView.svelte';
  import { lessonByNo } from '$lib/learn/lesson';
  import { answered, check, rightAnswerText, TYPE_LABEL, type Answer, type Payload } from '$lib/utils/grade';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Bài tập · Làm bài (07 §6). Chấm ngay tại máy, server chấm lại khi lưu.
  interface Q {
    id: number;
    type: string;
    payload: Payload;
    explain: string | null;
  }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let lesson = $state<LessonRef | null>(null);
  let next = $state<NextLesson | null>(null);
  let questions = $state<Q[]>([]);
  let order = $state<number[]>([]);
  let attemptId = $state<number | null>(null);
  let failed = $state(false);

  let i = $state(0);
  let answer = $state<Answer>(null);
  let checked = $state(false);
  let results = $state<boolean[]>([]);
  let finished = $state(false);
  let busy = $state(false);

  const q = $derived<Q | null>(questions[order[i]] ?? null);
  const total = $derived(order.length);
  const score = $derived(results.filter(Boolean).length);
  const isRight = $derived(checked && q ? check(q.type, q.payload, answer) : false);
  const canCheck = $derived(!checked && q !== null && answered(q.type, q.payload, answer));

  async function load(): Promise<void> {
    failed = false;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) {
        failed = true;
        return;
      }
      const data = await api.get<{ lesson: LessonRef; questions: Q[]; next: NextLesson | null }>(`/learn/lessons/${l.id}/exercises`);
      lesson = data.lesson;
      next = data.next;
      questions = data.questions;

      // Server quyết thứ tự câu của lượt làm bài.
      const at = await api.post<{ attemptId: number; questionIds: number[] }>('/exercises/attempts', { lessonId: l.id, mode: 'full' });
      attemptId = at.attemptId;
      order = at.questionIds.map((id) => questions.findIndex((x) => x.id === id)).filter((k) => k >= 0);

      i = 0;
      answer = null;
      checked = false;
      results = [];
      finished = questions.length === 0;
      heartbeat.setActivity({ part: 'bt', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => {
    void levelId;
    void lessonNo;
    void load();
  });

  async function doCheck(): Promise<void> {
    if (!q || !canCheck) return;
    checked = true;
    // Gửi lên server; server chấm lại và lưu kết quả của chính nó.
    if (attemptId !== null) {
      void api.patch(`/exercises/attempts/${attemptId}`, { result: { qid: q.id, answer, correct: isRight } }).catch(() => {});
    }
  }

  async function doNext(): Promise<void> {
    results = [...results, isRight];
    answer = null;
    checked = false;

    if (i + 1 >= total) {
      finished = true;
      busy = true;
      try {
        if (attemptId !== null) await api.post(`/exercises/attempts/${attemptId}/finish`);
      } catch {
        toast.show('Làm xong rồi nhưng chưa lưu được điểm.');
      } finally {
        busy = false;
      }
      return;
    }
    i++;
  }

  async function restart(): Promise<void> {
    await load();
  }

  const gridCls = (k: number): string => {
    if (k < results.length) return results[k] ? 'qn ok' : 'qn no';
    if (k === i && !finished) return 'qn cur';
    return 'qn';
  };
</script>

<svelte:head><title>{lesson ? `${lesson.title} · Bài tập` : 'Bài tập'} · Zìkǎ</title></svelte:head>

{#snippet feedback()}
  {#if checked && q}
    <div class="fb" style="background: {isRight ? 'var(--ngoc-soft)' : 'var(--son-soft)'}; border-color: {isRight ? '#9FE0B5' : 'var(--son-line)'}">
      <span class="ok-dot" style="background: {isRight ? 'var(--ngoc)' : 'var(--bad)'}">
        <Icon name={isRight ? 'check' : 'x'} size={14} />
      </span>
      <span style="flex: 1; min-width: 0">
        <b style="display: block">{isRight ? 'Chính xác!' : `Chưa đúng. Đáp án: ${rightAnswerText(q.type, q.payload)}`}</b>
        {#if q.explain}<span style="font-size: 14px">{q.explain}</span>{/if}
      </span>
    </div>
  {/if}
{/snippet}

{#snippet done(m: boolean)}
  <section class="block" style="padding: {m ? 22 : 32}px; display: flex; flex-direction: column; align-items: center; gap: 16px; text-align: center">
    <span class="stamp" style="font-size: {m ? 26 : 34}px">完成</span>
    <h1 style="font-size: {m ? 24 : 30}px">{score} / {total} câu đúng</h1>
    <p class="muted">Bài tập của “{lesson?.title}”.</p>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center">
      <button type="button" class="btn" disabled={busy} onclick={restart}><Icon name="repeat" size={16} /> Làm lại</button>
      {#if next}
        <a href="/learn/exercises/hsk/{levelId}/lesson/{next.no}" class="btn btn-son">Bài tiếp: {next.title}</a>
      {:else}
        <a href="/learn/exercises/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
      {/if}
    </div>
  </section>
{/snippet}

{#snippet main(m: boolean)}
  {#if failed}
    <EmptyState title="Không mở được bài tập này.">
      {#snippet action()}<a href="/learn/exercises/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if lesson === null}
    <Skeleton h="360px" r={20} />
  {:else if finished}
    {@render done(m)}
  {:else if q}
    <div style="display: flex; align-items: center; gap: 14px">
      <span class="tag" style="background: var(--ngoc-soft); color: var(--green-text)"><Icon name="exercise" size={14} /> {TYPE_LABEL[q.type] ?? q.type}</span>
      <span style="flex: 1"></span>
      <span class="num" style="font-weight: 700">Câu {i + 1} / {total}</span>
    </div>
    <Bar value={results.length} {total} />

    <section class="block" style="padding: {m ? 18 : 26}px; display: flex; flex-direction: column; gap: 16px">
      <QuestionView
        type={q.type}
        payload={q.payload}
        bind:answer
        locked={checked}
        rightIndex={checked && ['meaning', 'pinyin', 'tone', 'audio', 'listen', 'blank', 'fill', 'pick', 'toZh'].includes(q.type)
          ? (q.payload.answer as number)
          : null}
        mobile={m}
      />
      {@render feedback()}
    </section>

    <div style="display: flex; gap: 10px; justify-content: flex-end">
      {#if !checked}
        <button type="button" class="btn btn-son btn-lg" disabled={!canCheck} onclick={doCheck}>Kiểm tra</button>
      {:else}
        <button type="button" class="btn btn-son btn-lg" onclick={doNext}>{i + 1 >= total ? 'Xem kết quả' : 'Câu tiếp'}</button>
      {/if}
    </div>

    {#if !m}
      <div class="grid">
        {#each Array.from({ length: total }) as _, k (k)}<span class={gridCls(k)}>{k + 1}</span>{/each}
      </div>
    {/if}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/exercises/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">{lesson?.title ?? 'Bài tập'}</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render main(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px">
      <a href="/learn/exercises/hsk/{levelId}">Bài tập · HSK {levelId}</a> / <b>Bài {lessonNo}</b>
    </nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="bt" /><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 820px; display: flex; flex-direction: column; gap: 16px">{@render main(false)}</main>
{/if}

<style>
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(40px, 1fr));
    gap: 6px;
  }
</style>
