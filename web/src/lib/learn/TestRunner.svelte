<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
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
  import DauBai from '$lib/components/DauBai.svelte';
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
    /** Có giá trị khi lượt này thuộc một cuộc thi. */
    contestTitle: string | null;
    questions: TQ[];
    answers: Record<string, Answer>;
    listenCounts: Record<string, number>;
  }
  interface Result {
    score: number;
    maxScore: number;
    percent: number;
    passed: boolean;
    usedSec: number;
    autoSubmitted: boolean;
    contestTitle: string | null;
    correctCount: number;
    questionCount: number;
    answersHidden: boolean;
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
  let listens = $state<Record<number, number>>({});
  let left = $state(0);
  let confirmOpen = $state(false);
  let submitting = $state(false);

  let timer: ReturnType<typeof setInterval> | null = null;
  let saveTimer: ReturnType<typeof setTimeout> | null = null;
  let retryTimer: ReturnType<typeof setTimeout> | null = null;
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
      });
      if (auto) toast.show('Hết giờ — bài đã được nộp.');
    } catch (e) {
      if (e instanceof ApiError && (e.extra as { attemptId?: number })?.attemptId) {
        result = await api.get<Result>(`/tests/attempts/${(e.extra as { attemptId: number }).attemptId}`);
      } else if (auto) {
        // Hết giờ mà mất mạng: màn đã khoá nên cứ thử lại, người học không phải làm gì. Server vẫn giữ câu đã lưu.
        retryTimer = setTimeout(() => void submit(true), 5000);
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
    if (retryTimer) clearTimeout(retryTimer);
  });

  const lowTime = $derived(left > 0 && left < 60);
  const conLai = $derived(total - answeredCount);
  const contestTitle = $derived(at?.contestTitle ?? result?.contestTitle ?? null);
  const tenBai = $derived(contestTitle ?? (lessonTitle || heading));
  const quayLai = $derived(contestTitle ? '/learn/flashcards' : backHref);
  // Hết giờ là khoá màn làm bài cho tới khi nộp xong, không cho chọn thêm đáp án.
  const hetGio = $derived(at !== null && at.autoSubmit && left === 0 && result === null);
  // Đề thi thử không có số bài, nên dòng nhỏ trên đầu bài ghi theo loại bài kiểm tra.
  const kicker = $derived.by(() => {
    const loai = contestTitle ? 'Cuộc thi' : 'Thi thử cuối cấp';
    const dau = mockTestId !== null ? `HSK ${levelId} · ${loai}` : `HSK ${levelId} · Bài ${lessonNo} · Kiểm tra`;
    return at ? `${dau} · ${total} câu · ${Math.round(at.durationSec / 60)} phút` : dau;
  });
  const qnClass = (x: TQ, k: number): string => {
    let c = 'qn';
    if (k === cur) c += ' cur';
    else if (answered(x.type, x.payload, answers[x.id] ?? null)) c += ' ans';
    return c;
  };
</script>

<!-- Đồng hồ đếm ngược: máy tính đặt ở đầu bài (DauBai), điện thoại đặt ở hàng đầu khung. -->
{#snippet dongHo(m: boolean)}
  <span class="kt-dongho" class:kt-dongho-gap={lowTime} role="timer" aria-label="Thời gian còn lại {clock(left * 1000)}">
    <Icon name="clock" size={m ? 16 : 18} />
    <b class="num kt-dongho-so">{clock(left * 1000)}</b>
    {#if !m || lowTime}<span class="kt-dongho-chu">{lowTime ? 'Sắp hết giờ!' : 'còn lại'}</span>{/if}
  </span>
{/snippet}

{#snippet dongHoDau()}
  {@render dongHo(false)}
{/snippet}

<!-- Kết quả sau khi nộp cũng nằm trong khung: điểm ở giữa, câu sai là các ô con, nút ở chân khung. -->
{#snippet ketQua(m: boolean)}
  {#if result}
    <div class="zk-khung-dau">
      <h2 class="zk-khung-ten">Kết quả</h2>
      <span class="zk-so">Làm trong {clock(result.usedSec * 1000)}</span>
    </div>

    <div class="kt-diem" style="padding: {m ? 4 : 10}px 0">
      <span class="ib kt-diem-dau" style="background: {result.passed ? 'var(--ngoc-soft)' : 'var(--son-soft)'}; color: {result.passed ? 'var(--green-text)' : 'var(--bad-text)'}">
        <Icon name={result.passed ? 'check' : 'x'} size={m ? 30 : 34} />
      </span>
      <p class="bignum disp" style="font-size: {m ? 26 : 32}px">{num(result.score)} / {num(result.maxScore)} điểm</p>
      {#if result.contestTitle}
        <p class="muted">Đúng {result.correctCount} / {result.questionCount} câu{result.autoSubmitted ? ' · hết giờ tự nộp' : ''}</p>
      {:else}
        <p class="muted">{result.percent}% · {result.passed ? 'Đạt' : 'Chưa đạt'}{result.autoSubmitted ? ' · hết giờ tự nộp' : ''}</p>
      {/if}
    </div>

    {#if result.contestTitle}
      <p class="muted" style="text-align: center; font-size: 14px">
        Xem hạng của bạn ở mục Top chăm học, tab Cuộc thi.{result.answersHidden ? ' Đáp án sẽ hiện khi cuộc thi kết thúc.' : ''}
      </p>
    {/if}

    {#if result.results.length > 0}
      <div class="kt-sai">
        <p class="kicker">Các câu làm sai ({result.results.length})</p>
        {#each result.results as r (r.qid)}
          <div class="zk-o kt-sai-cau">
            <b style="font-size: 14px">Câu {r.no} · <span class="hz" lang="zh">{r.promptText}</span></b>
            <span style="font-size: 13.5px; color: var(--bad-text)">Bạn chọn: {r.yourAnswerText}</span>
            <span style="font-size: 13.5px; color: var(--green-text)">Đáp án: {r.rightAnswerText}</span>
            {#if r.explain}<span class="muted" style="font-size: 13px">{r.explain}</span>{/if}
          </div>
        {/each}
      </div>
    {/if}

    <div class="zk-chan" class:kt-chan-m={m}>
      {#if !result.contestTitle}
        <button type="button" class="btn" class:btn-lg={m} onclick={load}><Icon name="repeat" size={16} /> Làm lại</button>
      {/if}
      <a href={quayLai} class="btn btn-son" class:btn-lg={m}>Quay lại</a>
    </div>
  {/if}
{/snippet}

{#snippet lamBai(m: boolean)}
  {#if at && q}
    <!-- Hàng đầu khung: loại câu (máy tính) hoặc đồng hồ (điện thoại) bên trái, số câu bên phải. -->
    <div class="zk-khung-dau">
      {#if m}
        {@render dongHo(true)}
      {:else}
        <h2 class="zk-khung-ten kt-loai"><Icon name="quiz" size={18} /> {TYPE_LABEL[q.type] ?? q.type}</h2>
      {/if}
      <span class="kt-so-nhom">
        <span class="zk-so">Câu {cur + 1} / {total}</span>
        <span class="zk-so">Đã làm {answeredCount}/{total}</span>
      </span>
    </div>
    {#if m}
      <h2 class="zk-khung-ten kt-loai"><Icon name="quiz" size={16} /> {TYPE_LABEL[q.type] ?? q.type}</h2>
    {/if}

    <QuestionView
      type={q.type}
      payload={q.payload}
      bind:answer={() => answers[q.id] ?? null, setAnswer}
      listenLeft={q.type === 'listen' ? Math.max(0, at.listenLimit - (listens[q.id] ?? 0)) : null}
      onlisten={countListen}
      mobile={m}
    />

    <!-- Chân khung: chuyển câu, dãy số câu rồi nộp bài — gom một chỗ thay vì mấy mảnh rời. -->
    <div class="zk-chan kt-chan">
      <div class="kt-dieu" class:kt-dieu-m={m}>
        <button type="button" class="zk-nut" disabled={cur === 0} aria-label="Câu trước" onclick={() => cur--}>
          <Icon name="left" size={18} />{#if !m}Câu trước{/if}
        </button>
        <!-- Câu cuối: nút này thành Nộp bài như trước, không để người học bấm vào nút mờ. -->
        {#if cur + 1 < total}
          <button type="button" class="zk-nut zk-nut-chinh kt-tiep" onclick={() => cur++}>
            Câu tiếp <Icon name="right" size={18} />
          </button>
        {:else}
          <button type="button" class="zk-nut zk-nut-chinh kt-tiep" disabled={submitting} onclick={() => (confirmOpen = true)}>Nộp bài</button>
        {/if}
      </div>

      <div class="kt-socau" class:kt-socau-m={m} role="group" aria-label="Chọn câu">
        {#each at.questions as x, k (x.id)}
          <button type="button" class={qnClass(x, k)} onclick={() => (cur = k)} aria-label="Câu {k + 1}" aria-current={k === cur ? 'true' : undefined}>
            {k + 1}
          </button>
        {/each}
      </div>

      <div class="kt-nop-hang" class:kt-nop-hang-m={m}>
        <span class="muted kt-nop-chu">{conLai > 0 ? `Còn ${conLai} câu chưa làm` : 'Đã làm hết, có thể nộp bài'}</span>
        <button type="button" class="btn btn-son kt-nop" disabled={submitting} onclick={() => (confirmOpen = true)}>Nộp bài</button>
      </div>
    </div>
  {/if}
{/snippet}

<!-- Một khung trắng chứa trọn màn làm bài hoặc màn kết quả (khuôn chung của màn Lật thẻ). -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if result}
      {@render ketQua(m)}
    {:else}
      {@render lamBai(m)}
    {/if}
  </section>
{/snippet}

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Không mở được bài kiểm tra." desc={failMsg}>
      {#snippet action()}<a href={quayLai} class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if result === null && at === null}
    <Skeleton h={m ? '420px' : '520px'} r={22} />
  {:else if m}
    {@render khung(true)}
  {:else}
    <DauBai href={quayLai} label={contestTitle ? 'Bàn học' : mockTestId !== null ? 'Chọn đề' : 'Chọn bài'} {kicker} title={tenBai} right={result ? undefined : dongHoDau} />
    {@render khung(false)}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href={quayLai} class="btn btn-bare" aria-label="Thoát"><Icon name="left" size={20} /></a>
      <b style="flex: 1; min-width: 0; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{tenBai}</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    {#if contestTitle}
      <nav class="crumb" style="margin-left: 8px"><a href={quayLai}>Bàn học</a> / <b>Cuộc thi</b></nav>
    {:else}
      <nav class="crumb" style="margin-left: 8px"><a href={backHref}>Kiểm tra · HSK {levelId}</a> / <b>{heading}</b></nav>
    {/if}
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">{@render body(false)}</main>
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

{#if hetGio}
  <Modal title="Hết giờ" closable={false} onclose={() => {}}>
    <p style="font-size: 14px">Bài của bạn đang được nộp với các câu đã làm, chờ một chút nhé.</p>
  </Modal>
{/if}

<style>
  /* Đồng hồ: viên nền nhạt; sắp hết giờ thì đổi sang son để người làm kịp để ý. */
  .kt-dongho {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 40px;
    padding: 0 14px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: var(--surface-2);
    color: var(--text-2);
    white-space: nowrap;
  }
  .kt-dongho-so {
    font-size: 19px;
    color: var(--text);
  }
  .kt-dongho-chu {
    font-size: 13px;
  }
  .kt-dongho-gap {
    border-color: var(--son-line);
    background: var(--son-soft);
  }
  .kt-dongho-gap .kt-dongho-so,
  .kt-dongho-gap .kt-dongho-chu {
    color: var(--bad-text);
  }

  .kt-loai {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .kt-so-nhom {
    display: inline-flex;
    gap: 6px;
    flex-wrap: wrap;
  }

  /* Chân khung xếp dọc ba hàng, thay cho hàng ngang mặc định của .zk-chan. */
  .kt-chan {
    flex-direction: column;
    align-items: stretch;
    gap: 14px;
  }
  .kt-dieu {
    display: flex;
    justify-content: space-between;
    gap: 10px;
  }
  /* Điện thoại: nút lùi chỉ còn mũi tên, nút tiếp lấy hết phần còn lại cho dễ bấm. */
  .kt-dieu-m .kt-tiep {
    flex: 1 1 auto;
  }

  .kt-socau {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
    gap: 6px;
  }
  /* Ô số câu toàn cục cao 40px; điện thoại nâng lên 44px cho vừa ngón tay. */
  .kt-socau-m .qn {
    height: 44px;
  }

  .kt-nop-hang {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
  }
  .kt-nop-chu {
    font-size: 13.5px;
  }
  .kt-nop {
    min-height: 48px;
    padding: 0 24px;
  }
  .kt-nop-hang-m .kt-nop {
    width: 100%;
  }

  .kt-diem {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    text-align: center;
  }
  .kt-diem-dau {
    width: 74px;
    height: 74px;
    border-radius: 50%;
  }
  .kt-sai {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .kt-sai-cau {
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow-wrap: anywhere;
  }
  /* Điện thoại: hai nút kết quả xếp chồng, nút chính ở trên. */
  .kt-chan-m {
    flex-direction: column-reverse;
    align-items: stretch;
  }
</style>
