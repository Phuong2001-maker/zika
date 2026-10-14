<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import { SvelteSet } from 'svelte/reactivity';
  import { api, ApiError } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
  import QuestionView from '$lib/learn/QuestionView.svelte';
  import { lessonByNo } from '$lib/learn/lesson';
  import { answered, TYPE_LABEL, type Answer, type Payload } from '$lib/utils/grade';
  import { clock, num } from '$lib/utils/format';

  // Kiểm tra · Làm bài (07 §7). Không báo đúng/sai khi đang làm; server chấm khi nộp.
  interface TQ {
    id: number;
    no: number;
    type: string;
    score: number;
    payload: Payload;
  }
  interface Attempt {
    attemptId: number;
    serverNow: string;
    deadlineAt: string;
    softDeadlineAt: string;
    durationSec: number;
    listenLimit: number;
    maxScore: number;
    passScore: number;
    autoSubmit: boolean;
    questions: TQ[];
    answers: Record<string, Answer>;
    flags: number[];
    listenCounts: Record<string, number>;
  }
  interface Result {
    score: number;
    maxScore: number;
    percent: number;
    passed: boolean;
    usedSec: number;
    autoSubmitted: boolean;
    results: { qid: number; no: number; promptText: string; yourAnswerText: string; rightAnswerText: string; explain: string | null }[];
  }

  let {
    levelId,
    lessonNo = null,
    mockTestId = null,
    backHref,
    heading,
  }: {
    levelId: number;
    /** Bài kiểm tra của một bài học. */
    lessonNo?: number | null;
    /** Hoặc đề thi thử cuối cấp. */
    mockTestId?: number | null;
    backHref: string;
    heading: string;
  } = $props();

  let lessonTitle = $state('');
  let at = $state<Attempt | null>(null);
  let result = $state<Result | null>(null);
  let failed = $state(false);
  let failMsg = $state('');

  let cur = $state(0);
  let answers = $state<Record<number, Answer>>({});
  const flags = new SvelteSet<number>();
  let listens = $state<Record<number, number>>({});
  let left = $state(0);
  let confirmOpen = $state(false);
  let submitting = $state(false);

  let timer: ReturnType<typeof setInterval> | null = null;
  let saveTimer: ReturnType<typeof setTimeout> | null = null;
  /** Lệch giờ giữa máy người học và server (ms). */
  let skew = 0;
  let beeped = false;

  const q = $derived<TQ | null>(at?.questions[cur] ?? null);
  const total = $derived(at?.questions.length ?? 0);
  const answeredCount = $derived(
    (at?.questions ?? []).filter((x) => answered(x.type, x.payload, answers[x.id] ?? null)).length,
  );

  async function load(): Promise<void> {
    failed = false;
    result = null;
    at = null;
    try {
      let body: Record<string, number> = {};
      if (mockTestId !== null) {
        heartbeat.setActivity({ part: 'kt', screen: 'review', level: levelId, mockId: mockTestId });
        body = { mockTestId };
      } else {
        const l = await lessonByNo(levelId, lessonNo ?? 0);
        if (!l) {
          failed = true;
          return;
        }
        lessonTitle = l.title;
        heartbeat.setActivity({ part: 'kt', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
        body = { lessonId: l.id };
      }

      const data = await api.post<Attempt & { resultAttemptId?: number }>('/tests/start', body);

      // Lượt trước bị tự chấm mà chưa xem kết quả → mở thẳng kết quả.
      if (data.resultAttemptId) {
        result = await api.get<Result>(`/tests/attempts/${data.resultAttemptId}`);
        return;
      }

      at = data;
      answers = Object.fromEntries(Object.entries(data.answers ?? {}).map(([k, v]) => [Number(k), v]));
      flags.clear();
      for (const id of data.flags ?? []) flags.add(id);
      listens = Object.fromEntries(Object.entries(data.listenCounts ?? {}).map(([k, v]) => [Number(k), Number(v)]));
      cur = 0;
      skew = new Date(data.serverNow).getTime() - Date.now();
      startTimer();
    } catch (e) {
      failed = true;
      failMsg = e instanceof ApiError ? e.message : 'Không mở được bài kiểm tra.';
    }
  }

  function startTimer(): void {
    stopTimer();
    tick();
    timer = setInterval(tick, 1000);
  }
  function stopTimer(): void {
    if (timer) clearInterval(timer);
    timer = null;
  }

  function tick(): void {
    if (!at) return;
    const deadline = new Date(at.autoSubmit ? at.deadlineAt : at.softDeadlineAt).getTime();
    left = Math.max(0, Math.round((deadline - (Date.now() + skew)) / 1000));

    if (left === 60 && settings.value.testBeep && !beeped) {
      beeped = true;
      toast.show('Còn 1 phút!');
    }
    if (left === 0 && at.autoSubmit) {
      stopTimer();
      void submit(true);
    }
  }

  /** Tự lưu câu trả lời, gộp 1 giây một lần. */
  function queueSave(): void {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      if (!at) return;
      void api
        .patch(`/tests/attempts/${at.attemptId}`, {
          answers: Object.fromEntries(Object.entries(answers).map(([k, v]) => [k, v])),
          flags: [...flags],
          listenCounts: listens,
        })
        .catch(() => {});
    }, 1000);
  }

  function setAnswer(v: Answer): void {
    if (!q) return;
    answers = { ...answers, [q.id]: v };
    queueSave();
  }

  function toggleFlag(): void {
    if (!q) return;
    if (flags.has(q.id)) flags.delete(q.id);
    else flags.add(q.id);
    queueSave();
  }

  function countListen(): void {
    if (!q) return;
    listens = { ...listens, [q.id]: (listens[q.id] ?? 0) + 1 };
    queueSave();
  }

  async function submit(auto = false): Promise<void> {
    if (!at || submitting) return;
    submitting = true;
    confirmOpen = false;
    stopTimer();
    try {
      result = await api.post<Result>(`/tests/attempts/${at.attemptId}/submit`, {
        answers: Object.fromEntries(Object.entries(answers).map(([k, v]) => [k, v])),
        flags: [...flags],
      });
      if (auto) toast.show('Hết giờ — bài đã được nộp.');
    } catch (e) {
      if (e instanceof ApiError && (e.extra as { attemptId?: number })?.attemptId) {
        result = await api.get<Result>(`/tests/attempts/${(e.extra as { attemptId: number }).attemptId}`);
      } else {
        toast.show('Không nộp được bài, thử lại nhé.');
        startTimer();
      }
    } finally {
      submitting = false;
    }
  }

  onMount(load);
  onDestroy(() => {
    stopTimer();
    if (saveTimer) clearTimeout(saveTimer);
  });

  const lowTime = $derived(left > 0 && left < 60);
  const qnClass = (x: TQ, k: number): string => {
    let c = 'qn';
    if (k === cur) c += ' cur';
    else if (answered(x.type, x.payload, answers[x.id] ?? null)) c += ' ans';
    return c;
  };
</script>



{#snippet resultView(m: boolean)}
  {#if result}
    <section class="block" style="padding: {m ? 22 : 32}px; display: flex; flex-direction: column; align-items: center; gap: 14px; text-align: center">
      <span class="ib" style="width: 74px; height: 74px; border-radius: 50%; background: {result.passed ? 'var(--ngoc-soft)' : 'var(--son-soft)'}">
        <Icon name={result.passed ? 'check' : 'x'} size={34} />
      </span>
      <h1 style="font-size: {m ? 26 : 32}px">{num(result.score)} / {num(result.maxScore)} điểm</h1>
      <p class="muted">{result.percent}% · {result.passed ? 'Đạt' : 'Chưa đạt'}{result.autoSubmitted ? ' · hết giờ tự nộp' : ''}</p>
      <p class="muted" style="font-size: 14px">Làm trong {clock(result.usedSec * 1000)}</p>

      {#if result.results.length > 0}
        <div style="width: 100%; display: flex; flex-direction: column; gap: 10px; text-align: left; margin-top: 8px">
          <h2 class="h2">Các câu làm sai</h2>
          {#each result.results as r (r.qid)}
            <div class="paper" style="padding: 12px 14px; display: flex; flex-direction: column; gap: 4px">
              <b style="font-size: 14px">Câu {r.no} · <span class="hz" lang="zh">{r.promptText}</span></b>
              <span style="font-size: 13.5px; color: var(--bad-text)">Bạn chọn: {r.yourAnswerText}</span>
              <span style="font-size: 13.5px; color: var(--green-text)">Đáp án: {r.rightAnswerText}</span>
              {#if r.explain}<span class="muted" style="font-size: 13px">{r.explain}</span>{/if}
            </div>
          {/each}
        </div>
      {/if}

      <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 8px">
        <button type="button" class="btn" onclick={load}><Icon name="repeat" size={16} /> Làm lại</button>
        <a href={backHref} class="btn btn-son">Quay lại</a>
      </div>
    </section>
  {/if}
{/snippet}

{#snippet doing(m: boolean)}
  {#if at && q}
    <!-- Đồng hồ -->
    <div class="clockbar" style="background: {lowTime ? 'var(--son-soft)' : 'var(--surface-2)'}">
      <Icon name="clock" size={18} />
      <b class="num" style="font-size: 20px; color: {lowTime ? 'var(--bad-text)' : 'var(--text)'}">{clock(left * 1000)}</b>
      <span class="muted" style="font-size: 13px">{lowTime ? 'Sắp hết giờ!' : 'còn lại'}</span>
      <span style="flex: 1"></span>
      <span class="num" style="font-size: 13.5px">Đã làm {answeredCount}/{total}</span>
    </div>

    <div style="display: flex; align-items: center; gap: 12px">
      <span class="tag" style="background: var(--son-soft); color: var(--son-text)"><Icon name="quiz" size={14} /> {TYPE_LABEL[q.type] ?? q.type}</span>
      <span style="flex: 1"></span>
      <span class="num" style="font-weight: 700">Câu {cur + 1} / {total}</span>
    </div>

    <section class="block" style="padding: {m ? 18 : 26}px">
      <QuestionView
        type={q.type}
        payload={q.payload}
        bind:answer={() => answers[q.id] ?? null, setAnswer}
        listenLeft={q.type === 'listen' ? Math.max(0, at.listenLimit - (listens[q.id] ?? 0)) : null}
        onlisten={countListen}
        mobile={m}
      />
    </section>

    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center">
      <button type="button" class="btn" disabled={cur === 0} onclick={() => cur--}><Icon name="left" size={16} /> Câu trước</button>
      <button type="button" class="btn" class:apps-on={flags.has(q.id)} onclick={toggleFlag}>
        <Icon name="flag" size={16} /> {flags.has(q.id) ? 'Bỏ đánh dấu' : 'Đánh dấu xem lại'}
      </button>
      <span style="flex: 1"></span>
      {#if cur + 1 < total}
        <button type="button" class="btn btn-son" onclick={() => cur++}>Câu tiếp <Icon name="right" size={16} /></button>
      {:else}
        <button type="button" class="btn btn-son" onclick={() => (confirmOpen = true)}>Nộp bài</button>
      {/if}
    </div>

    <div class="grid">
      {#each at.questions as x, k (x.id)}
        <button type="button" class={qnClass(x, k)} onclick={() => (cur = k)} aria-label="Câu {k + 1}">
          {k + 1}{#if flags.has(x.id)}<i class="fl"></i>{/if}
        </button>
      {/each}
    </div>

    <button type="button" class="btn btn-son btn-lg" style="align-self: center" onclick={() => (confirmOpen = true)}>Nộp bài</button>
  {/if}
{/snippet}

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Không mở được bài kiểm tra." desc={failMsg}>
      {#snippet action()}<a href={backHref} class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if result}
    {@render resultView(m)}
  {:else if at === null}
    <Skeleton h="360px" r={20} />
  {:else}
    {@render doing(m)}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href={backHref} class="btn btn-bare" aria-label="Thoát"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">{lessonTitle || heading}</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href={backHref}>Kiểm tra · HSK {levelId}</a> / <b>{heading}</b></nav>
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 860px; display: flex; flex-direction: column; gap: 16px">
    <BackLink href={backHref} label="Chọn bài" />
    {@render body(false)}</main>
{/if}

{#if confirmOpen}
  <Modal title="Nộp bài?" onclose={() => (confirmOpen = false)}>
    <p style="font-size: 14px">
      Bạn đã làm {answeredCount}/{total} câu{answeredCount < total ? ` — còn ${total - answeredCount} câu chưa trả lời` : ''}.
      Nộp rồi thì không sửa được nữa.
    </p>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (confirmOpen = false)}>Làm tiếp</button>
      <button type="button" class="btn btn-son btn-sm" disabled={submitting} onclick={() => submit(false)}>Nộp bài</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .clockbar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border: 1px solid var(--line);
    border-radius: var(--r);
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(42px, 1fr));
    gap: 6px;
  }
</style>
