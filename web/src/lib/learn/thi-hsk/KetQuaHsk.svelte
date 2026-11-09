<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { clock, num } from '$lib/utils/format';
  import { PHAN, TEN_PHAN } from './cau-truc';
  import BanGhiNghe from './BanGhiNghe.svelte';
  import type { KetQua } from './state.svelte';

  // Kết quả đề HSK 3.0: tỉ lệ đúng chung rồi từng phần như phiếu điểm đề thật; phần nói không chấm, chỉ đếm câu đã nói
  // và cho nghe lại bản ghi cạnh bài mẫu. Câu sai kèm đáp án và giải thích.
  // Cuộc thi: không có nút Làm lại (onlamlai = null, mỗi người một lượt); cuộc thi còn mở thì server giấu đáp án.
  let {
    kq,
    mobile = false,
    onlamlai,
    ve,
    tenVe = 'Về danh sách đề',
  }: { kq: KetQua; mobile?: boolean; onlamlai: (() => void) | null; ve: string; tenVe?: string } = $props();

  const icon = (p: string): string => PHAN.find((x) => x.code === p)?.icon ?? 'quiz';
  const coCham = $derived(kq.maxScore > 0);
</script>

<div class="zk-khung-dau">
  <h2 class="zk-khung-ten">{kq.contestTitle ? 'Kết quả cuộc thi' : kq.mode === 'practice' ? 'Kết quả luyện tập' : 'Kết quả thi thử'}</h2>
  <span class="zk-so">Làm trong {clock(kq.usedSec * 1000)}{kq.autoSubmitted ? ' · hết giờ tự nộp' : ''}</span>
</div>

<div class="kq-dau" class:kq-dau-m={mobile}>
  {#if coCham}
    <p class="bignum disp kq-so">{kq.percent}%</p>
    <p class="muted">Đúng {kq.correctCount} câu chấm được · {num(kq.score)} / {num(kq.maxScore)} điểm</p>
  {:else}
    <p class="muted">Đề này chỉ có phần nói, tự nghe lại và so với bài mẫu bên dưới.</p>
  {/if}
</div>

{#if kq.sections.length > 0}
  <div class="kq-phan">
    {#each kq.sections as s (s.section)}
      <div class="zk-o kq-dong">
        <span class="kq-ten"><Icon name={icon(s.section)} size={17} /> {TEN_PHAN[s.section] ?? s.section}</span>
        {#if s.graded}
          <span class="kq-vach" aria-hidden="true"><span style="width: {s.percent}%"></span></span>
          <b class="num kq-pt">{s.percent}%</b>
          <span class="muted num kq-dem">{s.done}/{s.total} câu</span>
        {:else}
          <span class="muted kq-noi">Đã nói {s.done}/{s.total} câu · tự đánh giá</span>
        {/if}
      </div>
    {/each}
  </div>
{/if}

{#if kq.speaking.length > 0}
  <div class="kq-ds">
    <p class="kicker">Phần nói — nghe lại bản ghi của bạn</p>
    {#each kq.speaking as r (r.qid)}
      <div class="zk-o kq-cau">
        <b style="font-size: 14px">Câu {r.no} · <span class="hz" lang="zh">{r.promptText}</span></b>
        {#if r.yourAnswerText === 'Chưa trả lời'}
          <span class="muted" style="font-size: 13px">Chưa ghi âm câu này.</span>
        {:else}
          <BanGhiNghe attemptId={kq.attemptId} qid={r.qid} />
        {/if}
        <span style="font-size: 13.5px">{r.yourAnswerText}</span>
        {#if r.rightAnswerText}<span style="font-size: 13.5px; color: var(--green-text)">Mẫu: <span class="hz" lang="zh">{r.rightAnswerText}</span></span>{/if}
        {#if r.explain}<span class="muted" style="font-size: 13px">{r.explain}</span>{/if}
      </div>
    {/each}
  </div>
{/if}

{#if kq.results.length > 0}
  <div class="kq-ds">
    <p class="kicker">Các câu làm sai ({kq.results.length})</p>
    {#each kq.results as r (r.qid)}
      <div class="zk-o kq-cau">
        <b style="font-size: 14px">Câu {r.no}{r.section ? ` · ${TEN_PHAN[r.section] ?? ''}` : ''} · <span class="hz" lang="zh">{r.promptText}</span></b>
        <span style="font-size: 13.5px; color: var(--bad-text)">Bạn chọn: {r.yourAnswerText}</span>
        <span style="font-size: 13.5px; color: var(--green-text)">Đáp án: {r.rightAnswerText}</span>
        {#if r.explain}<span class="muted" style="font-size: 13px">{r.explain}</span>{/if}
      </div>
    {/each}
  </div>
{:else if kq.answersHidden}
  <p class="muted" style="text-align: center; font-size: 14px">Đáp án, giải thích và phần nghe lại bài nói hiện sau khi cuộc thi kết thúc.</p>
{:else if coCham}
  <p class="muted" style="text-align: center; font-size: 14px">Không sai câu nào. Giỏi quá!</p>
{/if}

<div class="zk-chan" class:kq-chan-m={mobile}>
  {#if onlamlai}<button type="button" class="btn" class:btn-lg={mobile} onclick={onlamlai}><Icon name="repeat" size={16} /> Làm lại</button>{/if}
  <a href={ve} class="btn btn-son" class:btn-lg={mobile}>{tenVe}</a>
</div>

<style>
  .kq-dau { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 8px 0; text-align: center; }
  .kq-so { font-size: 44px; line-height: 1.1; }
  .kq-dau-m .kq-so { font-size: 36px; }
  .kq-phan { display: flex; flex-direction: column; gap: 8px; }
  .kq-dong { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .kq-ten { display: inline-flex; align-items: center; gap: 6px; width: 72px; font-weight: 700; }
  .kq-vach { flex: 1 1 140px; height: 10px; border-radius: 999px; background: var(--track); overflow: hidden; }
  .kq-vach span { display: block; height: 100%; border-radius: 999px; background: var(--ngoc); }
  .kq-pt { width: 48px; text-align: right; }
  .kq-dem { font-size: 13px; }
  .kq-noi { font-size: 14px; }
  .kq-ds { display: flex; flex-direction: column; gap: 8px; }
  .kq-cau { display: flex; flex-direction: column; gap: 6px; overflow-wrap: anywhere; }
  .kq-chan-m { flex-direction: column-reverse; align-items: stretch; }
</style>
