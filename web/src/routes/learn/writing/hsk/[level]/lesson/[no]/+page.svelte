<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import { cap } from '$lib/stores/cap.svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import HanziPad from '$lib/learn/HanziPad.svelte';
  import { untrack } from 'svelte';
  import { replaceState } from '$app/navigation';
  import { completePart, lessonByNo, levelProgress, progressOf, saveProgress } from '$lib/learn/lesson';
  import { viTriDaLuu } from '$lib/learn/resume';
  import { speak } from '$lib/learn/speech';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Luyện viết từ mới (07 §12).
  interface Char {
    id: number;
    hanzi: string;
    pinyin: string;
    tone: number;
    hanViet: string;
    meaning: string;
    strokes: number;
    structure: string;
    strokeDataUrl: string | null;
    word: { hanzi: string; meaning: string } | null;
    radicalInfo: { no: number; form: string; root: string; name: string; meaning: string } | null;
    analysis: string;
    radicalExamples: { hanzi: string; pinyin: string; hanViet: string; meaning: string; word: string | null }[];
  }
  interface Config { startMode: 'demo' | 'trace' | 'free'; grid: 'tian' | 'mi'; passCount: number; hintAfterMisses: number; showAnimation: boolean; highlightRadical: boolean }
  interface Data { lesson: LessonRef; config: Config; chars: Char[]; next: NextLesson | null }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<Data | null>(null);
  let failed = $state(false);
  let ci = $state(0);
  let mode = $state<'demo' | 'trace' | 'free'>('trace');
  let passes = $state(0);
  let done = $state(false);
  let pad = $state<ReturnType<typeof HanziPad> | null>(null);

  const chars = $derived(data?.chars ?? []);
  const c = $derived<Char | null>(chars[ci] ?? null);
  const need = $derived(data?.config.passCount ?? 2);

  const CACH = [
    { ma: 'demo', ten: 'Xem mẫu' },
    { ma: 'trace', ten: 'Tô theo' },
    { ma: 'free', ten: 'Tự viết' },
  ] as const;

  // Ô viết trên điện thoại chiếm gần hết bề ngang khung: trừ lề trang 16px, viền 1px và lề
  // trong của .zk-khung-m 14px mỗi bên (16 + 1 + 14 = 31), tối đa 340px.
  let rong = $state(375);
  const oDt = $derived(Math.max(240, Math.min(340, rong - 62)));
  const conChu = $derived(!done && ci + 1 < chars.length);
  let dai = $state<HTMLDivElement | null>(null);

  /** Dải chữ trên điện thoại cuộn ngang: đổi chữ thì kéo chữ đang viết vào giữa cho thấy. */
  $effect(() => {
    void ci;
    dai?.querySelector<HTMLElement>('.on')?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  });

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) { failed = true; return; }
      const res = await api.get<Data>(`/learn/lessons/${l.id}/writing`);
      data = res;
      mode = res.config.startMode;
      done = false;
      // Mở lại đúng chữ đang viết dở. Không gọi pickChar ở đây: nó lưu vị trí ngay, mà lúc này chưa biết
      // vị trí thật nên sẽ ghi đè chữ đã lưu bằng chữ đầu.
      const st = untrack(() => page.state);
      const fromStart = st.fromStart === true;
      if (st.fromStart !== undefined) {
        try { replaceState('', {}); } catch { /* router chưa sẵn sàng thì để nguyên */ }
      }
      const saved = fromStart ? null : await levelProgress(levelId, 'lv').then((p) => progressOf(p, l.id)).catch(() => null);
      ci = viTriDaLuu(saved, res.chars.length, fromStart) ?? 0;
      passes = 0;
      heartbeat.setActivity({ part: 'lv', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  $effect(() => { void levelId; void lessonNo; void load(); });

  function pickChar(k: number): void {
    ci = k;
    passes = 0;
    if (data) void saveProgress(data.lesson.id, 'lv', k, chars.length).catch(() => {});
  }

  /**
   * Tô xong cả chữ một lượt. Trước đây người học tự bấm “Viết đúng”, giờ ô viết tự chấm từng nét
   * nên lượt nào đạt là nó báo về đây — không ai phải tự khai nữa.
   */
  async function markPass(mistakes: number): Promise<void> {
    if (!c || !data) return;
    passes++;
    try {
      const res = await api.post<{ passes: number; charDone: boolean }>('/writing/result', {
        charId: c.id,
        lessonId: data.lesson.id,
        mode: mode === 'free' ? 'free' : 'trace',
        mistakes,
        passed: true,
      });
      if (res.charDone || passes >= need) {
        if (ci + 1 < chars.length) {
          toast.ok(`Xong chữ ${c.hanzi}!`);
          pickChar(ci + 1);
        } else {
          await finish();
        }
      } else {
        toast.ok(`Đúng rồi! Viết lại lượt ${passes + 1}/${need}.`);
        pad?.chayLai();
      }
    } catch {
      toast.show('Chưa lưu được kết quả.');
    }
  }

  async function finish(): Promise<void> {
    if (!data) return;
    try {
      await completePart(data.lesson.id, 'lv');
      done = true;
      toast.ok('Đã viết xong cả bài.');
    } catch {
      toast.show('Chưa lưu được tiến độ.');
    }
  }

  const hint = $derived(
    mode === 'demo'
      ? 'Nét được đánh số theo thứ tự viết. Xem xong thì chuyển sang “Tô theo”.'
      : mode === 'trace'
        ? 'Viết đè lên chữ mờ, mỗi lần một nét. Nét đúng sẽ hiện ra, nét sai thì viết lại.'
        : 'Không có chữ mờ — viết từ trí nhớ, sai nét nào thì bấm “Gợi ý”.',
  );
</script>

<svelte:head><title>{data ? `${data.lesson.title} · Luyện viết` : 'Luyện viết'} · Zìkǎ</title></svelte:head>
<svelte:window bind:innerWidth={rong} />

{#snippet grid(size: number)}
  <div class="tian" class:mi={data?.config.grid === 'mi'} style="width: {size}px; height: {size}px">
    {#if c}
      {#key c.id}
        <HanziPad
          bind:this={pad}
          hanzi={c.hanzi}
          dataUrl={c.strokeDataUrl}
          {size}
          {mode}
          tuChay={data?.config.showAnimation ?? true}
          toBoThu={data?.config.highlightRadical ?? false}
          goiYSauMay={data?.config.hintAfterMisses ?? 3}
          onpass={markPass}
        />
      {/key}
    {/if}
  </div>
{/snippet}

<!-- Dải chữ của bài: điện thoại một dải cuộn ngang tràn sát mép khung để ô viết nằm ngay
     trong màn hình đầu; máy tính xuống dòng vì đủ chỗ. -->
{#snippet daiChu(m: boolean)}
  <div bind:this={dai} class="vt-dai" class:vt-dai-m={m} role="group" aria-label="Chữ trong bài">
    {#each chars as ch, k (ch.id)}
      <button
        type="button"
        class="vt-chu hz"
        lang="zh"
        class:on={k === ci}
        class:vt-qua={k < ci}
        aria-current={k === ci ? 'true' : undefined}
        onclick={() => pickChar(k)}
      >{ch.hanzi}</button>
    {/each}
  </div>
{/snippet}

<!-- Ba cách viết dùng chung kiểu nút chọn với hàng chế độ của Lật thẻ. -->
{#snippet cheDo(m: boolean)}
  <div class="zk-tabs vt-che" class:zk-tabs-deu={m} class:vt-che-m={m} role="tablist" aria-label="Cách viết">
    {#each CACH as k (k.ma)}
      <button type="button" role="tab" class="zk-tab" aria-selected={mode === k.ma} onclick={() => (mode = k.ma)}>{k.ten}</button>
    {/each}
  </div>
{/snippet}

<!-- Nút thao tác trên ô viết. Điện thoại kèm luôn “Bỏ qua” để không phải cuộn xuống cuối khung. -->
{#snippet congCu(m: boolean)}
  <div class="vt-cu" class:vt-cu-m={m}>
    {#if mode === 'demo'}
      <button type="button" class="zk-nut" onclick={() => pad?.chayLai()}><Icon name="play" size={16} /> Chạy lại</button>
    {:else}
      <button type="button" class="zk-nut" onclick={() => pad?.xoa()}><Icon name="repeat" size={16} /> Viết lại</button>
      <button type="button" class="zk-nut" onclick={() => pad?.goiY()}><Icon name="eye" size={16} /> Gợi ý nét</button>
    {/if}
    {#if m && conChu}
      <button type="button" class="zk-nut" aria-label="Bỏ qua chữ này" onclick={() => pickChar(ci + 1)}>Bỏ qua </button>
    {/if}
  </div>
{/snippet}

{#snippet oViet(m: boolean)}
  <div class="vt-trai">
    {@render grid(m ? oDt : 320)}
    {@render cheDo(m)}
    <p class="vt-huong">{hint}</p>
    {@render congCu(m)}
    {#if m}<p class="vt-luot num">Chữ {ci + 1}/{chars.length} · Lượt viết đúng {passes}/{need}</p>{/if}
  </div>
{/snippet}

<!-- Thông tin chữ: một ô nền nhạt trong khung, các ô con (bộ thủ, chữ cùng bộ) nền trắng nổi lên trên. -->
{#snippet thongTin(cc: Char)}
  <div class="zk-o vt-tt">
    <div class="vt-tt-dau">
      <span class="hz vt-tt-chu" lang="zh">{cc.hanzi}</span>
      <div style="flex: 1; min-width: 0">
        <b class="t{cc.tone}" style="font-size: 20px">{cc.pinyin}</b>
        {#if cc.hanViet}<p class="vt-hv">{cc.hanViet}</p>{/if}
      </div>
      <button type="button" class="vt-loa" aria-label="Nghe {cc.hanzi}" onclick={() => speak(cc.hanzi)}><Icon name="speaker" size={18} /></button>
    </div>

    <p style="font-size: 15px">{cc.meaning}</p>
    <hr class="rule" />
    <p class="muted" style="font-size: 13.5px"><b>{cc.strokes} nét</b>{cc.structure ? ` · ${cc.structure}` : ''}</p>
    {#if cc.word}
      <p style="font-size: 14px">Có trong từ: <b class="hz" lang="zh">{cc.word.hanzi}</b> <span class="muted">{cc.word.meaning}</span></p>
    {/if}

    {#if cc.radicalInfo}
      <div class="vt-bo">
        <span class="hz vt-bo-chu" lang="zh">{cc.radicalInfo.form}</span>
        <div style="min-width: 0">
          <p style="font-size: 14px"><b>Bộ {cc.radicalInfo.name}</b>{#if cc.radicalInfo.form !== cc.radicalInfo.root}&nbsp;<span class="hz" lang="zh">({cc.radicalInfo.root})</span>{/if} · <span class="muted">số {cc.radicalInfo.no}</span></p>
          <p class="muted" style="font-size: 13.5px">Nghĩa: {cc.radicalInfo.meaning}</p>
        </div>
      </div>
    {/if}
    {#if cc.analysis}
      <p style="font-size: 14px; line-height: 1.55">{cc.analysis}</p>
    {/if}
    {#if cc.radicalExamples.length > 0 && cc.radicalInfo}
      <div>
        <p class="vt-nhan">Chữ khác cùng bộ {cc.radicalInfo.form}</p>
        <div class="vt-cung">
          {#each cc.radicalExamples as e, ei (ei)}
            <button type="button" class="vt-cung-o" onclick={() => speak(e.hanzi)} aria-label="Nghe {e.hanzi}">
              <span class="hz" lang="zh" style="font-size: 26px; line-height: 1">{e.hanzi}</span>
              <span style="min-width: 0; text-align: left">
                <b style="font-size: 13px">{e.pinyin}</b>{#if e.hanViet}<span class="muted" style="font-size: 11.5px; margin-left: 5px">{e.hanViet}</span>{/if}
                <span class="muted" style="display: block; font-size: 12.5px">{e.meaning}{#if e.word} · <span class="hz" lang="zh">{e.word}</span>{/if}</span>
              </span>
            </button>
          {/each}
        </div>
      </div>
    {/if}
  </div>
{/snippet}

<!-- Hàng nút cuối khung. Điện thoại đã có “Bỏ qua” cạnh ô viết nên chỉ hiện hàng này khi
     tới chữ cuối hoặc xong bài, như trước. -->
{#snippet chan(m: boolean)}
  <div class="zk-chan">
    {#if !m}
      <span class="vt-luot num">{done ? 'Đã viết xong cả bài.' : `Lượt viết đúng: ${passes}/${need}`}</span>
    {/if}
    <div class="vt-chan-nut" class:vt-chan-nut-m={m}>
      {#if done && data}
        {#if data.next}
          <a href="/learn/writing/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son btn-lg">Bài tiếp: {data.next.title}</a>
        {:else}
          <a href="/learn/writing/hsk/{levelId}" class="btn btn-son btn-lg">Về danh sách bài</a>
        {/if}
      {:else if ci + 1 < chars.length}
        <button type="button" class="zk-nut" onclick={() => pickChar(ci + 1)}>Bỏ qua chữ này </button>
      {:else}
        <button type="button" class="zk-nut zk-nut-chinh" onclick={finish}>Đánh dấu xong bài</button>
      {/if}
    </div>
  </div>
{/snippet}

<!-- Một khung chứa trọn màn viết (khuôn chung với Lật thẻ): dải chữ, ô viết, thông tin chữ và
     hàng nút cuối, không còn mảnh rời trôi trên nền xám. -->
{#snippet khung(m: boolean)}
  {#if c}
    <section class="block zk-khung" class:zk-khung-m={m}>
      {#if !m}
        <div class="zk-khung-dau">
          <h2 class="zk-khung-ten">Chữ trong bài</h2>
          <span class="zk-so">Chữ {ci + 1}/{chars.length}</span>
        </div>
      {/if}
      {@render daiChu(m)}
      <div class="vt-than" class:vt-than-m={m}>
        {@render oViet(m)}
        {@render thongTin(c)}
      </div>
      {#if !m || !conChu}{@render chan(m)}{/if}
    </section>
  {/if}
{/snippet}

{#snippet hong()}
  <EmptyState title="Bài này chưa có chữ để luyện viết.">
    {#snippet action()}<a href="/learn/writing/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
  </EmptyState>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <MTopbar current="lv" back="/learn/writing/hsk/{levelId}" backLabel="Về danh sách bài" title={data?.lesson.title ?? 'Luyện viết'} sub="Luyện viết · {cap.ten(levelId)} · Bài {lessonNo}" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">
      {#if failed}
        {@render hong()}
      {:else if data === null}
        <Skeleton h="420px" r={22} />
      {:else}
        {@render khung(true)}
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="lv">
    <!-- Chỉ ghi phần và cấp: số bài và tên bài đã ở khối tiêu đề ngay bên dưới, ghi lại ở đây là lặp. -->
    <nav class="crumb" aria-label="Đường dẫn">
    <a href="/learn/writing">Luyện viết</a> / <a href="/learn/writing/hsk/{levelId}">{cap.ten(levelId)}</a>
    </nav>
  </Topbar>
  <main class="page zk-cot-rong" style="display: flex; flex-direction: column; gap: 18px">
    {#if failed}
      {@render hong()}
    {:else if data === null}
      <Skeleton h="520px" r={22} />
    {:else}
      <DauBai href="/learn/writing/hsk/{levelId}" kicker="{cap.ten(levelId)} · Bài {lessonNo} · {chars.length} chữ" title={data.lesson.title} />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  /* Dải chữ của bài. */
  .vt-dai {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  /* Điện thoại: cuộn ngang, tràn sát mép trong của khung (lề .zk-khung-m là 14px). */
  .vt-dai-m {
    flex-wrap: nowrap;
    overflow-x: auto;
    margin: 0 -14px;
    padding: 2px 14px 6px;
    scroll-snap-type: x proximity;
    scrollbar-width: none;
  }
  .vt-dai-m::-webkit-scrollbar {
    display: none;
  }
  .vt-chu {
    flex: none;
    min-width: 44px;
    height: 44px;
    padding: 0 8px;
    border: 1px solid var(--ink-strong);
    border-radius: var(--r-sm);
    background: var(--surface);
    color: var(--text);
    font-size: 20px;
    cursor: pointer;
    scroll-snap-align: center;
  }
  .vt-chu:hover {
    border-color: var(--line-3);
  }
  .vt-chu:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  /* Chữ đã qua tô ngọc nhạt; viền pha từ biến màu để giao diện tối không bị ô sáng chói. */
  .vt-chu.vt-qua {
    background: var(--ngoc-soft);
    border-color: color-mix(in srgb, var(--ngoc) 35%, var(--line));
  }
  .vt-chu.on {
    border-color: var(--son);
    background: var(--son-soft);
    color: var(--son-text);
  }

  /* Ô viết bên trái, thông tin chữ bên phải; hẹp quá thì thông tin xuống dưới. */
  .vt-than {
    display: flex;
    flex-wrap: wrap;
    gap: 24px 28px;
    align-items: flex-start;
  }
  .vt-than-m {
    flex-direction: column;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 16px;
  }
  .vt-trai {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
  }
  .vt-huong {
    max-width: 300px;
    font-size: 13px;
    text-align: center;
    color: var(--text-2);
  }
  .vt-cu {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
  }
  /* Điện thoại: các nút chia đều bề ngang ô viết, lề trong hẹp lại để “Gợi ý nét” không xuống dòng. */
  .vt-cu-m {
    flex-wrap: nowrap;
    width: 100%;
  }
  .vt-cu-m .zk-nut {
    flex: 1 1 0;
    min-width: 0;
    gap: 6px;
    padding: 0 6px;
    font-size: 13.5px;
    white-space: nowrap;
  }
  /* Nút chọn cách viết: điện thoại cao 44px cho ngón tay; chỉ có 3 nút nên chữ to hơn mặc định của .zk-tabs-deu. */
  .vt-che-m {
    width: 100%;
  }
  .vt-che-m .zk-tab {
    height: 44px;
    font-size: 14px;
  }
  .vt-luot {
    font-size: 13px;
    color: var(--text-2);
  }

  /* Thông tin chữ. */
  .vt-tt {
    flex: 1 1 300px;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px 18px;
  }
  .vt-than-m .vt-tt {
    flex: none;
    padding: 14px;
  }
  .vt-tt-dau {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .vt-tt-chu {
    font-size: 40px;
    line-height: 1.1;
  }
  .vt-hv {
    font-size: 12.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-2);
  }
  .vt-loa {
    flex: none;
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
    transition: color 0.15s ease;
  }
  .vt-loa:hover,
  .vt-loa:active {
    color: var(--son-text);
  }
  .vt-loa:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  .vt-bo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--surface);
  }
  .vt-bo-chu {
    display: grid;
    place-items: center;
    flex: none;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--troi);
    font-size: 28px;
    color: var(--blue-text);
  }
  .vt-nhan {
    margin-bottom: 6px;
    font-size: 12.5px;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--text-2);
  }
  .vt-cung {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(150px, 100%), 1fr));
    gap: 8px;
  }
  .vt-cung-o {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 52px;
    padding: 8px 10px;
    border: 1px solid var(--line);
    border-radius: var(--r-sm);
    background: var(--surface);
    color: var(--text);
    font: inherit;
    cursor: pointer;
  }
  .vt-cung-o:hover {
    border-color: var(--line-3);
  }
  .vt-cung-o:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }

  /* Hàng nút cuối khung: nút nằm bên phải; điện thoại kéo hết bề ngang. */
  .vt-chan-nut {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-left: auto;
  }
  .vt-chan-nut-m {
    width: 100%;
    margin-left: 0;
  }
  .vt-chan-nut-m > :global(*) {
    flex: 1 1 auto;
  }

  .tian.mi {
    background-image:
      linear-gradient(to right, transparent calc(50% - 0.5px), var(--son-line) calc(50% - 0.5px), var(--son-line) calc(50% + 0.5px), transparent calc(50% + 0.5px)),
      linear-gradient(to bottom, transparent calc(50% - 0.5px), var(--son-line) calc(50% - 0.5px), var(--son-line) calc(50% + 0.5px), transparent calc(50% + 0.5px)),
      linear-gradient(to bottom right, transparent calc(50% - 0.4px), var(--son-line) calc(50% - 0.4px), var(--son-line) calc(50% + 0.4px), transparent calc(50% + 0.4px)),
      linear-gradient(to bottom left, transparent calc(50% - 0.4px), var(--son-line) calc(50% - 0.4px), var(--son-line) calc(50% + 0.4px), transparent calc(50% + 0.4px));
  }
</style>
