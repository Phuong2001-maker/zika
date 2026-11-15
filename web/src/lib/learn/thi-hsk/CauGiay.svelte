<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import QuestionView from '$lib/learn/QuestionView.svelte';
  import { speak } from '$lib/learn/speech';
  import { tileOrder } from '$lib/utils/shuffle';
  import type { Answer } from '$lib/utils/grade';
  import GhiAm from './GhiAm.svelte';
  import Ruby from './Ruby.svelte';
  import { urlHinh } from './hinh';
  import type { CauHoi, KiemTra } from './state.svelte';

  // Một câu của màn Thi thử kiểu tờ đề (chốt 15/11/2026, mẫu release/mau-giao-dien/thi-thu-hsk/): trình bày gọn như đề giấy
  // — số câu bên trái, nút nghe nhỏ, tranh/phương án trên một hàng, pinyin trên chữ ở HSK 1–2. Dạng lạ thì dùng QuestionView.
  let {
    q,
    answer,
    onanswer,
    locked = false,
    kt = null,
    listenLeft = null,
    onlisten,
    py = null,
    mobile = false,
    attemptId,
  }: {
    q: CauHoi;
    answer: Answer;
    onanswer: (v: Answer) => void;
    locked?: boolean;
    kt?: KiemTra | null;
    listenLeft?: number | null;
    onlisten?: () => void;
    py?: Record<string, [string, string][]> | null;
    mobile?: boolean;
    attemptId: number;
  } = $props();

  const p = $derived(q.payload);
  const opts = $derived<string[]>(p.options ?? []);
  const CHU = 'ABCDEFGH';
  const dungSai = $derived(opts.join('|') === 'Đúng|Sai' || opts.join('|') === 'Sai|Đúng');
  const coNghe = $derived(q.type === 'listen' || (q.type === 'pic' && !!p.audioText));

  /** Lớp của một phương án sau khi kiểm (luyện tập): đúng viền xanh, chọn sai viền đỏ, đáp án đúng viền xanh đứt. */
  function lop(i: number): string {
    if (kt && kt.rightIndex !== null) {
      if (i === kt.rightIndex) return answer === i ? 'dung' : 'dap-an';
      if (i === answer) return 'sai';
      return '';
    }
    return answer === i ? 'chon' : '';
  }
  function chon(i: number): void {
    if (!locked) onanswer(i);
  }
  function nghe(): void {
    if (listenLeft === 0) return;
    onlisten?.();
    void speak(String(p.audioText ?? ''), p.audioUrl);
  }

  /* Sắp xếp: chỉ số ô theo tiles server gửi (đã xáo), giống QuestionView. */
  const tiles = $derived<string[]>(p.tiles ?? []);
  const hienO = $derived(tileOrder(tiles));
  const xep = $derived<number[]>(Array.isArray(answer) ? (answer as number[]) : []);

  /* Nối: answer = { pairs: { trái: phải } } theo chỉ số cột phải server gửi (payload.right). */
  const trai = $derived<string[]>(p.left ?? (p.pairs ?? []).map((x: string[]) => x[0]));
  const phai = $derived<string[]>(p.right ?? (p.pairs ?? []).map((x: string[]) => x[1]));
  const cap = $derived<Record<number, number>>(
    answer && typeof answer === 'object' && !Array.isArray(answer) ? ((answer as { pairs?: Record<number, number> }).pairs ?? {}) : {},
  );
  function noi(i: number, j: number): void {
    if (locked) return;
    const next = { ...cap };
    for (const k of Object.keys(next)) if (next[Number(k)] === j) delete next[Number(k)];
    if (cap[i] === j) delete next[i];
    else next[i] = j;
    onanswer({ pairs: next });
  }
  const chuCau = $derived(String(p.sentence ?? '').split('___'));
</script>

{#snippet nutNghe()}
  <span class="cg-nghe">
    <button type="button" class="cg-nghe-nut" disabled={listenLeft === 0} onclick={nghe} aria-label="Nghe câu {q.no}"><Icon name="speaker" size={18} /></button>
    {#if listenLeft !== null}<span class="cg-nghe-con">còn {listenLeft} lượt</span>{/if}
  </span>
{/snippet}

{#snippet ketQua()}
  {#if kt && kt.graded}
    <p class="cg-kq" class:dung={kt.correct}>
      <b><Icon name={kt.correct ? 'check' : 'x'} size={15} /> {kt.correct ? 'Đúng' : 'Chưa đúng'}</b>
      {#if !kt.correct && kt.rightAnswerText}<span>· Đáp án: <span class="hz" lang="zh">{kt.rightAnswerText}</span></span>{/if}
      {#if kt.explain}<span class="muted">· {kt.explain}</span>{/if}
    </p>
  {/if}
{/snippet}

<div class="cg" class:m={mobile} id="cau-{q.no}" data-cau={q.no}>
  {#if q.type === 'pic'}
    <!-- Chọn tranh: như bảng tranh của đề giấy — số câu + nút nghe (phần Nghe) hoặc câu chữ (phần Đọc), các tranh một hàng. -->
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      {#if coNghe}{@render nutNghe()}{/if}
      {#if p.ask}<p class="cg-cau hz" lang="zh"><Ruby text={p.ask} {py} /></p>{/if}
    </div>
    <div class="cg-tranh" class:nhieu={opts.length > 4}>
      {#each opts as o, i (i)}
        <button type="button" class="cg-tranh-o {lop(i)}" disabled={locked} onclick={() => chon(i)} aria-label="Tranh {CHU[i]}" aria-pressed={answer === i}>
          <img src={urlHinh(o)} alt="" loading="lazy" />
          <span class="cg-chu">{CHU[i]}{#if lop(i) === 'chon' || lop(i) === 'dung' || lop(i) === 'dap-an'} ✓{:else if lop(i) === 'sai'} ✗{/if}</span>
        </button>
      {/each}
    </div>
  {:else if q.type === 'listen' && dungSai}
    <div class="cg-hang">
      <b class="cg-so">{q.no}</b>
      {@render nutNghe()}
      {#if p.imageUrl}<img class="cg-anh" src={urlHinh(String(p.imageUrl))} alt="Tranh của câu {q.no}" loading="lazy" />{/if}
      <!-- Server xáo phương án mỗi lượt; Đúng/Sai thì luôn hiện Đúng trước cho khỏi bấm nhầm (đáp án vẫn theo chỉ số). -->
      <span class="cg-ds">
        {#each [...opts.keys()].sort((a, b) => (opts[a] === 'Đúng' ? -1 : 0) - (opts[b] === 'Đúng' ? -1 : 0)) as i (i)}
          <button type="button" class="cg-ds-nut {lop(i)}" disabled={locked} onclick={() => chon(i)} aria-pressed={answer === i}>
            <Icon name={opts[i] === 'Đúng' ? 'check' : 'x'} size={17} /> {opts[i]}
          </button>
        {/each}
      </span>
    </div>
  {:else if ['listen', 'blank', 'pick', 'toZh', 'meaning', 'tone', 'pinyin'].includes(q.type)}
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      {#if coNghe}{@render nutNghe()}{/if}
      <div class="cg-noi">
        {#if q.type === 'blank'}
          <p class="cg-cau hz" lang="zh">
            <Ruby text={chuCau[0]} {py} /><span class="cg-trong" class:co={typeof answer === 'number'}>{typeof answer === 'number' ? (opts[answer] ?? '') : ''}</span><Ruby text={chuCau[1] ?? ''} {py} />
          </p>
        {:else if q.type === 'pick'}
          {#if p.imageUrl}<img class="cg-anh" src={urlHinh(String(p.imageUrl))} alt="Hình của câu {q.no}" loading="lazy" />{/if}
          <!-- Câu chọn có thể trộn chữ Việt (lời dặn của câu ghép bộ phận) → font trộn, không dùng font chữ Hán cho chữ Việt. -->
          <p class="cg-cau cg-doan cg-tron" lang="zh"><Ruby text={String(p.ask ?? '')} {py} /></p>
        {:else if q.type === 'toZh'}
          <p class="cg-goi-y">“{p.vi}”</p>
        {:else if q.type !== 'listen'}
          <p class="cg-cau hz" lang="zh"><Ruby text={String(p.word ?? '')} {py} />{#if p.ask}<span class="cg-goi-y"> · {p.ask}</span>{/if}</p>
        {/if}
        <div class="cg-pa" class:luoi={q.type === 'toZh' || opts.some((o) => o.length > 10)} class:khung={opts.length > 4}>
          {#each opts as o, i (i)}
            <button type="button" class="cg-pa-nut {lop(i)}" disabled={locked} onclick={() => chon(i)} aria-pressed={answer === i}>
              <b>{CHU[i] ?? i + 1}</b><span class="hz" lang="zh"><Ruby text={o} {py} /></span>
            </button>
          {/each}
        </div>
      </div>
    </div>
  {:else if q.type === 'tf'}
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      <div class="cg-noi">
        <p class="cg-cau cg-doan cg-khung hz" lang="zh"><Ruby text={String(p.passage ?? '')} {py} /></p>
        <div class="cg-sao">
          <p class="cg-cau hz" lang="zh"><span class="cg-sao-dau">★</span><Ruby text={String(p.statement ?? '')} {py} /></p>
          <span class="cg-ds">
            <button type="button" class="cg-ds-nut" class:chon={answer === true} disabled={locked} onclick={() => !locked && onanswer(true)} aria-pressed={answer === true}><Icon name="check" size={17} /> Đúng</button>
            <button type="button" class="cg-ds-nut" class:chon={answer === false} disabled={locked} onclick={() => !locked && onanswer(false)} aria-pressed={answer === false}><Icon name="x" size={17} /> Sai</button>
          </span>
        </div>
      </div>
    </div>
  {:else if q.type === 'match'}
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      <div class="cg-noi cg-noi-2" class:m={mobile}>
        <div class="cg-cot">
          {#each trai as t, i (i)}
            <div class="cg-trai">
              <p class="cg-cau hz" lang="zh"><Ruby text={t} {py} /></p>
              <span class="cg-chu-chon" role="group" aria-label="Chọn nghĩa cho câu {i + 1}">
                {#each phai as _, j (j)}
                  <button type="button" class="cg-o-chu" class:chon={cap[i] === j} disabled={locked} onclick={() => noi(i, j)}>{CHU[j]}</button>
                {/each}
              </span>
            </div>
          {/each}
        </div>
        <div class="cg-cot">
          {#each phai as t, j (j)}
            <div class="cg-phai"><b>{CHU[j]}</b><span>{t}</span></div>
          {/each}
        </div>
      </div>
    </div>
  {:else if q.type === 'order'}
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      <div class="cg-noi">
        {#if p.vi}<p class="cg-goi-y">{p.vi}</p>{/if}
        <div class="cg-xep">
          {#each xep as t, pos (pos)}
            <button type="button" class="cg-manh dat hz" lang="zh" disabled={locked} onclick={() => onanswer(xep.filter((_, k) => k !== pos))}><Ruby text={tiles[t]} {py} /></button>
          {/each}
          {#if xep.length === 0}<span class="muted" style="font-size: 13.5px">Bấm các mảnh bên dưới theo đúng thứ tự</span>{/if}
        </div>
        <div class="cg-kho">
          {#each hienO as i (i)}
            <button type="button" class="cg-manh hz" lang="zh" disabled={locked || xep.includes(i)} onclick={() => onanswer([...xep, i])}><Ruby text={tiles[i]} {py} /></button>
          {/each}
        </div>
      </div>
    </div>
  {:else if q.type === 'go'}
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      <p class="cg-cau hz" lang="zh">
        <Ruby text={chuCau[0]} {py} /><span class="cg-go"><span class="cg-go-py">{p.pinyin}</span><input
            class="cg-go-o hz"
            lang="zh"
            maxlength="4"
            disabled={locked}
            value={typeof answer === 'string' ? answer : ''}
            oninput={(e) => onanswer(e.currentTarget.value)}
            aria-label="Câu {q.no}: viết chữ Hán có pinyin {p.pinyin}"
          /></span><Ruby text={chuCau[1] ?? ''} {py} />
      </p>
    </div>
  {:else if q.type === 'speak'}
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      <div class="cg-noi">
        <GhiAm
          {attemptId}
          qid={q.id}
          payload={q.payload}
          bind:answer={() => answer ?? null, (v) => onanswer(v)}
          locked={!!kt}
          mau={kt ? { sample: kt.sample ?? '', sampleVi: kt.sampleVi ?? '' } : null}
          {mobile}
        />
      </div>
    </div>
  {:else}
    <div class="cg-dau">
      <b class="cg-so">{q.no}</b>
      <div class="cg-noi">
        <QuestionView type={q.type} payload={q.payload} bind:answer={() => answer ?? null, (v) => onanswer(v)} {locked} rightIndex={kt?.rightIndex ?? null} {listenLeft} {onlisten} {mobile} />
      </div>
    </div>
  {/if}
  {@render ketQua()}
</div>

<style>
  .cg {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 0;
    scroll-margin-top: 140px;
  }
  .cg + :global(.cg) {
    border-top: 1px dashed var(--line-3);
  }
  .cg-dau,
  .cg-hang {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }
  .cg-hang {
    align-items: center;
    flex-wrap: wrap;
  }
  .cg-so {
    flex: none;
    min-width: 28px;
    font-size: 17px;
    font-weight: 800;
    line-height: 2.1;
  }
  .cg-noi {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .cg-cau {
    font-size: 21px;
    line-height: 2.2;
  }
  .cg-doan {
    white-space: pre-line;
  }
  .cg-tron {
    font-family: var(--font-mix);
    font-weight: 500;
  }
  .cg-khung {
    padding: 8px 16px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--surface-2);
  }
  .cg-goi-y {
    font-size: 15px;
    color: var(--text-2);
  }
  .cg-nghe {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .cg-nghe-nut {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 50%;
    background: var(--son);
    color: #fff;
    cursor: pointer;
    box-shadow: 0 4px 10px rgb(229 72 77 / 0.3);
  }
  .cg-nghe-nut:disabled {
    opacity: 0.4;
    cursor: default;
  }
  .cg-nghe-con {
    font-size: 12px;
    color: var(--text-2);
    white-space: nowrap;
  }
  .cg-tranh {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    padding-left: 40px;
  }
  .cg-tranh.nhieu {
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  }
  .m .cg-tranh {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
    padding-left: 0;
  }
  .cg-tranh-o {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 6px;
    border: 2px solid transparent;
    border-radius: 12px;
    background: none;
    font: inherit;
    cursor: pointer;
  }
  .cg-tranh-o img {
    width: 100%;
    max-width: 150px;
    aspect-ratio: 4 / 3;
    object-fit: contain;
    border-radius: 10px;
    background: #fffdf8;
  }
  .cg-chu {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--text-2);
  }
  .cg-anh {
    width: 128px;
    max-width: 40vw;
    height: auto;
    border-radius: 10px;
    background: #fffdf8;
  }
  .cg-ds {
    display: flex;
    gap: 8px;
    margin-left: auto;
  }
  .m .cg-ds {
    flex-direction: column;
  }
  .cg-ds-nut {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 44px;
    padding: 0 16px;
    border: 1px solid var(--line-3);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text-2);
    font: 700 14px/1 var(--font-ui);
    cursor: pointer;
  }
  .cg-pa {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .cg-pa.luoi {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr));
  }
  .cg-pa.khung {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
  }
  .cg-pa-nut {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 50px;
    padding: 4px 14px 4px 8px;
    border: 1px solid var(--line-3);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    font: inherit;
    text-align: left;
    cursor: pointer;
  }
  .cg-pa-nut > b {
    flex: none;
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--surface-2);
    font-size: 13px;
    color: var(--text-2);
  }
  .cg-pa-nut .hz {
    font-size: 18px;
    line-height: 2;
  }
  /* Trạng thái chọn / sau khi kiểm (luyện tập), dùng chung cho tranh, phương án, Đúng/Sai. */
  .chon {
    border-color: var(--son) !important;
    background: var(--son-soft) !important;
    color: var(--son-text) !important;
  }
  .cg-pa-nut.chon > b {
    background: var(--son);
    color: #fff;
  }
  .dung {
    border-color: var(--ngoc) !important;
    background: var(--ngoc-soft) !important;
  }
  .sai {
    border-color: #dc2626 !important;
    background: #fef2f2 !important;
  }
  .dap-an {
    border-color: var(--ngoc) !important;
    border-style: dashed !important;
  }
  .cg-trong {
    display: inline-block;
    min-width: 64px;
    margin: 0 6px;
    padding: 0 6px;
    border-bottom: 2px solid var(--text);
    line-height: 1.3;
    text-align: center;
  }
  .cg-trong.co {
    color: var(--son-text);
  }
  .cg-sao {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .cg-sao-dau {
    margin-right: 6px;
    color: var(--son);
    font-size: 16px;
  }
  .cg-noi-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 18px;
  }
  .cg-noi-2.m {
    grid-template-columns: 1fr;
  }
  .cg-cot {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .cg-trai {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .cg-chu-chon {
    display: flex;
    gap: 4px;
  }
  .cg-o-chu {
    width: 34px;
    height: 34px;
    border: 1.5px solid var(--line-3);
    border-radius: 8px;
    background: var(--surface);
    font: 800 13px/1 var(--font-ui);
    color: var(--text-2);
    cursor: pointer;
  }
  .cg-phai {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 50px;
    padding: 0 12px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--surface-2);
    font-size: 14.5px;
  }
  .cg-phai b {
    color: var(--text-2);
  }
  .cg-xep {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 6px;
    min-height: 60px;
    padding: 6px 0 8px;
    border-bottom: 2px solid var(--text);
  }
  .cg-kho {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .cg-manh {
    display: inline-flex;
    align-items: center;
    min-height: 50px;
    padding: 0 14px;
    border: 1px solid var(--line-3);
    border-radius: 12px;
    background: var(--surface);
    font-size: 18px;
    line-height: 2;
    cursor: pointer;
  }
  .cg-manh:disabled {
    opacity: 0.4;
    cursor: default;
  }
  .cg-manh.dat {
    border-color: var(--son-line);
    background: var(--son-soft);
  }
  .cg-manh.dat:disabled {
    opacity: 1;
  }
  .cg-go {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    margin: 0 4px;
    vertical-align: bottom;
    line-height: 1.2;
  }
  .cg-go-py {
    font-family: var(--font-ui);
    font-size: 12px;
    font-weight: 600;
    color: var(--text-2);
  }
  .cg-go-o {
    width: 2.4em;
    height: 1.6em;
    border: 1px solid var(--ink-strong);
    border-radius: 6px;
    background: var(--surface);
    color: var(--son-text);
    font-size: 21px;
    text-align: center;
  }
  .cg-kq {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 8px;
    padding: 8px 12px;
    border: 1px solid var(--son-line);
    border-radius: 10px;
    background: var(--son-soft);
    font-size: 13.5px;
  }
  .cg-kq b {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--bad-text);
  }
  .cg-kq.dung {
    border-color: var(--line);
    background: var(--ngoc-soft);
  }
  .cg-kq.dung b {
    color: var(--green-text);
  }
</style>
