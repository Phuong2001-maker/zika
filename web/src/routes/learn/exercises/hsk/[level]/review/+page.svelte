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
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import QuestionView from '$lib/learn/QuestionView.svelte';
  import { answered, check, rightAnswerText, TYPE_LABEL, type Answer, type Payload } from '$lib/utils/grade';

  // Bài tập tổng hợp cả cấp (04 §4.9 part = bt, §6 mode = review).
  interface Q { id: number; type: string; payload: Payload; explain: string | null; lessonNo?: number }

  const levelId = $derived(Number(page.params.level));

  let pool = $state<Q[]>([]);
  let order = $state<number[]>([]);
  let attemptId = $state<number | null>(null);
  let failed = $state(false);
  let loading = $state(true);

  let i = $state(0);
  let answer = $state<Answer>(null);
  let checked = $state(false);
  let results = $state<boolean[]>([]);
  let finished = $state(false);

  const q = $derived<Q | null>(pool[order[i]] ?? null);
  const total = $derived(order.length);
  const score = $derived(results.filter(Boolean).length);
  const isRight = $derived(checked && q ? check(q.type, q.payload, answer) : false);
  const canCheck = $derived(!checked && q !== null && answered(q.type, q.payload, answer));

  async function load(): Promise<void> {
    failed = false;
    loading = true;
    try {
      const data = await api.get<{ questions: Q[] }>(`/learn/levels/${levelId}/review/bt`);
      pool = data.questions;
      const at = await api.post<{ attemptId: number; questionIds: number[] }>('/exercises/attempts', { levelId, mode: 'review' });
      attemptId = at.attemptId;
      order = at.questionIds.map((id) => pool.findIndex((x) => x.id === id)).filter((k) => k >= 0);
      i = 0;
      answer = null;
      checked = false;
      results = [];
      finished = order.length === 0;
      heartbeat.setActivity({ part: 'bt', screen: 'review', level: levelId });
    } catch {
      failed = true;
    } finally {
      loading = false;
    }
  }
  onMount(load);

  function doCheck(): void {
    if (!q || !canCheck) return;
    checked = true;
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
      try {
        if (attemptId !== null) await api.post(`/exercises/attempts/${attemptId}/finish`);
      } catch {
        toast.show('Làm xong rồi nhưng chưa lưu được điểm.');
      }
      return;
    }
    i++;
  }
</script>

<svelte:head><title>Bài tập tổng hợp HSK {levelId} · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Cấp này chưa có câu bài tập nào.">
      {#snippet action()}<a href="/learn/exercises/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if loading}
    <Skeleton h="340px" r={20} />
  {:else if finished}
    <section class="block" style="padding: {m ? 22 : 32}px; display: flex; flex-direction: column; align-items: center; gap: 14px; text-align: center">
      <span class="stamp" style="font-size: {m ? 26 : 34}px">完成</span>
      <h1 style="font-size: {m ? 24 : 30}px">{score} / {total} câu đúng</h1>
      <p class="muted">Bài tập tổng hợp HSK {levelId}.</p>
      <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center">
        <button type="button" class="btn" onclick={load}><Icon name="repeat" size={16} /> Làm lại</button>
        <a href="/learn/exercises/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
      </div>
    </section>
  {:else if q}
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap">
      <span class="tag" style="background: var(--ngoc-soft); color: var(--green-text)">{TYPE_LABEL[q.type] ?? q.type}</span>
      {#if q.lessonNo}<span class="tag">Bài {q.lessonNo}</span>{/if}
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
        rightIndex={checked && typeof q.payload.answer === 'number' ? q.payload.answer : null}
        mobile={m}
      />
      {#if checked}
        <div class="fb" style="background: {isRight ? 'var(--ngoc-soft)' : 'var(--son-soft)'}; border-color: {isRight ? '#9FE0B5' : 'var(--son-line)'}">
          <span class="ok-dot" style="background: {isRight ? 'var(--ngoc)' : 'var(--bad)'}"><Icon name={isRight ? 'check' : 'x'} size={14} /></span>
          <span style="flex: 1">
            <b style="display: block">{isRight ? 'Chính xác!' : `Chưa đúng. Đáp án: ${rightAnswerText(q.type, q.payload)}`}</b>
            {#if q.explain}<span style="font-size: 14px">{q.explain}</span>{/if}
          </span>
        </div>
      {/if}
    </section>

    <div style="display: flex; justify-content: flex-end">
      {#if !checked}
        <button type="button" class="btn btn-son btn-lg" disabled={!canCheck} onclick={doCheck}>Kiểm tra</button>
      {:else}
        <button type="button" class="btn btn-son btn-lg" onclick={doNext}>{i + 1 >= total ? 'Xem kết quả' : 'Câu tiếp'}</button>
      {/if}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/exercises/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Bài tập tổng hợp</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/exercises/hsk/{levelId}">Bài tập · HSK {levelId}</a> / <b>Tổng hợp</b></nav>
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 820px; display: flex; flex-direction: column; gap: 16px">{@render body(false)}</main>
{/if}
