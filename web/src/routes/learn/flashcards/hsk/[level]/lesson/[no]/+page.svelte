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
  import DauBai from '$lib/components/DauBai.svelte';
  import { completePart, lessonByNo, saveProgress, seededShuffle } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';
  import { CHE_DO, GhepCap, GoChu, TracNghiem, type CheDo } from '$lib/learn/che-do/state.svelte';
  import GhepCapView from '$lib/learn/che-do/GhepCap.svelte';
  import TracNghiemView from '$lib/learn/che-do/TracNghiem.svelte';
  import GoChuView from '$lib/learn/che-do/GoChu.svelte';
  import TongKet from '$lib/learn/che-do/TongKet.svelte';

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
  // Pinyin / nghĩa của câu ví dụ: mặc định tắt để tự đọc chữ Hán trước. Giữ nguyên khi sang thẻ
  // khác, kẻo người đã bật phải bấm lại ở từng thẻ; mở bài mới thì tắt lại.
  let vdPinyin = $state(false);
  let vdNghia = $state(false);

  // Chế độ học: Lật thẻ là màn cũ, 3 chế độ còn lại luyện cùng bộ từ. Mỗi chế độ giữ lượt đang
  // làm khi đổi qua lại; đối tượng nằm ở trang nên đổi bố cục điện thoại/máy tính không mất.
  let che = $state<CheDo>('the');
  let ghep = $state.raw<GhepCap | null>(null);
  let tn = $state.raw<TracNghiem | null>(null);
  let go = $state.raw<GoChu | null>(null);

  const cards = $derived(data?.words ?? []);
  const total = $derived(order.length);
  const card = $derived<Word | null>(cards[order[i]] ?? null);

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
      vdPinyin = false;
      vdNghia = false;
      ghep = tn = go = null;
      if (che !== 'the') batDau(che);
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
    if (flipped && settings.value.autoSpeak && card) void speak(card.hanzi, null, card.pinyin, 'nam');
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

  /** Xong một chế độ luyện cũng tính là học xong phần Lật thẻ, như lật hết thẻ. */
  function xongCheDo(): void {
    if (!data) return;
    completePart(data.lesson.id, 'lt').catch(() => toast.show('Đã học xong nhưng chưa lưu được tiến độ.'));
  }

  /** Mở lượt mới của một chế độ; `tu` bỏ trống là cả bài, truyền danh sách từ sai để luyện lại. */
  function batDau(c: CheDo, tu: Word[] = cards): void {
    if (c === 'ghep') ghep = new GhepCap(tu, xongCheDo);
    else if (c === 'tn') tn = new TracNghiem(tu, cards, xongCheDo);
    else if (c === 'go') go = new GoChu(tu, xongCheDo);
  }

  function chonCheDo(c: CheDo): void {
    che = c;
    if ((c === 'ghep' && !ghep) || (c === 'tn' && !tn) || (c === 'go' && !go)) batDau(c);
  }

  // Phím tắt vẫn còn cho người quen dùng bàn phím, chỉ không bày gợi ý trên màn nữa.
  // Ba chế độ luyện có phím riêng trong component của chúng.
  function onKey(e: KeyboardEvent): void {
    if (che !== 'the' || finished || !card) return;
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
    {#each toneParts(py) as p, k (k)}<span class="t{p.tone}">{p.text}</span>{/each}
  </span>
{/snippet}

{#snippet cardFace(m: boolean)}
  {#if card}
    <!-- Khoá theo chỉ số thẻ: mỗi thẻ là một phần tử mới nên animation “trượt vào” chạy lại từ đầu. -->
    {#key i}
      <!-- Điện thoại: thẻ cao theo màn hình, chừa chỗ cho hàng nút ngay bên dưới. -->
      <div class="deckwrap the vao" style="height: {m ? 'clamp(240px, 40vh, 330px)' : '340px'}; --huong: {huong}">
        <!-- Nút nghe nằm ngoài <button> lật (không lồng nút được) nên đặt tuyệt đối đè lên góc thẻ.
             Chỉ hiện khi mặt đang xem là chữ Hán — nghe pinyin hay nghĩa tiếng Việt thì vô nghĩa. -->
        {#if flipped || settings.value.cardFront === 'hanzi'}
          <button type="button" class="loa" aria-label="Nghe lại {card.hanzi}" onclick={() => speak(card.hanzi, null, card.pinyin, 'nam')}>
            <Icon name="speaker" size={m ? 17 : 18} />
          </button>
        {/if}
        <button type="button" class={flipped ? 'flip is-back' : 'flip'} onclick={flip} aria-label={flipped ? 'Bấm để xem lại mặt trước' : 'Lật thẻ'}>
          <span class="flip-inner">
            <span class="face face-front">
              <span class="nhan-mat">{frontLabel}</span>
              <span class={settings.value.cardFront === 'hanzi' ? 'hz' : ''} lang={settings.value.cardFront === 'hanzi' ? 'zh' : 'vi'}
                    style="font-size: {settings.value.cardFront === 'hanzi' ? (m ? 60 : 84) : (m ? 28 : 38)}px; font-weight: 800; line-height: 1.15">
                {frontText}
              </span>
              <span class="goi"><Icon name="repeat" size={13} /> {m ? 'Chạm để lật' : 'Bấm để lật'}</span>
            </span>
            <span class="face face-back">
              {@render pinyinRow(card.pinyin, m ? 19 : 22)}
              <span class="hz" lang="zh" style="font-size: {m ? 60 : 84}px; line-height: 1.1">{card.hanzi}</span>
              {#if settings.value.showHanViet && card.hanViet}
                <span class="hv">{card.hanViet}</span>
              {/if}
              <span class="nghia" style="font-size: {m ? 17 : 20}px">{card.meaning}</span>
            </span>
          </span>
        </button>
      </div>
    {/key}
  {/if}
{/snippet}

<!-- Điều hướng gom ngay dưới thẻ (như trang mẫu): số thẻ + tiến độ, rồi ← Lật → canh giữa. -->
{#snippet dieuHuong(m: boolean)}
  <div class="dh">
    <div class="dh-so">
      <span class="num">Thẻ <b>{Math.min(i + 1, total)}</b> / {total}</span>
      <span class="dh-bar"><Bar value={i} {total} /></span>
    </div>
    <div class="dh-nut" class:dh-nut-m={m}>
      <button type="button" class="zk-nut" aria-label="Từ trước" disabled={i === 0} onclick={prev}><Icon name="left" size={20} /></button>
      <button type="button" class="zk-nut zk-nut-chinh nut-lat" onclick={flip}><Icon name="flip" size={17} /> {flipped ? 'Mặt trước' : 'Lật thẻ'}</button>
      <button type="button" class="zk-nut" aria-label="Từ sau" onclick={next}><Icon name="right" size={20} /></button>
    </div>
    {#if !m}<p class="dh-phim">Phím Space để lật · ← / → để chuyển từ</p>{/if}
  </div>
{/snippet}

<!-- Chỉ hiện sau khi lật: trước đó nó là đáp án lộ sẵn, mà hiện ra đúng lúc cũng làm màn học sinh động hơn. -->
{#snippet examples()}
  {#if card && flipped && settings.value.showExamples && card.examples.length > 0}
    <div class="vidu">
      <div class="vd-dau">
        <p class="kicker">Câu ví dụ</p>
        <div class="seg">
          <button type="button" class:on={vdPinyin} aria-pressed={vdPinyin} onclick={() => (vdPinyin = !vdPinyin)}>Pinyin</button>
          <button type="button" class:on={vdNghia} aria-pressed={vdNghia} onclick={() => (vdNghia = !vdNghia)}>Nghĩa</button>
        </div>
      </div>
      {#each card.examples as ex (ex.zh)}
        <div class="zk-o vd-cau">
          <div style="display: flex; align-items: flex-start; gap: 8px">
            <span class="hz" lang="zh" style="font-size: 20px; line-height: 1.45; flex: 1">
              {#if ex.highlight && ex.zh.includes(ex.highlight)}
                {@const parts = ex.zh.split(ex.highlight)}
                {parts[0]}<b class="hl">{ex.highlight}</b>{parts.slice(1).join(ex.highlight)}
              {:else}{ex.zh}{/if}
            </span>
            <button type="button" class="loa-cau" aria-label="Nghe câu" onclick={() => speak(ex.zh, null, undefined, 'nam')}><Icon name="speaker" size={16} /></button>
          </div>
          {#if vdPinyin && ex.pinyin}{@render pinyinRow(ex.pinyin, 13.5)}{/if}
          {#if vdNghia && ex.vi}<span class="muted" style="font-size: 14px">{ex.vi}</span>{/if}
        </div>
      {/each}
    </div>
  {/if}
{/snippet}

{#snippet done(m: boolean)}
  <div class="xong" style="padding: {m ? 12 : 20}px 0">
    <span class="stamp" style="font-size: {m ? 26 : 32}px">完成</span>
    <h2 style="font-size: {m ? 22 : 26}px">Xong bài {data?.lesson.no}!</h2>
    <p class="muted">Bạn đã lật hết {total} thẻ của bài “{data?.lesson.title}”.</p>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center">
      <button type="button" class="btn" onclick={restart}><Icon name="repeat" size={16} /> Lật lại</button>
      {#if data?.next}
        <a href="/learn/flashcards/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son">Bài tiếp: {data.next.title}</a>
      {:else}
        <a href="/learn/flashcards/hsk/{levelId}" class="btn btn-son">Về danh sách bài</a>
      {/if}
    </div>
  </div>
{/snippet}

<!-- Hàng chọn chế độ: nút có viền như trang mẫu, nút đang chọn tô màu son của Zìkǎ. -->
{#snippet chonCheDoRow(m: boolean)}
  <div class="zk-tabs" class:zk-tabs-deu={m} role="tablist" aria-label="Chế độ học">
    {#each CHE_DO as c (c.ma)}
      <button type="button" role="tab" class="zk-tab" aria-selected={che === c.ma} onclick={() => chonCheDo(c.ma)}>{c.ten}</button>
    {/each}
  </div>
{/snippet}

{#snippet luyen(m: boolean)}
  {#if che === 'ghep' && ghep}
    {#if ghep.xong}
      {@const kq = ghep.ketQua()}
      <TongKet {kq} ten="Ghép cặp" {m} onLamLai={() => batDau('ghep')} onLuyenSai={() => batDau('ghep', kq.sai)} />
    {:else}
      <GhepCapView st={ghep} {m} />
    {/if}
  {:else if che === 'tn' && tn}
    {#if tn.xong}
      {@const kq = tn.ketQua()}
      <TongKet {kq} ten="Trắc nghiệm" {m} onLamLai={() => batDau('tn')} onLuyenSai={() => batDau('tn', kq.sai)} />
    {:else}
      <TracNghiemView st={tn} {m} />
    {/if}
  {:else if che === 'go' && go}
    {#if go.xong}
      {@const kq = go.ketQua()}
      <TongKet {kq} ten="Gõ chữ" {m} onLamLai={() => batDau('go')} onLuyenSai={() => batDau('go', kq.sai)} />
    {:else}
      <GoChuView st={go} {m} />
    {/if}
  {/if}
{/snippet}

<!-- Một khung trắng chứa trọn màn học (giống “Chế độ học tập” của trang mẫu): thẻ, điều hướng,
     ví dụ và 3 chế độ luyện đều nằm trong, không còn mảnh rời trôi trên nền xám. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if !m}
      <div class="zk-khung-dau">
        <h2 class="zk-khung-ten">Chế độ học</h2>
        {@render chonCheDoRow(false)}
      </div>
    {/if}
    {#if che !== 'the'}
      {@render luyen(m)}
    {:else if finished}
      {@render done(m)}
    {:else}
      {@render cardFace(m)}
      {@render dieuHuong(m)}
      {@render examples()}
    {/if}
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
        <b style="display: block; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{data?.lesson.title ?? '…'}</b>
        <span class="muted" style="font-size: 12px">HSK {levelId} · Bài {lessonNo}</span>
      </span>
      <Avatar size={36} />
    </header>

    <!-- overflow-x: clip — thẻ mới trượt vào lệch 40px, không được tạo thanh cuộn ngang. -->
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px; overflow-x: clip">
      {#if data === null}
        <Skeleton h="420px" r={22} />
      {:else}
        <!-- Điện thoại: hàng chế độ đứng ngoài khung để 4 nút có đủ bề ngang, không cắt chữ. -->
        {@render chonCheDoRow(true)}
        {@render khung(true)}
      {/if}
    </main>
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

  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    {#if data === null}
      <Skeleton h="520px" r={22} />
    {:else}
      <DauBai href="/learn/flashcards/hsk/{levelId}" kicker="HSK {levelId} · Bài {lessonNo} · {cards.length} từ" title={data.lesson.title} />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  /* Thẻ: một lá phẳng, viền mảnh trên nền khung trắng. */
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
  .nghia {
    font-weight: 600;
    max-width: 90%;
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

  /* Nút nghe ở góc thẻ: nằm đè lên mặt thẻ nên phải ở trên lớp lật. */
  .loa {
    position: absolute;
    z-index: 2;
    top: 12px;
    right: 12px;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: var(--surface);
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
  .dh-phim {
    font-size: 12px;
    color: var(--text-3);
  }

  /* Ví dụ nằm trong khung, ngăn với phần thẻ bằng một đường kẻ. */
  .vidu {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
    animation: hienra 0.3s ease both;
  }
  .vd-dau {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
  }
  .vd-cau {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  /* Nút nghe câu ví dụ đủ to cho ngón tay. */
  .loa-cau {
    flex: none;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    margin: -2px -4px 0 0;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
  }
  .loa-cau:hover,
  .loa-cau:active {
    color: var(--son-text);
  }

  .xong {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    text-align: center;
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
</style>
