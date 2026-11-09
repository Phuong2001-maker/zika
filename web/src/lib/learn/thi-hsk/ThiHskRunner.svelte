<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { media } from '$lib/stores/media.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import QuestionView from '$lib/learn/QuestionView.svelte';
  import { answered, TYPE_LABEL } from '$lib/utils/grade';
  import { clock } from '$lib/utils/format';
  import { TEN_PHAN } from './cau-truc';
  import GhiAm from './GhiAm.svelte';
  import KetQuaHsk from './KetQuaHsk.svelte';
  import { ThiHsk, type CheDo, type CauHoi } from './state.svelte';

  // Màn làm đề Thi thử HSK 3.0. Giống màn Kiểm tra (TestRunner) về khung và thanh riêng lúc đang thi, thêm: câu chia
  // theo phần Nghe/Đọc/Viết/Nói, câu nói ghi âm, và chế độ luyện tập bấm Kiểm tra từng câu.
  let { mockId, mode }: { mockId: number; mode: CheDo } = $props();

  // svelte-ignore state_referenced_locally
  const st = new ThiHsk(mockId, mode);
  onMount(() => void st.tai());
  onDestroy(() => st.huy());

  const capDe = $derived(st.at?.mock?.levelId ?? null);
  const veDanhSach = $derived(capDe ? `/learn/hsk30?cap=${capDe}` : '/learn/hsk30');
  const tenDe = $derived(st.at?.mock?.title ?? 'Thi thử HSK 3.0');
  const tenCheDo = $derived(mode === 'practice' ? 'Luyện tập' : 'Thi thật');
  const kicker = $derived(
    st.at ? `HSK ${capDe} · ${tenCheDo} · ${st.total} câu${st.coGio ? ` · ${Math.round(st.at.durationSec / 60)} phút` : ''}` : 'Thi thử HSK 3.0',
  );
  const dangThi = $derived(st.coGio && st.ketQua === null);
  const kt = $derived(st.q ? (st.kiemTra[st.q.id] ?? null) : null);
  const conLai = $derived(st.total - st.daLam);

  function oSo(x: CauHoi, k: number): string {
    let c = 'qn';
    if (k === st.cur) c += ' cur';
    else if (answered(x.type, x.payload, st.answers[x.id] ?? null)) c += ' ans';
    return c;
  }
</script>

{#snippet dongHo(m: boolean)}
  <span class="th-dongho" class:th-dongho-gap={st.sapHet} role="timer" aria-label="Thời gian còn lại {clock(st.left * 1000)}">
    <Icon name="clock" size={m ? 16 : 18} />
    <b class="num th-dongho-so">{clock(st.left * 1000)}</b>
    {#if !m || st.sapHet}<span class="th-dongho-chu">{st.sapHet ? 'Sắp hết giờ!' : 'còn lại'}</span>{/if}
  </span>
{/snippet}

{#snippet dongHoDau()}
  {#if st.coGio && !st.ketQua}{@render dongHo(false)}{/if}
{/snippet}

{#snippet lamBai(m: boolean)}
  {#if st.at && st.q}
    {@const q = st.q}
    <div class="zk-khung-dau">
      {#if m && st.coGio}
        {@render dongHo(true)}
      {:else}
        <h2 class="zk-khung-ten th-loai">
          <span class="th-phan">{TEN_PHAN[q.section ?? 'reading'] ?? 'Đọc'}</span>
          {TYPE_LABEL[q.type] ?? q.type}
        </h2>
      {/if}
      <span class="th-so-nhom">
        <span class="zk-so">Câu {st.cur + 1} / {st.total}</span>
        <span class="zk-so">Đã làm {st.daLam}/{st.total}</span>
      </span>
    </div>
    {#if m && st.coGio}
      <h2 class="zk-khung-ten th-loai"><span class="th-phan">{TEN_PHAN[q.section ?? 'reading'] ?? 'Đọc'}</span> {TYPE_LABEL[q.type] ?? q.type}</h2>
    {/if}

    {#key q.id}
      {#if q.type === 'speak'}
        <GhiAm
          attemptId={st.at.attemptId}
          qid={q.id}
          payload={q.payload}
          bind:answer={() => st.answers[q.id] ?? null, (v) => st.datTraLoi(v)}
          locked={!!kt}
          mau={kt ? { sample: kt.sample ?? '', sampleVi: kt.sampleVi ?? '' } : null}
          mobile={m}
        />
      {:else}
        <QuestionView
          type={q.type}
          payload={q.payload}
          bind:answer={() => st.answers[q.id] ?? null, (v) => st.datTraLoi(v)}
          locked={!!kt}
          rightIndex={kt?.rightIndex ?? null}
          listenLeft={st.conNghe(q)}
          onlisten={() => st.demNghe()}
          mobile={m}
        />
      {/if}
    {/key}

    {#if st.luyenTap}
      {#if !kt}
        <button
          type="button"
          class="btn th-kiem"
          disabled={st.dangKiem || !answered(q.type, q.payload, st.answers[q.id] ?? null)}
          onclick={() => void st.kiemTraCau()}
        >
          <Icon name="check" size={16} /> {q.type === 'speak' ? 'Xem bài mẫu' : 'Kiểm tra'}
        </button>
      {:else if kt.graded}
        <div class="th-kq" class:th-kq-dung={kt.correct} role="status">
          <b><Icon name={kt.correct ? 'check' : 'x'} size={16} /> {kt.correct ? 'Đúng rồi' : 'Chưa đúng'}</b>
          {#if !kt.correct && kt.rightAnswerText}<span>Đáp án: <span class="hz" lang="zh">{kt.rightAnswerText}</span></span>{/if}
          {#if kt.explain}<span class="muted">{kt.explain}</span>{/if}
        </div>
      {/if}
    {/if}

    <div class="zk-chan th-chan">
      <div class="th-dieu" class:th-dieu-m={m}>
        <button type="button" class="zk-nut" disabled={st.cur === 0} aria-label="Câu trước" onclick={() => st.cur--}>
          <Icon name="chev" size={18} style="transform: rotate(180deg)" />{#if !m}Câu trước{/if}
        </button>
        {#if st.cur + 1 < st.total}
          <button type="button" class="zk-nut zk-nut-chinh th-tiep" onclick={() => st.cur++}>Câu tiếp</button>
        {:else}
          <button type="button" class="zk-nut zk-nut-chinh th-tiep" disabled={st.dangNop} onclick={() => (st.hoiNop = true)}>Nộp bài</button>
        {/if}
      </div>

      <!-- Dãy số câu chia theo phần, như phiếu trả lời đề thật. -->
      {#each st.nhom as g, gi (gi)}
        <div class="th-nhom">
          <span class="th-nhom-ten">{TEN_PHAN[g.phan]}</span>
          <div class="th-socau" class:th-socau-m={m} role="group" aria-label="Câu phần {TEN_PHAN[g.phan]}">
            {#each g.cau as { q: x, k } (x.id)}
              <button type="button" class={oSo(x, k)} onclick={() => (st.cur = k)} aria-label="Câu {k + 1}" aria-current={k === st.cur ? 'true' : undefined}>
                {k + 1}
              </button>
            {/each}
          </div>
        </div>
      {/each}

      <div class="th-nop-hang" class:th-nop-hang-m={m}>
        <span class="muted th-nop-chu">{conLai > 0 ? `Còn ${conLai} câu chưa làm` : 'Đã làm hết, có thể nộp bài'}</span>
        <button type="button" class="btn btn-son th-nop" disabled={st.dangNop} onclick={() => (st.hoiNop = true)}>Nộp bài</button>
      </div>
    </div>
  {/if}
{/snippet}

{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if st.ketQua}
      <KetQuaHsk kq={st.ketQua} mobile={m} onlamlai={() => void st.tai()} ve={veDanhSach} />
    {:else}
      {@render lamBai(m)}
    {/if}
  </section>
{/snippet}

{#snippet body(m: boolean)}
  {#if st.loi}
    <EmptyState title="Không mở được đề thi." desc={st.loi}>
      {#snippet action()}<a href={veDanhSach} class="btn btn-sm">Về danh sách đề</a>{/snippet}
    </EmptyState>
  {:else if st.dangTai || (!st.ketQua && !st.at)}
    <Skeleton h={m ? '420px' : '520px'} r={22} />
  {:else if m}
    {@render khung(true)}
  {:else}
    <DauBai href={veDanhSach} hideBack={dangThi} label="Chọn đề" {kicker} title={tenDe} right={dongHoDau} />
    {@render khung(false)}
  {/if}
{/snippet}

<!-- Thanh riêng như màn Kiểm tra: đang thi tính giờ thì không có lối bấm sang màn khác. -->
{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      {#if !dangThi}<a href={veDanhSach} class="btn btn-bare" aria-label="Về danh sách đề"><Icon name="chev" size={20} style="transform: rotate(180deg)" /></a>{/if}
      <b style="flex: 1; min-width: 0; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{tenDe}</b>
      <span class="tag">{tenCheDo}</span>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <nav class="crumb">{#if !dangThi}<a href={veDanhSach}>Thi thử HSK 3.0</a> / {/if}<b>{tenDe}</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 10px">
      <span class="tag">{dangThi ? 'Đang thi' : tenCheDo}</span>
      {#if !dangThi}<Avatar size={44} />{/if}
    </div>
  </header>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">{@render body(false)}</main>
{/if}

{#if st.hoiNop}
  <Modal title="Nộp bài?" onclose={() => (st.hoiNop = false)}>
    <p style="font-size: 14px">
      Bạn đã làm {st.daLam}/{st.total} câu{conLai > 0 ? ` — còn ${conLai} câu chưa trả lời` : ''}.
      {st.luyenTap ? 'Nộp để xem tổng kết từng phần.' : 'Nộp rồi thì không sửa được nữa.'}
    </p>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (st.hoiNop = false)}>Làm tiếp</button>
      <button type="button" class="btn btn-son btn-sm" disabled={st.dangNop} onclick={() => void st.nop(false)}>Nộp bài</button>
    {/snippet}
  </Modal>
{/if}

{#if st.hetGio}
  <Modal title="Hết giờ" closable={false} onclose={() => {}}>
    <p style="font-size: 14px">Bài của bạn đang được nộp với các câu đã làm, chờ một chút nhé.</p>
  </Modal>
{/if}

<style>
  .th-dongho {
    display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 14px;
    border: 1px solid var(--line); border-radius: 999px; background: var(--surface-2); color: var(--text-2); white-space: nowrap;
  }
  .th-dongho-so { font-size: 19px; color: var(--text); }
  .th-dongho-chu { font-size: 13px; }
  .th-dongho-gap { border-color: var(--son-line); background: var(--son-soft); }
  .th-dongho-gap .th-dongho-so, .th-dongho-gap .th-dongho-chu { color: var(--bad-text); }
  .th-loai { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .th-phan { padding: 3px 10px; border-radius: 999px; background: #FFE9CC; color: #7A4A00; font-size: 12.5px; font-weight: 800; }
  .th-so-nhom { display: inline-flex; gap: 6px; flex-wrap: wrap; }
  .th-kiem { align-self: center; min-height: 44px; padding: 0 22px; }
  .th-kq { display: flex; flex-direction: column; gap: 4px; padding: 12px 14px; border: 1px solid var(--son-line); border-radius: 14px; background: var(--son-soft); font-size: 14px; }
  .th-kq b { display: inline-flex; align-items: center; gap: 6px; color: var(--bad-text); }
  .th-kq-dung { border-color: var(--line); background: var(--ngoc-soft); }
  .th-kq-dung b { color: var(--green-text); }
  /* .zk-chan là hàng ngang có wrap + space-between; đổi sang cột thì phải tắt cả hai, không các nhóm số câu bị kéo giãn. */
  .th-chan { flex-direction: column; flex-wrap: nowrap; justify-content: flex-start; align-items: stretch; gap: 14px; }
  .th-dieu { display: flex; justify-content: space-between; gap: 10px; }
  .th-dieu-m .th-tiep { flex: 1 1 auto; }
  .th-nhom { display: flex; flex-direction: column; gap: 6px; flex: none; }
  .th-nhom-ten { font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--text-3); }
  .th-socau { display: grid; grid-template-columns: repeat(auto-fill, minmax(42px, 1fr)); gap: 6px; }
  .th-socau-m .qn { height: 44px; }
  .th-nop-hang { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
  .th-nop-chu { font-size: 13.5px; }
  .th-nop { min-height: 48px; padding: 0 24px; }
  .th-nop-hang-m .th-nop { width: 100%; }
</style>
