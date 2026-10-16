<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import type { FlashcardsData, Word } from '$lib/api/types';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
  import { completePart, lessonByNo, saveProgress, seededShuffle } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';

  // Lật thẻ — màn học chính (07 §5.1).
  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<FlashcardsData | null>(null);
  let failed = $state(false);
  let order = $state<number[]>([]);   // chỉ số thẻ theo thứ tự học
  let i = $state(0);                  // đang ở thẻ thứ mấy
  let flipped = $state(false);
  let finished = $state(false);
  let huong = $state(1); // 1 = sang thẻ sau, -1 = lùi lại — thẻ mới trượt vào từ phía tương ứng
  let swipeX = 0;
  let swipeY = 0;
  let swipePointer: number | null = null;
  let ignoreCardClick = false;

  const cards = $derived(data?.words ?? []);
  const total = $derived(order.length);
  const card = $derived<Word | null>(cards[order[i]] ?? null);
  const pct = $derived(total > 0 ? Math.round((i / total) * 100) : 0);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (l === null) {
        failed = true;
        return;
      }
      const d = await api.get<FlashcardsData>(`/learn/lessons/${l.id}/flashcards`);
      data = d;

      const idx = d.words.map((_, k) => k);
      order = settings.value.shuffle ? seededShuffle(idx, l.id) : idx;
      i = 0;
      flipped = false;
      finished = d.words.length === 0;
      heartbeat.setActivity({ part: 'lt', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);

  // Đổi bài trên URL → tải lại từ đầu.
  $effect(() => {
    void levelId;
    void lessonNo;
    void load();
  });

  function flip(): void {
    flipped = !flipped;
    if (flipped && settings.value.autoSpeak && card) void speak(card.hanzi);
  }

  function cardClick(): void {
    if (ignoreCardClick) {
      ignoreCardClick = false;
      return;
    }
    flip();
  }

  function swipeStart(e: PointerEvent): void {
    if (e.pointerType === 'mouse') return;
    swipePointer = e.pointerId;
    swipeX = e.clientX;
    swipeY = e.clientY;
  }

  function swipeEnd(e: PointerEvent): void {
    if (swipePointer !== e.pointerId) return;
    const dx = e.clientX - swipeX;
    const dy = e.clientY - swipeY;
    swipePointer = null;
    if (Math.abs(dx) < 56 || Math.abs(dx) < Math.abs(dy) * 1.25) return;
    ignoreCardClick = true;
    if (dx < 0) void next();
    else prev();
  }

  function swipeCancel(): void {
    swipePointer = null;
  }

  /** Sang thẻ sau; hết thẻ thì vào màn tổng kết và ghi “đã học xong”. */
  async function next(): Promise<void> {
    if (!data || !card) return;

    const at = i + 1;
    flipped = false;
    huong = 1;

    if (at >= total) {
      finished = true;
      try {
        await completePart(data.lesson.id, 'lt');
      } catch {
        toast.show('Đã học xong nhưng chưa lưu được tiến độ.');
      }
      return;
    }

    i = at;
    // Lưu vị trí để lần sau mở lại đúng chỗ; lỗi mạng thì bỏ qua, không chặn người học.
    void saveProgress(data.lesson.id, 'lt', at, total, { order }).catch(() => {});
  }

  function prev(): void {
    if (i > 0) {
      huong = -1;
      i--;
      flipped = false;
    }
  }

  function restart(): void {
    i = 0;
    flipped = false;
    finished = false;
  }

  // Phím tắt vẫn còn cho người quen dùng bàn phím, chỉ không bày gợi ý trên màn nữa.
  function onKey(e: KeyboardEvent): void {
    if (finished || !card) return;
    const tag = (e.target as HTMLElement | null)?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); flip(); }
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'ArrowRight') void next();
  }

  const frontText = $derived.by(() => {
    if (!card) return '';
    return settings.value.cardFront === 'hanzi' ? card.hanzi : settings.value.cardFront === 'pinyin' ? card.pinyin : card.meaning;
  });
  const frontLabel = $derived(
    settings.value.cardFront === 'hanzi' ? 'Chữ Hán' : settings.value.cardFront === 'pinyin' ? 'Pinyin' : 'Nghĩa tiếng Việt',
  );
</script>

<svelte:head><title>{data ? `${data.lesson.title} · Lật thẻ` : 'Lật thẻ'} · Zìkǎ</title></svelte:head>
<svelte:window onkeydown={onKey} />

{#snippet pinyinRow(py: string, size: number)}
  <span class="num" style="font-size: {size}px; font-weight: 600" class:no-tone={!settings.value.toneColors}>
    {#each toneParts(py) as p (p.text + p.tone)}<span class="t{p.tone}">{p.text}</span>{/each}
  </span>
{/snippet}

{#snippet cardFace(m: boolean)}
  {#if card}
    <!-- Khoá theo chỉ số thẻ: mỗi thẻ là một phần tử mới nên animation “trượt vào” chạy lại từ đầu. -->
    {#key i}
      <div class="deckwrap the vao" style="height: {m ? 300 : 380}px; --huong: {huong}">
        <span class="stk stk-a"></span>
        <span class="stk stk-b"></span>
        <!-- Nút nghe nằm ngoài <button> lật (không lồng nút được) nên đặt tuyệt đối đè lên góc thẻ.
             Chỉ hiện khi mặt đang xem là chữ Hán — nghe pinyin hay nghĩa tiếng Việt thì vô nghĩa. -->
        {#if flipped || settings.value.cardFront === 'hanzi'}
          <button type="button" class="loa" aria-label="Nghe lại {card.hanzi}" onclick={() => speak(card.hanzi)}>
            <Icon name="speaker" size={m ? 17 : 19} />
          </button>
        {/if}
        <button type="button" class={flipped ? 'flip is-back' : 'flip'} onclick={cardClick} aria-label={flipped ? 'Bấm để xem lại mặt trước' : 'Lật thẻ'} onpointerdown={m ? swipeStart : undefined} onpointerup={m ? swipeEnd : undefined} onpointercancel={m ? swipeCancel : undefined}>
          <span class="flip-inner">
            <span class="face face-front">
              <span class="kicker">{frontLabel}</span>
              <span class={settings.value.cardFront === 'hanzi' ? 'hz' : ''} lang={settings.value.cardFront === 'hanzi' ? 'zh' : 'vi'}
                    style="font-size: {settings.value.cardFront === 'hanzi' ? (m ? 56 : 80) : (m ? 28 : 38)}px; font-weight: 800; line-height: 1.15">
                {frontText}
              </span>
              <span class="goi">{m ? '' : 'Bấm để lật'}</span>
            </span>
            <span class="face face-back">
              {@render pinyinRow(card.pinyin, m ? 18 : 22)}
              <span class="hz" lang="zh" style="font-size: {m ? 56 : 80}px; line-height: 1.1">{card.hanzi}</span>
              {#if settings.value.showHanViet && card.hanViet}
                <span class="muted" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em">{card.hanViet}</span>
              {/if}
              <span style="font-size: {m ? 17 : 20}px; font-weight: 600">{card.meaning}</span>
            </span>
          </span>
        </button>
      </div>
    {/key}
  {/if}
{/snippet}

<!-- Điện thoại ưu tiên vuốt; hai nút nhỏ là điều hướng dự phòng. -->
{#snippet controls()}
  <div class="ctrl">
    <button type="button" class="tron" aria-label="Từ trước" disabled={i === 0} onclick={prev}><Icon name="left" size={20} /></button>
    <span class="muted" style="font-size: 12.5px">Vuốt để chuyển thẻ</span>
    <button type="button" class="tron" aria-label="Từ sau" onclick={next}><Icon name="right" size={20} /></button>
  </div>
{/snippet}

<!-- Chỉ hiện sau khi lật: trước đó nó là đáp án lộ sẵn, mà hiện ra đúng lúc cũng làm màn học sinh động hơn. -->
{#snippet examples()}
  {#if card && flipped && settings.value.showExamples && card.examples.length > 0}
    <section class="block vidu" style="padding: 18px 20px; display: flex; flex-direction: column; gap: 14px">
      <p class="kicker">Câu ví dụ</p>
      {#each card.examples as ex (ex.zh)}
        <div style="display: flex; flex-direction: column; gap: 4px">
          <div style="display: flex; align-items: flex-start; gap: 8px">
            <span class="hz" lang="zh" style="font-size: 19px; flex: 1">
              {#if ex.highlight && ex.zh.includes(ex.highlight)}
                {@const parts = ex.zh.split(ex.highlight)}
                {parts[0]}<b class="hl">{ex.highlight}</b>{parts.slice(1).join(ex.highlight)}
              {:else}{ex.zh}{/if}
            </span>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Nghe câu" onclick={() => speak(ex.zh)}><Icon name="speaker" size={17} /></button>
          </div>
          {#if ex.pinyin}<span class="muted" style="font-size: 13.5px">{ex.pinyin}</span>{/if}
          {#if ex.vi}<span style="font-size: 14px">{ex.vi}</span>{/if}
        </div>
      {/each}
    </section>
  {/if}
{/snippet}

{#snippet done(m: boolean)}
  <section class="block" style="padding: {m ? 22 : 32}px; display: flex; flex-direction: column; align-items: center; gap: 16px; text-align: center">
    <span class="stamp" style="font-size: {m ? 26 : 34}px">完成</span>
    <h1 style="font-size: {m ? 24 : 30}px">Xong bài {data?.lesson.no}!</h1>
    <p class="muted">Bạn đã lật hết {total} thẻ của bài “{data?.lesson.title}”.</p>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center">
      <button type="button" class="btn" onclick={restart}><Icon name="repeat" size={16} /> Lật lại</button>
      {#if data?.next}
        <a href="/learn/flashcards/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son">Bài tiếp: {data.next.title}</a>
      {:else}
        <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
      {/if}
    </div>
  </section>
{/snippet}

{#if failed}
  <main class="page">
    <EmptyState title="Không mở được bài này." desc="Bài có thể đang ẩn hoặc đã bị xoá.">
      {#snippet action()}<a href="/learn/flashcards/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  </main>
{:else if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <span style="flex: 1; min-width: 0; text-align: center">
        <b style="display: block; font-size: 15px">{data?.lesson.title ?? '…'}</b>
        <span class="muted num" style="font-size: 12px">Thẻ {Math.min(i + 1, total)} / {total}</span>
      </span>
      <Avatar size={36} />
    </header>
    <div style="padding: 0 16px"><Bar value={i} {total} /></div>

    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">
      {#if data === null}
        <Skeleton h="300px" r={20} />
      {:else if finished}
        {@render done(true)}
      {:else}
        <div class="swipe-zone">
          {@render cardFace(true)}
        </div>
        {@render examples()}
      {/if}
    </main>

    {#if data !== null && !finished}
      <div class="msheet">{@render controls()}</div>
    {/if}
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px">
      <a href="/learn/flashcards/hsk/{levelId}">HSK {levelId}</a> / <b>Bài {lessonNo}{data ? ` · ${data.lesson.title}` : ''}</b>
    </nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px">
      <Switcher current="lt" />
      <Avatar size={44} />
    </div>
  </header>

  <main class="page" style="display: flex; flex-direction: column; gap: 20px">
    {#if data === null}
      <Skeleton h="420px" r={22} />
    {:else if finished}
      {@render done(false)}
    {:else}
      <!-- Một cột, mọi thứ xoay quanh thẻ: chỉ còn đường về bài và tiến độ ở hàng trên. -->
      <div class="hud">
        <BackLink href="/learn/flashcards/hsk/{levelId}" label="Chọn bài" />
        <span class="num" style="font-weight: 700; white-space: nowrap">Thẻ {Math.min(i + 1, total)} / {total}</span>
        <span style="flex: 1 1 160px; min-width: 120px"><Bar value={i} {total} /></span>
        <span class="muted num" style="font-size: 13px">{pct}%</span>
      </div>

      <div class="deck">
        <!-- Máy tính: nút chuyển từ nằm hai bên thẻ, tay không phải rời khỏi vùng nhìn. -->
        <div class="sanh">
          <button type="button" class="tron" aria-label="Từ trước" disabled={i === 0} onclick={prev}><Icon name="left" size={22} /></button>
          <div class="o-the">{@render cardFace(false)}</div>
          <button type="button" class="tron" aria-label="Từ sau" onclick={next}><Icon name="right" size={22} /></button>
        </div>
        <!-- Thụt vào đúng bề ngang hai nút tròn để khối ví dụ thẳng mép với thẻ. -->
        <div class="duoi">{@render examples()}</div>
      </div>
    {/if}
  </main>
{/if}

<style>
  /* Thẻ là trung tâm màn học: một cột hẹp, canh giữa, không có gì đặt cạnh để mắt phải chia đôi. */
  .hud,
  .deck {
    width: 100%;
    max-width: 760px;
    margin: 0 auto;
  }
  .hud {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 14px;
  }
  .deck {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  /* Thẻ và hai nút chuyển từ nằm cùng một hàng; thẻ co giãn, nút giữ nguyên cỡ. */
  .sanh {
    display: flex;
    align-items: center;
    gap: 18px;
  }
  .o-the {
    flex: 1 1 auto;
    min-width: 0;
  }
  .duoi {
    padding: 0 72px;
  }
  .tron {
    flex: none;
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    border: 1px solid var(--ink);
    border-radius: 50%;
    background: var(--surface);
    box-shadow: var(--sh-sm);
    color: var(--text);
    cursor: pointer;
    /* Rê chuột không đổi gì cho đỡ rối mắt; chỉ lúc bấm mới có phản hồi. */
    transition: background 0.15s ease;
  }
  .tron:hover:not(:disabled) {
    background: var(--surface-2);
  }
  .tron:active:not(:disabled) {
    background: var(--surface-2);
  }
  .tron:disabled {
    opacity: 0.35;
    cursor: default;
  }
  .tron:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 3px;
  }

  /* Nút nghe ở góc thẻ: nằm đè lên mặt thẻ nên phải ở trên lớp lật. */
  .loa {
    position: absolute;
    z-index: 2;
    top: 14px;
    right: 14px;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: var(--surface);
    box-shadow: var(--sh-sm);
    color: var(--text-2);
    cursor: pointer;
    transition: color 0.15s ease;
  }
  .loa:hover,
  .loa:active {
    color: var(--son-text);
  }
  .loa:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }

  /* Hai thẻ xếp phía sau lệch ra ngoài 7px mỗi bên, trên màn hẹp chúng đẩy trang tràn ngang. */
  .the {
    margin-inline: 14px;
  }

  /* Khung thẻ: nền chuyển sắc dịu, viền trong mảnh và bóng sâu hơn để thẻ nổi khỏi trang. */
  .the :global(.face) {
    border-radius: 26px;
    box-shadow:
      0 18px 40px -24px rgb(16 24 40 / 45%),
      inset 0 1px 0 rgb(255 255 255 / 70%);
  }
  .the :global(.face-front) {
    /* Chỉ hắt sáng ở góc trên, giữ nguyên màu nền của thẻ. */
    background-image: linear-gradient(158deg, rgb(255 255 255 / 55%), rgb(255 255 255 / 0%) 42%);
  }
  .the :global(.face-back) {
    background-image: linear-gradient(160deg, var(--surface-2), var(--surface) 60%);
  }
  /* app.css nhấc thẻ lên khi rê chuột — ở màn này bỏ, thẻ đứng yên cho dễ nhìn. */
  .the :global(.flip:hover) :global(.flip-inner) {
    translate: none;
  }

  /* Thẻ mới trượt vào từ phía vừa đi tới; câu ví dụ đổ xuống ngay sau khi lật. */
  .vao {
    animation: thevao 0.34s cubic-bezier(0.22, 0.9, 0.26, 1) both;
  }
  @keyframes thevao {
    from {
      opacity: 0;
      transform: translateX(calc(var(--huong) * 40px)) scale(0.97);
    }
    to {
      opacity: 1;
      transform: none;
    }
  }
  .vidu {
    animation: hienra 0.3s ease both;
  }
  @keyframes hienra {
    from {
      opacity: 0;
      transform: translateY(-8px);
    }
    to {
      opacity: 1;
      transform: none;
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .vao,
    .vidu {
      animation: none;
    }
  }

  .goi {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: var(--text-2);
  }
  .ctrl {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .swipe-zone {
    touch-action: pan-y;
  }
</style>
