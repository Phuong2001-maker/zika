<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
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

<svelte:head><title>Bài tập tổng hợp {cap.ten(levelId)} · Zìkǎ</title></svelte:head>

<!-- Một khung chứa trọn màn làm bài, cùng khuôn với màn bài tập từng bài và màn Lật thẻ:
     loại câu + số câu ở đầu khung, nút Kiểm tra ở chân khung. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if finished}
      <div class="btr-xong" style="padding: {m ? 12 : 20}px 0">
        <span class="stamp" style="font-size: {m ? 26 : 34}px">完成</span>
        <h2 style="font-size: {m ? 24 : 30}px">{score} / {total} câu đúng</h2>
        <p class="muted">Bài tập tổng hợp {cap.ten(levelId)}.</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center">
          <button type="button" class="btn" onclick={load}><Icon name="repeat" size={16} /> Làm lại</button>
          <a href="/learn/exercises/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
        </div>
      </div>
    {:else if q}
      <div class="zk-khung-dau">
        <span class="btr-nhan">
          <span class="tag tag-ok">{TYPE_LABEL[q.type] ?? q.type}</span>
          {#if q.lessonNo}<span class="tag">Bài {q.lessonNo}</span>{/if}
        </span>
        <span class="zk-so">Câu {i + 1} / {total}</span>
      </div>
      <Bar value={results.length} {total} />

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
          <span style="flex: 1; min-width: 0">
            <b style="display: block">{isRight ? 'Chính xác!' : `Chưa đúng. Đáp án: ${rightAnswerText(q.type, q.payload)}`}</b>
            {#if q.explain}<span style="font-size: 14px">{q.explain}</span>{/if}
          </span>
        </div>
      {/if}

      <div class="zk-chan" class:btr-chan-m={m}>
        {#if !checked}
          <button type="button" class="btn btn-son btn-lg btr-nut" disabled={!canCheck} onclick={doCheck}>Kiểm tra</button>
        {:else}
          <button type="button" class="btn btn-son btn-lg btr-nut" onclick={doNext}>{i + 1 >= total ? 'Xem kết quả' : 'Câu tiếp'}</button>
        {/if}
      </div>
    {/if}
  </section>
{/snippet}

{#if failed}
  <main class="page">
    <EmptyState title="{cap.tuHoa} này chưa có câu bài tập nào.">
      {#snippet action()}<a href="/learn/exercises/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  </main>
{:else if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <MTopbar current="bt" back="/learn/exercises/hsk/{levelId}" backLabel="Về danh sách bài" title="Bài tập tổng hợp" sub="{cap.ten(levelId)} · Cả {cap.tu}" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">
      {#if loading}
        <Skeleton h="340px" r={20} />
      {:else}
        {@render khung(true)}
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="bt">
    <nav class="crumb"><a href="/learn/exercises/hsk/{levelId}">Bài tập · {cap.ten(levelId)}</a> / <b>Tổng hợp</b></nav>
  </Topbar>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    {#if loading}
      <Skeleton h="420px" r={22} />
    {:else}
      <DauBai href="/learn/exercises/hsk/{levelId}" kicker="{cap.ten(levelId)} · Tổng hợp cả {cap.tu} · {total} câu" title="Bài tập tổng hợp" />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  .btr-nhan {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
  }
  .btr-nut {
    min-width: 150px;
    margin-left: auto;
  }
  /* Điện thoại: nút chính trải hết bề ngang, cao hơn cho ngón cái. */
  .btr-chan-m .btr-nut {
    flex: 1 1 auto;
    min-height: 50px;
  }
  .btr-xong {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    text-align: center;
  }
</style>
