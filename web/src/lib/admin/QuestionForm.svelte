<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import type { Draft } from '$lib/admin/api';

  // Biểu mẫu soạn một câu hỏi, dùng chung cho Bài tập (8 dạng) và Kiểm tra (10 dạng) — 06 §2.
  // Component chỉ giữ dữ liệu; việc lưu do trang gọi nó đảm nhiệm.

  let {
    draft = $bindable(),
    types,
    errors = {},
    onchange,
  }: {
    draft: Draft;
    /** Danh sách dạng câu cho phép: [mã, tên hiển thị] */
    types: [string, string][];
    errors?: Record<string, string>;
    onchange?: () => void;
  } = $props();

  const CHOICE = ['meaning', 'pinyin', 'tone', 'audio', 'listen', 'blank', 'fill', 'pick', 'toZh'];
  const isChoice = $derived(CHOICE.includes(draft.type));
  const isWord = $derived(['meaning', 'pinyin', 'tone'].includes(draft.type));
  const isAudio = $derived(['audio', 'listen'].includes(draft.type));
  const isBlank = $derived(['blank', 'fill'].includes(draft.type));
  /** Đáp án là chữ Hán → ô nhập dùng phông chữ Hán. */
  const hzOptions = $derived(['audio', 'blank', 'fill', 'pick', 'toZh'].includes(draft.type));

  function touch(): void {
    onchange?.();
  }

  /** Đổi dạng câu: giữ lại các trường dùng chung, dựng khung trống cho trường mới. */
  function setType(t: string): void {
    if (t === draft.type) return;
    const p = draft.payload;
    const keep: Record<string, any> = {
      word: p.word ?? '',
      ask: p.ask ?? '',
      vi: p.vi ?? '',
      sentence: p.sentence ?? '',
      audioText: p.audioText ?? '',
      options: Array.isArray(p.options) && p.options.length ? p.options : ['', '', '', ''],
      answer: typeof p.answer === 'number' ? p.answer : 0,
      tiles: p.tiles ?? [],
      zh: p.zh ?? '',
      pinyin: p.pinyin ?? '',
      sample: p.sample ?? '',
      keywords: p.keywords ?? [],
      passage: p.passage ?? '',
      statement: p.statement ?? '',
      pairs: p.pairs ?? [['', ''], ['', ''], ['', ''], ['', '']],
      showMeaning: p.showMeaning ?? true,
    };
    if (t === 'tf') keep.answer = typeof p.answer === 'boolean' ? p.answer : false;
    if (t === 'order') keep.answer = typeof p.answer === 'string' ? p.answer : '';
    draft.type = t;
    draft.payload = keep;
    touch();
  }

  /* ---------------- Các dạng có danh sách đáp án ---------------- */

  function setOption(i: number, v: string): void {
    const opts = [...(draft.payload.options ?? ['', '', '', ''])];
    opts[i] = v;
    draft.payload.options = opts;
    touch();
  }

  /* ---------------- order: ô chữ ---------------- */

  /** Tự tách câu đúng thành các ô chữ khi admin chưa tự sửa. */
  function autoTiles(): void {
    const text = String(draft.payload.answer ?? '').replace(/[，。！？、；：""''（）《》,.!?;:'"()\-\s]/g, '');
    if (text === '') return;
    // Tách thô theo từng chữ; admin gộp lại bằng nút bên dưới.
    draft.payload.tiles = Array.from(text);
    touch();
  }

  function mergeTile(i: number): void {
    const t = [...(draft.payload.tiles ?? [])];
    if (i >= t.length - 1) return;
    t.splice(i, 2, t[i] + t[i + 1]);
    draft.payload.tiles = t;
    touch();
  }

  function splitTile(i: number): void {
    const t = [...(draft.payload.tiles ?? [])];
    const s = String(t[i] ?? '');
    if (s.length < 2) return;
    const half = Math.ceil(s.length / 2);
    t.splice(i, 1, s.slice(0, half), s.slice(half));
    draft.payload.tiles = t;
    touch();
  }

  /* ---------------- trans: từ khoá ---------------- */

  let kwInput = $state('');
  function addKeyword(): void {
    const v = kwInput.trim();
    if (v === '') return;
    draft.payload.keywords = [...(draft.payload.keywords ?? []), v];
    kwInput = '';
    touch();
  }
  function removeKeyword(i: number): void {
    draft.payload.keywords = (draft.payload.keywords ?? []).filter((_: string, k: number) => k !== i);
    touch();
  }

  /* ---------------- match: cặp từ – nghĩa ---------------- */

  function setPair(i: number, col: 0 | 1, v: string): void {
    const pairs = (draft.payload.pairs ?? []).map((p: string[]) => [...p]);
    pairs[i][col] = v;
    draft.payload.pairs = pairs;
    touch();
  }
  function addPair(): void {
    if ((draft.payload.pairs ?? []).length >= 6) return;
    draft.payload.pairs = [...(draft.payload.pairs ?? []), ['', '']];
    touch();
  }
  function removePair(i: number): void {
    draft.payload.pairs = (draft.payload.pairs ?? []).filter((_: unknown, k: number) => k !== i);
    touch();
  }

  /** Đọc thử nội dung bằng giọng của trình duyệt (câu nghe). */
  function speak(text: string): void {
    if (typeof speechSynthesis === 'undefined' || text.trim() === '') return;
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'zh-CN';
    speechSynthesis.cancel();
    speechSynthesis.speak(u);
  }
</script>

<!-- Dạng câu -->
<div class="field">
  <span class="lbl">Dạng câu</span>
  <div class="chips">
    {#each types as [code, label] (code)}
      <button type="button" class="btn btn-sm {draft.type === code ? 'btn-muc' : 'btn-paper'}" onclick={() => setType(code)}>{label}</button>
    {/each}
  </div>
</div>

<!-- Trường riêng theo dạng -->
{#if isWord}
  <div class="grid2">
    <div class="field">
      <label class="lbl" for="q-word">Chữ / từ hỏi</label>
      <input id="q-word" class="inp hz" lang="zh" style="font-size: 24px; height: 54px" bind:value={draft.payload.word} oninput={touch} />
      {#if errors.word}<p class="err">{errors.word}</p>{/if}
    </div>
    <div class="field">
      <label class="lbl" for="q-ask">Câu hỏi hiện cho người học</label>
      <input id="q-ask" class="inp" bind:value={draft.payload.ask} oninput={touch} placeholder="Từ này nghĩa là gì?" />
    </div>
  </div>
{/if}

{#if isAudio}
  <div class="field">
    <label class="lbl" for="q-audio">Nội dung đọc</label>
    <div class="row">
      <input id="q-audio" class="inp hz" lang="zh" style="font-size: 20px" bind:value={draft.payload.audioText} oninput={touch} />
      <button type="button" class="btn btn-sm btn-paper" onclick={() => speak(draft.payload.audioText ?? '')}>
        <Icon name="play" size={15} /> Nghe thử
      </button>
    </div>
    <p class="hint">Người học không thấy chữ này, chỉ nghe.</p>
    {#if errors.audioText}<p class="err">{errors.audioText}</p>{/if}
  </div>
{/if}

{#if isBlank}
  <div class="field">
    <label class="lbl" for="q-sent">Câu có chỗ trống</label>
    <input id="q-sent" class="inp hz" lang="zh" style="font-size: 22px" bind:value={draft.payload.sentence} oninput={touch} placeholder="我每天喝一杯___。" />
    <p class="hint">Dùng <b>___</b> (ba gạch dưới) để đánh dấu chỗ trống.</p>
    {#if errors.sentence}<p class="err">{errors.sentence}</p>{/if}
  </div>
  <div class="field">
    <label class="lbl" for="q-blank-vi">Nghĩa tiếng Việt của câu</label>
    <input id="q-blank-vi" class="inp" bind:value={draft.payload.vi} oninput={touch} placeholder="Mỗi ngày tôi uống một cốc ___." />
    <label class="chk" style="margin-top: 4px">
      <input type="checkbox" bind:checked={draft.payload.showMeaning} onchange={touch} />
      Hiện nghĩa cho người học
    </label>
  </div>
{/if}

{#if draft.type === 'pick'}
  <div class="field">
    <label class="lbl" for="q-pick">Đề bài</label>
    <input id="q-pick" class="inp" bind:value={draft.payload.ask} oninput={touch} placeholder="Câu nào viết đúng?" />
    {#if errors.ask}<p class="err">{errors.ask}</p>{/if}
  </div>
{/if}

{#if draft.type === 'toZh'}
  <div class="field">
    <label class="lbl" for="q-tozh">Câu tiếng Việt</label>
    <input id="q-tozh" class="inp" bind:value={draft.payload.vi} oninput={touch} placeholder="Tôi muốn uống một cốc cà phê." />
    {#if errors.vi}<p class="err">{errors.vi}</p>{/if}
  </div>
{/if}

{#if isChoice}
  <div class="field">
    <span class="lbl">Đáp án · chọn ô tròn ở đáp án đúng</span>
    {#each draft.payload.options ?? ['', '', '', ''] as opt, i (i)}
      <div class="opt-row">
        <input
          type="radio"
          name="q-answer"
          checked={draft.payload.answer === i}
          onchange={() => { draft.payload.answer = i; touch(); }}
          aria-label="Đáp án {String.fromCharCode(65 + i)} là đáp án đúng"
        />
        <span class="key">{String.fromCharCode(65 + i)}</span>
        <input
          class="inp {hzOptions ? 'hz' : ''}"
          lang={hzOptions ? 'zh' : 'vi'}
          value={opt}
          oninput={(e) => setOption(i, e.currentTarget.value)}
        />
      </div>
    {/each}
    {#if errors.options}<p class="err">{errors.options}</p>{/if}
    {#if errors.answer}<p class="err">{errors.answer}</p>{/if}
  </div>
{/if}

{#if draft.type === 'order'}
  <div class="field">
    <label class="lbl" for="q-order">Câu đúng</label>
    <div class="row">
      <input id="q-order" class="inp hz" lang="zh" style="font-size: 22px" bind:value={draft.payload.answer} oninput={touch} placeholder="我想吃面条" />
      <button type="button" class="btn btn-sm btn-paper" onclick={autoTiles}>Tự tách ô</button>
    </div>
    {#if errors.answer}<p class="err">{errors.answer}</p>{/if}
  </div>
  <div class="field">
    <span class="lbl">Các ô chữ người học sẽ xếp · bấm để gộp hoặc tách</span>
    <div class="tiles">
      {#each draft.payload.tiles ?? [] as t, i (i)}
        <span class="tile-wrap">
          <button type="button" class="wt" onclick={() => splitTile(i)} title="Bấm để tách đôi ô này">{t}</button>
          {#if i < (draft.payload.tiles ?? []).length - 1}
            <button type="button" class="merge" onclick={() => mergeTile(i)} aria-label="Gộp hai ô">+</button>
          {/if}
        </span>
      {/each}
      {#if (draft.payload.tiles ?? []).length === 0}
        <span class="muted" style="font-size: 13px">Nhập câu đúng rồi bấm “Tự tách ô”.</span>
      {/if}
    </div>
    {#if errors.tiles}<p class="err">{errors.tiles}</p>{/if}
  </div>
  <div class="field">
    <label class="lbl" for="q-order-vi">Nghĩa tiếng Việt</label>
    <input id="q-order-vi" class="inp" bind:value={draft.payload.vi} oninput={touch} placeholder="Tôi muốn ăn mì." />
  </div>
{/if}

{#if draft.type === 'trans'}
  <div class="field">
    <label class="lbl" for="q-zh">Câu tiếng Trung</label>
    <input id="q-zh" class="inp hz" lang="zh" style="font-size: 22px" bind:value={draft.payload.zh} oninput={touch} />
    {#if errors.zh}<p class="err">{errors.zh}</p>{/if}
  </div>
  <div class="field">
    <label class="lbl" for="q-py">Pinyin</label>
    <input id="q-py" class="inp" bind:value={draft.payload.pinyin} oninput={touch} placeholder="Wǒ yào yángròu hé mǐfàn." />
  </div>
  <div class="field">
    <label class="lbl" for="q-sample">Bản dịch mẫu</label>
    <textarea id="q-sample" class="inp" rows="2" bind:value={draft.payload.sample} oninput={touch}></textarea>
  </div>
  <div class="field">
    <span class="lbl">Từ khoá để chấm điểm · bài dịch có đủ ý này là đúng</span>
    <div class="kw-list">
      {#each draft.payload.keywords ?? [] as k, i (i)}
        <span class="kw">{k}<button type="button" onclick={() => removeKeyword(i)} aria-label="Xoá từ khoá {k}"><Icon name="x" size={12} /></button></span>
      {/each}
    </div>
    <input
      class="inp"
      bind:value={kwInput}
      placeholder="Gõ rồi bấm Enter. Nhiều cách viết thì ngăn bằng dấu |"
      onkeydown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addKeyword(); } }}
    />
    {#if errors.keywords}<p class="err">{errors.keywords}</p>{/if}
  </div>
{/if}

{#if draft.type === 'tf'}
  <div class="field">
    <label class="lbl" for="q-passage">Đoạn văn</label>
    <textarea id="q-passage" class="inp hz" lang="zh" rows="2" style="font-size: 20px" bind:value={draft.payload.passage} oninput={touch}></textarea>
    {#if errors.passage}<p class="err">{errors.passage}</p>{/if}
  </div>
  <div class="field">
    <label class="lbl" for="q-stmt">Câu cần xét (★)</label>
    <input id="q-stmt" class="inp hz" lang="zh" style="font-size: 20px" bind:value={draft.payload.statement} oninput={touch} />
    {#if errors.statement}<p class="err">{errors.statement}</p>{/if}
  </div>
  <div class="field">
    <span class="lbl">Đáp án</span>
    <div class="seg">
      <button type="button" class:on={draft.payload.answer === true} onclick={() => { draft.payload.answer = true; touch(); }}>Đúng</button>
      <button type="button" class:on={draft.payload.answer !== true} onclick={() => { draft.payload.answer = false; touch(); }}>Sai</button>
    </div>
  </div>
{/if}

{#if draft.type === 'match'}
  <div class="field">
    <span class="lbl">Các cặp từ – nghĩa · cột nghĩa sẽ tự xáo trộn khi người học làm</span>
    {#each draft.payload.pairs ?? [] as p, i (i)}
      <div class="pair-row">
        <span class="key">{i + 1}</span>
        <input class="inp hz" lang="zh" value={p[0]} oninput={(e) => setPair(i, 0, e.currentTarget.value)} />
        <span class="muted">—</span>
        <input class="inp" value={p[1]} oninput={(e) => setPair(i, 1, e.currentTarget.value)} />
        <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá cặp {i + 1}" onclick={() => removePair(i)}><Icon name="trash" size={16} /></button>
      </div>
    {/each}
    {#if (draft.payload.pairs ?? []).length < 6}
      <button type="button" class="btn btn-sm btn-paper" onclick={addPair}><Icon name="plus" size={15} /> Thêm cặp</button>
    {/if}
    {#if errors.pairs}<p class="err">{errors.pairs}</p>{/if}
  </div>
{/if}

<!-- Giải thích + điểm -->
<div class="field">
  <label class="lbl" for="q-explain">Giải thích · hiện sau khi người học trả lời</label>
  <textarea id="q-explain" class="inp" rows="2" bind:value={draft.explain} oninput={touch} placeholder="Vì sao đáp án này đúng…"></textarea>
</div>

<style>
  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
    gap: 14px;
  }
  .chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .row {
    display: flex;
    gap: 8px;
    align-items: center;
  }
  .row .inp {
    flex: 1;
  }
  .opt-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .opt-row input[type='radio'] {
    width: 20px;
    height: 20px;
    flex: none;
    accent-color: var(--son);
    margin: 0;
  }
  .key {
    width: 30px;
    height: 30px;
    flex: none;
    display: grid;
    place-items: center;
    border-radius: 8px;
    border: 1px solid var(--ink-strong);
    background: var(--surface-2);
    font-weight: 700;
    font-size: 14px;
    color: var(--text-2);
  }
  .pair-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .pair-row .inp {
    flex: 1;
    min-width: 0;
  }
  .tiles {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
  }
  .tile-wrap {
    display: inline-flex;
    align-items: center;
    gap: 2px;
  }
  .merge {
    width: 18px;
    height: 24px;
    border: 0;
    background: none;
    color: var(--text-3);
    cursor: pointer;
    font-weight: 700;
  }
  .merge:hover {
    color: var(--son);
  }
  .kw-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .kw {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--ngoc-soft);
    color: var(--green-text);
    font-size: 13px;
    font-weight: 600;
  }
  .kw button {
    border: 0;
    background: none;
    padding: 0;
    cursor: pointer;
    color: inherit;
    display: grid;
    place-items: center;
  }
</style>
