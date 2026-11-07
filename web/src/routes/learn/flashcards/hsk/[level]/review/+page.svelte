<script lang="ts">
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { untrack } from 'svelte';
  import { page } from '$app/state';
  import { replaceState } from '$app/navigation';
  import { api } from '$lib/api/client';
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
  import DauBai from '$lib/components/DauBai.svelte';
  import { completeReview, levelProgress, saveReviewProgress, seededShuffle } from '$lib/learn/lesson';
  import { hocTiepThe } from '$lib/learn/resume';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';
  import type { Word } from '$lib/api/types';
  import ChuDien from '$lib/components/ChuDien.svelte';

  // Lật thẻ · Tổng hợp cả cấp (04 §4.9 part = lt). Bố cục theo màn lật thẻ của bài:
  // đầu bài + một khung trắng chứa thẻ và điều hướng.
  const levelId = $derived(Number(page.params.level));

  let words = $state<Word[] | null>(null);
  let order = $state<number[]>([]);
  let failed = $state(false);
  let i = $state(0);
  let flipped = $state(false);
  let finished = $state(false);
  let huong = $state(1); // 1 = sang thẻ sau, -1 = lùi lại — thẻ mới trượt vào từ phía tương ứng

  const total = $derived(order.length);
  const card = $derived<Word | null>(words?.[order[i]] ?? null);

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.get<{ level: { name: string }; words: Word[] }>(`/learn/levels/${levelId}/review/lt`);
      words = res.words;
      // Như màn theo bài: “Từ đầu” và xáo trộn đi theo history state, đọc xong thì xoá để F5 không lặp lại.
      const st = untrack(() => page.state);
      const fromStart = st.fromStart === true;
      const shuffle = st.shuffle ?? settings.value.shuffle;
      if (st.fromStart !== undefined || st.shuffle !== undefined) {
        try { replaceState('', {}); } catch { /* router chưa sẵn sàng thì để nguyên */ }
      }
      const saved = fromStart ? null : await levelProgress(levelId, 'lt').then((p) => p.review).catch(() => null);
      const back = hocTiepThe(saved, res.words.length, fromStart);

      const idx = res.words.map((_, k) => k);
      order = back ? back.order : shuffle ? seededShuffle(idx, levelId * 7919) : idx;
      i = back ? back.i : 0;
      flipped = false;
      finished = res.words.length === 0;
      heartbeat.setActivity({ part: 'lt', screen: 'review', level: levelId });
    } catch {
      failed = true;
    }
  }
  onMount(load);

  function flip(): void {
    flipped = !flipped;
    if (flipped && settings.value.autoSpeak && card) void speak(card.hanzi, null, card.pinyin, 'nam');
  }

  function prev(): void {
    if (i > 0) {
      huong = -1;
      i--;
      flipped = false;
      void saveReviewProgress(levelId, 'lt', i, total, { order }).catch(() => {});
    }
  }

  async function next(): Promise<void> {
    flipped = false;
    huong = 1;
    const at = i + 1;
    if (at >= total) {
      finished = true;
      try {
        await completeReview(levelId, 'lt');
      } catch {
        toast.show('Xong rồi nhưng chưa lưu được tiến độ.');
      }
      return;
    }
    i = at;
    void saveReviewProgress(levelId, 'lt', at, total, { order }).catch(() => {});
  }

  // Cùng bộ phím với màn lật thẻ theo bài, để người quen bàn phím không phải đổi thói quen.
  function onKey(e: KeyboardEvent): void {
    if (finished || !card) return;
    const tag = (e.target as HTMLElement | null)?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); flip(); }
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'ArrowRight') void next();
  }
</script>

<svelte:head><title>Tổng hợp {cap.ten(levelId)} · Zìkǎ</title></svelte:head>
<svelte:window onkeydown={onKey} />

{#snippet cardFace(m: boolean)}
  {#if card}
    <!-- Khoá theo chỉ số thẻ: mỗi thẻ là một phần tử mới nên animation “trượt vào” chạy lại từ đầu. -->
    {#key i}
      <!-- Điện thoại: thẻ cao theo màn hình, chừa chỗ cho hàng nút ngay bên dưới. -->
      <div class="deckwrap the vao" style="height: {m ? 'clamp(240px, 40vh, 330px)' : '340px'}; --huong: {huong}">
        <button type="button" class={flipped ? 'flip is-back' : 'flip'} onclick={flip} aria-label={flipped ? 'Bấm để xem lại mặt trước' : 'Lật thẻ'}>
          <span class="flip-inner">
            <span class="face face-front">
              <span class="nhan-mat">Nghĩa tiếng Việt</span>
              <span style="font-size: {m ? 28 : 38}px; font-weight: 800; line-height: 1.15">{card.meaning}</span>
              <span class="goi"><Icon name="repeat" size={13} /> {m ? 'Chạm để lật' : 'Bấm để lật'}</span>
            </span>
            <span class="face face-back">
              <span class="num" style="font-size: {m ? 19 : 22}px; font-weight: 600" class:no-tone={!settings.value.toneColors}>
                {#each toneParts(card.pinyin) as p, k (k)}<span class="t{p.tone}">{p.text}</span>{/each}
              </span>
              <ChuDien text={card.hanzi} size={m ? 68 : 92} />
              {#if settings.value.showHanViet && card.hanViet}
                <span class="hv">{card.hanViet}</span>
              {/if}
            </span>
          </span>
        </button>
      </div>
    {/key}
  {/if}
{/snippet}

<!-- Điều hướng gom ngay dưới thẻ như màn lật thẻ theo bài: số thẻ + tiến độ, rồi ← Lật → canh giữa.
     Giống màn theo bài: chỉ lật và chuyển từ, không chấm thuộc/chưa thuộc. -->
{#snippet dieuHuong(m: boolean)}
  <div class="dh">
    <div class="dh-so">
      <span class="num">Thẻ <b>{Math.min(i + 1, total)}</b> / {total}</span>
      <span class="dh-bar"><Bar value={i} {total} /></span>
    </div>
    <div class="dh-nut" class:dh-nut-m={m}>
      <button type="button" class="zk-nut" aria-label="Từ trước" disabled={i === 0} onclick={prev}><Icon name="chev" size={20} style="transform: rotate(180deg)" /></button>
      <button type="button" class="zk-nut zk-nut-chinh nut-lat" onclick={flip}><Icon name="flip" size={17} /> {flipped ? 'Mặt trước' : 'Lật thẻ'}</button>
      <button type="button" class="zk-nut" aria-label="Từ sau" onclick={next}><Icon name="chev" size={20} /></button>
    </div>
  </div>
{/snippet}

{#snippet done(m: boolean)}
  <div class="xong" style="padding: {m ? 12 : 20}px 0">
    <span class="stamp" style="font-size: {m ? 26 : 32}px">完成</span>
    <h2 style="font-size: {m ? 22 : 26}px">Xong Tổng hợp {cap.ten(levelId)}</h2>
    <p class="muted">Bạn đã lật hết {total} thẻ của {cap.tu} này.</p>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center">
      <button type="button" class="btn" onclick={load}><Icon name="repeat" size={16} /> Lật lại</button>
      <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
    </div>
  </div>
{/snippet}

<!-- Một khung trắng chứa trọn màn ôn: thẻ và điều hướng, như màn lật thẻ theo bài.
     Thẻ Tổng hợp trộn từ nhiều bài nên đầu khung ghi thẻ đang xem thuộc bài nào. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if finished}
      {@render done(m)}
    {:else}
      <div class="zk-khung-dau">
        <h2 class="zk-khung-ten">Lật thẻ cả {cap.tu}</h2>
        {#if card?.lessonNo}<span class="zk-so">Từ Bài {card.lessonNo}</span>{/if}
      </div>
      {@render cardFace(m)}
      {@render dieuHuong(m)}
    {/if}
  </section>
{/snippet}

{#snippet loi()}
  <EmptyState title="{cap.tuHoa} này chưa có từ nào.">
    {#snippet action()}<a href="/learn/flashcards/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
  </EmptyState>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="chev" size={20} style="transform: rotate(180deg)" /></a>
      <span style="flex: 1; min-width: 0; text-align: center">
        <b style="display: block; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">Tổng hợp {cap.ten(levelId)}</b>
        <span class="muted" style="font-size: 12px">Lật thẻ{words ? ` · ${words.length} từ` : ''}</span>
      </span>
      <Avatar size={36} />
    </header>

    <!-- overflow-x: clip — thẻ mới trượt vào lệch 40px, không được tạo thanh cuộn ngang. -->
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px; overflow-x: clip">
      {#if failed}
        {@render loi()}
      {:else if words === null}
        <Skeleton h="420px" r={22} />
      {:else}
        {@render khung(true)}
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/flashcards/hsk/{levelId}">{cap.ten(levelId)}</a> / <b>Tổng hợp</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px">
      <Switcher current="lt" />
      <Avatar size={44} />
    </div>
  </header>

  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    {#if failed}
      {@render loi()}
    {:else if words === null}
      <Skeleton h="520px" r={22} />
    {:else}
      <DauBai href="/learn/flashcards/hsk/{levelId}" kicker="{cap.ten(levelId)} · Tổng hợp · {words.length} từ" title="Tổng hợp {cap.ten(levelId)}" />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  /* Thẻ: một lá phẳng, viền mảnh trên nền khung trắng — cùng kiểu màn lật thẻ theo bài. */
  .the :global(.face) {
    gap: 10px;
  }
  .the :global(.flip:focus-visible) {
    border-radius: 20px;
  }
  .nhan-mat {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-3);
  }
  .hv {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-3);
  }
  .goi {
    position: absolute;
    bottom: 16px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: var(--text-3);
  }

  /* Điều hướng dưới thẻ. */
  .dh {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
  }
  .dh-so {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    max-width: 360px;
    font-size: 13.5px;
    color: var(--text-2);
    white-space: nowrap;
  }
  .dh-bar {
    flex: 1;
    min-width: 0;
  }
  .dh-nut {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .nut-lat {
    min-width: 150px;
  }
  /* Điện thoại: ba nút chia hết bề ngang khung, nút Lật rộng nhất cho ngón cái. */
  .dh-nut-m {
    width: 100%;
  }
  .dh-nut-m .zk-nut {
    flex: 1 1 0;
    height: 50px;
  }
  .dh-nut-m .nut-lat {
    flex: 2 1 0;
    min-width: 0;
  }

  .xong {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    text-align: center;
  }

  /* Thẻ mới trượt vào từ phía vừa đi tới. */
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
  @media (prefers-reduced-motion: reduce) {
    .vao {
      animation: none;
    }
  }
</style>
