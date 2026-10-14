<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { speak } from '$lib/learn/speech';
  import { isChoice, PAIR_COLORS, type Answer, type Payload } from '$lib/utils/grade';

  // Hiển thị một câu hỏi cho người học, dùng chung cho Bài tập và Kiểm tra (06 §6).
  // Component không tự chấm: trang gọi nó quyết định `state` để tô màu.
  let {
    type,
    payload,
    answer = $bindable(),
    locked = false,
    /** Khi đã chấm (Bài tập): chỉ số đáp án đúng để tô xanh. */
    rightIndex = null,
    listenLeft = null,
    onlisten,
    mobile = false,
  }: {
    type: string;
    payload: Payload;
    answer: Answer;
    locked?: boolean;
    rightIndex?: number | null;
    listenLeft?: number | null;
    onlisten?: () => void;
    mobile?: boolean;
  } = $props();

  const options = $derived<string[]>(payload.options ?? []);
  const hzOptions = $derived(['audio', 'blank', 'pick', 'toZh'].includes(type));
  const isWordQ = $derived(['meaning', 'pinyin', 'tone'].includes(type));
  const isAudioQ = $derived(type === 'audio' || type === 'listen');
  const isBlankQ = $derived(type === 'blank');

  /* ---------------- trắc nghiệm ---------------- */
  function pickOption(i: number): void {
    if (!locked) answer = i;
  }

  function optClass(i: number): string {
    if (rightIndex !== null) {
      if (i === rightIndex) return 'opt right';
      if (i === answer) return 'opt wrong';
      return 'opt';
    }
    return answer === i ? 'opt sel' : 'opt';
  }

  /* ---------------- sắp xếp câu ---------------- */
  const tiles = $derived<string[]>(payload.tiles ?? []);
  const picked = $derived<number[]>(Array.isArray(answer) ? (answer as number[]) : []);

  function addTile(i: number): void {
    if (locked || picked.includes(i)) return;
    answer = [...picked, i];
  }
  function removeTile(pos: number): void {
    if (locked) return;
    answer = picked.filter((_, k) => k !== pos);
  }
  function clearTiles(): void {
    if (!locked) answer = [];
  }

  /* ---------------- nối từ ---------------- */
  const left = $derived<string[]>(payload.left ?? (payload.pairs ?? []).map((p: string[]) => p[0]));
  const right = $derived<string[]>(payload.right ?? (payload.pairs ?? []).map((p: string[]) => p[1]));
  let selLeft = $state<number | null>(null);

  const pairs = $derived<Record<number, number>>(
    answer && typeof answer === 'object' && !Array.isArray(answer) ? ((answer as { pairs?: Record<number, number> }).pairs ?? {}) : {},
  );

  function clickLeft(i: number): void {
    if (locked) return;
    selLeft = selLeft === i ? null : i;
  }
  function clickRight(j: number): void {
    if (locked || selLeft === null) return;
    const next = { ...pairs };
    // Một nghĩa chỉ nối với một từ: gỡ cặp cũ nếu nghĩa này đã dùng.
    for (const k of Object.keys(next)) if (next[Number(k)] === j) delete next[Number(k)];
    next[selLeft] = j;
    answer = { pairs: next };
    selLeft = null;
  }
  /** Số thứ tự cặp để tô cùng màu hai bên. */
  const rightOwner = (j: number): number | null => {
    for (const k of Object.keys(pairs)) if (pairs[Number(k)] === j) return Number(k);
    return null;
  };
</script>

<div class="q" class:m={mobile}>
  <!-- Đề bài -->
  {#if isWordQ}
    <div class="word">
      {#each Array.from(String(payload.word ?? '')) as ch (ch)}
        <span class="tian hz" lang="zh" style="width: {mobile ? 86 : 106}px; height: {mobile ? 86 : 106}px; font-size: {mobile ? 58 : 74}px">{ch}</span>
      {/each}
    </div>
    {#if payload.ask}<p class="muted ask">{payload.ask}</p>{/if}
  {/if}

  {#if isAudioQ}
    <div class="audio">
      <button
        type="button"
        class="speak"
        style="width: {mobile ? 80 : 92}px; height: {mobile ? 80 : 92}px"
        aria-disabled={listenLeft === 0}
        disabled={listenLeft === 0}
        onclick={() => { onlisten?.(); void speak(String(payload.audioText ?? ''), payload.audioUrl); }}
      >
        <Icon name="speaker" size={mobile ? 30 : 34} />
      </button>
      <p class="muted" style="font-size: 13.5px">
        {#if listenLeft === null}
          Bấm để nghe lại
        {:else}
          Nghe đoạn ngắn rồi chọn câu đúng · còn {listenLeft} lần
        {/if}
      </p>
    </div>
  {/if}

  {#if isBlankQ}
    {@const parts = String(payload.sentence ?? '').split('___')}
    <p class="hz sentence" lang="zh" style="font-size: {mobile ? 24 : 30}px">
      {parts[0]}<span class="blank">{rightIndex !== null ? options[rightIndex] : ''}</span>{parts[1] ?? ''}
    </p>
    {#if payload.showMeaning && payload.vi}<p class="muted ask">{payload.vi}</p>{/if}
  {/if}

  {#if type === 'pick' && payload.ask}
    <p class="ask-big">{payload.ask}</p>
  {/if}
  {#if type === 'toZh' && payload.vi}
    <p class="ask-big" style="font-size: {mobile ? 19 : 22}px">“{payload.vi}”</p>
  {/if}

  {#if type === 'tf'}
    <div class="passage hz" lang="zh">{payload.passage}</div>
    <p class="stmt hz" lang="zh"><span class="star">★</span> {payload.statement}</p>
  {/if}

  {#if type === 'trans'}
    <p class="hz" lang="zh" style="font-size: {mobile ? 24 : 30}px; text-align: center">{payload.zh}</p>
    {#if payload.pinyin}
      <p class="muted" style="font-size: 14px; text-align: center">{payload.pinyin}</p>
    {/if}
  {/if}

  {#if type === 'order' && payload.vi}
    <p class="ask-big" style="font-size: {mobile ? 17 : 18}px">“{payload.vi}”</p>
  {/if}

  <!-- Vùng trả lời -->
  {#if isChoice(type)}
    <div class="opts" class:one={hzOptions && options.some((o) => o.length > 12)}>
      {#each options as o, i (i)}
        <button type="button" class={optClass(i)} disabled={locked} onclick={() => pickOption(i)}>
          <span class="k">{String.fromCharCode(65 + i)}</span>
          <span class={hzOptions ? 'hz' : ''} lang={hzOptions ? 'zh' : 'vi'}>{o}</span>
        </button>
      {/each}
    </div>
  {:else if type === 'order'}
    <div class="drop" class:trong={picked.length === 0}>
      {#if picked.length === 0}
        <span class="muted" style="font-size: 14px">Bấm các ô chữ bên dưới theo đúng thứ tự</span>
      {:else}
        {#each picked as t, pos (pos)}
          <button type="button" class="wt" disabled={locked} onclick={() => removeTile(pos)}>{tiles[t]}</button>
        {/each}
      {/if}
    </div>
    <div class="tiles">
      {#each tiles as t, i (i)}
        <button type="button" class="wt" disabled={locked || picked.includes(i)} onclick={() => addTile(i)}>{t}</button>
      {/each}
    </div>
    {#if picked.length > 0 && !locked}
      <button type="button" class="btn btn-sm" style="align-self: center" onclick={clearTiles}><Icon name="repeat" size={15} /> Xếp lại</button>
    {/if}
  {:else if type === 'trans'}
    <label class="lbl" for="q-trans">Bản dịch tiếng Việt của bạn</label>
    <textarea
      id="q-trans"
      class="inp"
      rows="3"
      disabled={locked}
      value={typeof answer === 'string' ? answer : ''}
      oninput={(e) => (answer = e.currentTarget.value)}
      placeholder="Gõ câu dịch vào đây…"
    ></textarea>
    <p class="hint">Chấm theo ý chính, không bắt buộc đúng từng chữ.</p>
  {:else if type === 'tf'}
    <div class="tf">
      <button type="button" class="btn btn-lg" class:apps-on={answer === true} disabled={locked} onclick={() => (answer = true)}>
        <Icon name="check" size={18} /> Đúng
      </button>
      <button type="button" class="btn btn-lg" class:apps-on={answer === false} disabled={locked} onclick={() => (answer = false)}>
        <Icon name="x" size={18} /> Sai
      </button>
    </div>
  {:else if type === 'match'}
    <p class="muted" style="font-size: 13.5px; text-align: center">
      {selLeft === null ? 'Chọn một từ ở cột trái, rồi chọn nghĩa của nó' : 'Giờ chọn nghĩa ở cột bên phải'}
    </p>
    <div class="match">
      <div class="col">
        {#each left as w, i (i)}
          {@const pos = pairs[i]}
          <button
            type="button"
            class="mbox hz"
            lang="zh"
            disabled={locked}
            style="background: {pos !== undefined ? PAIR_COLORS[i % PAIR_COLORS.length] : selLeft === i ? 'var(--bo-soft)' : 'var(--surface)'};
                   border-width: {selLeft === i ? 2 : 1}px"
            onclick={() => clickLeft(i)}
          >
            {#if pos !== undefined}<span class="pnum">{i + 1}</span>{/if}
            {w}
          </button>
        {/each}
      </div>
      <div class="col">
        {#each right as w, j (j)}
          {@const owner = rightOwner(j)}
          <button
            type="button"
            class="mbox"
            disabled={locked}
            style="background: {owner !== null ? PAIR_COLORS[owner % PAIR_COLORS.length] : 'var(--surface)'}"
            onclick={() => clickRight(j)}
          >
            {#if owner !== null}<span class="pnum">{owner + 1}</span>{/if}
            {w}
          </button>
        {/each}
      </div>
    </div>
  {/if}
</div>

<style>
  .q {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .word {
    display: flex;
    gap: 10px;
    justify-content: center;
  }
  .ask {
    font-size: 15px;
    text-align: center;
  }
  .ask-big {
    font-size: 17px;
    font-weight: 600;
    text-align: center;
  }
  .audio {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
  }
  .speak {
    display: grid;
    place-items: center;
    border: 1px solid var(--son);
    border-radius: 50%;
    background: var(--son);
    color: #fff;
    cursor: pointer;
    box-shadow: var(--sh-md);
  }
  .speak:disabled {
    opacity: 0.45;
    cursor: default;
  }
  .sentence {
    text-align: center;
    line-height: 1.6;
  }
  .blank {
    display: inline-block;
    min-width: 110px;
    border-bottom: 3px solid var(--text);
    color: var(--blue);
    text-align: center;
  }
  .passage {
    padding: 14px 16px;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--r);
    font-size: 22px;
    line-height: 1.6;
  }
  .stmt {
    font-size: 24px;
    text-align: center;
  }
  .star {
    color: var(--son);
  }
  .opts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .opts.one {
    grid-template-columns: 1fr;
  }
  .drop {
    min-height: 76px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: var(--surface-2);
    border: 2px dashed var(--line-3);
    border-radius: var(--r);
  }
  .drop.trong {
    justify-content: center;
  }
  .tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
  }
  .tf {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  .match {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .col {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .mbox {
    position: relative;
    min-height: 52px;
    padding: 10px 14px;
    border: 1px solid var(--ink-strong);
    border-radius: var(--r);
    font: inherit;
    font-size: 16px;
    cursor: pointer;
    text-align: center;
  }
  .mbox:disabled {
    cursor: default;
  }
  .pnum {
    position: absolute;
    top: -6px;
    left: -6px;
    width: 20px;
    height: 20px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--text);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
  }

  .q.m .opts {
    grid-template-columns: 1fr;
  }
  .q.m .blank {
    min-width: 72px;
  }
</style>
