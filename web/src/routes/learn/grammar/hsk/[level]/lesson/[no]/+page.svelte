<script lang="ts">
  import { SvelteSet } from 'svelte/reactivity';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import { completePart, lessonByNo, saveProgress } from '$lib/learn/lesson';
  import { tileOrder } from '$lib/utils/shuffle';
  import { speak } from '$lib/learn/speech';
  import { normZh } from '$lib/utils/grade';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Cấu trúc ngữ pháp (07 §11).
  interface Block { label: string; hz: string; color: string }
  interface Example { kind: string; formula: string; zh: string; highlight: string | null; pinyin: string; vi: string }
  interface Practice { answer: string; vi: string; tiles: string[] }
  interface Point {
    id: number;
    title: string;
    subtitle: string;
    explain: string;
    note: string | null;
    blocks: Block[];
    examples: Example[];
    practice: Practice | null;
  }
  interface Data { lesson: LessonRef; points: Point[]; next: NextLesson | null }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<Data | null>(null);
  let failed = $state(false);
  let pi = $state(0);
  let picked = $state<number[]>([]);
  let checked = $state(false);
  let done = $state(false);
  /** Các điểm ngữ pháp đã mở trong lượt này — xem hết thì tự ghi “xong phần” (không còn nút Xong bài). */
  const daXem = new SvelteSet<number>();
  // Pinyin / nghĩa của câu ví dụ: mặc định tắt để tự đọc chữ Hán trước, đổi điểm thì tắt lại.
  let vdPinyin = $state(false);
  let vdNghia = $state(false);

  const points = $derived(data?.points ?? []);
  const g = $derived<Point | null>(points[pi] ?? null);
  const built = $derived(picked.map((k) => g?.practice?.tiles[k] ?? '').join(''));
  const isRight = $derived(checked && g?.practice ? normZh(built) === normZh(g.practice.answer) : false);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) { failed = true; return; }
      data = await api.get<Data>(`/learn/lessons/${l.id}/grammar`);
      pi = 0;
      resetPractice();
      done = false;
      daXem.clear();
      daXem.add(0);
      xemHet();
      heartbeat.setActivity({ part: 'np', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  $effect(() => { void levelId; void lessonNo; void load(); });

  // Gọi mỗi khi mở điểm khác hoặc tải bài, nên câu ví dụ cũng tắt pinyin / nghĩa lại ở đây.
  function resetPractice(): void {
    picked = [];
    checked = false;
    vdPinyin = false;
    vdNghia = false;
  }

  function goPoint(k: number): void {
    pi = k;
    resetPractice();
    if (data) void saveProgress(data.lesson.id, 'np', k + 1, points.length).catch(() => {});
    daXem.add(k);
    xemHet();
  }

  /** Đã mở đủ mọi điểm của bài thì ghi xong phần Ngữ pháp, để tiến độ bài học không kẹt ở “đang học”. */
  function xemHet(): void {
    if (points.length > 0 && daXem.size >= points.length) void finish();
  }

  async function finish(): Promise<void> {
    if (!data || done) return;
    try {
      await completePart(data.lesson.id, 'np');
      done = true;
      toast.ok('Đã xem xong phần ngữ pháp.');
    } catch {
      toast.show('Chưa lưu được tiến độ.');
    }
  }

  function parts(zh: string, hl: string | null): [string, string, string] {
    if (!hl || !zh.includes(hl)) return [zh, '', ''];
    const i = zh.indexOf(hl);
    return [zh.slice(0, i), hl, zh.slice(i + hl.length)];
  }
</script>

<svelte:head><title>{data ? `${data.lesson.title} · Ngữ pháp` : 'Ngữ pháp'} · Zìkǎ</title></svelte:head>

<!-- Phần học của một điểm: công thức, giải thích, ví dụ, ghi chú. -->
{#snippet diem(p: Point, m: boolean)}
  <div class="np-diem">
    <div>
      <h3 class="hz np-ten" lang="zh" style="font-size: {m ? 26 : 30}px">{p.title}</h3>
      {#if p.subtitle}<p class="muted" style="font-size: 15px">{p.subtitle}</p>{/if}
    </div>

    <!-- Khối công thức -->
    {#if p.blocks.length > 0}
      <div class="np-cthuc">
        {#each p.blocks as b, k (k)}
          {#if k > 0}<span class="np-cong">+</span>{/if}
          <span class="fblk" style={b.color ? `background: ${b.color}; color: #1F2430` : undefined}>
            <!-- Nền khối là màu pastel quản trị chọn: chữ luôn đậm, kể cả chế độ tối. -->
            <span style="font-size: 11.5px; font-weight: 700; color: {b.color ? '#4A505C' : 'var(--text-2)'}">{b.label}</span>
            <span class="hz" lang="zh" style="font-size: 22px">{b.hz}</span>
          </span>
        {/each}
      </div>
    {/if}

    <p style="font-size: 15px; line-height: 1.65">{p.explain}</p>

    <!-- Ví dụ: mỗi câu một ô nền nhạt như câu ví dụ ở Lật thẻ. -->
    {#if p.examples.length > 0}
      <div class="np-vidu">
        <div class="np-vd-dau">
          <p class="kicker">Ví dụ</p>
          <div class="seg">
            <button type="button" class:on={vdPinyin} aria-pressed={vdPinyin} onclick={() => (vdPinyin = !vdPinyin)}>Pinyin</button>
            <button type="button" class:on={vdNghia} aria-pressed={vdNghia} onclick={() => (vdNghia = !vdNghia)}>Nghĩa</button>
          </div>
        </div>
        {#each p.examples as ex, k (k)}
          {@const [pre, hl, post] = parts(ex.zh, ex.highlight)}
          <div class="zk-o np-cau">
            {#if ex.kind || ex.formula}
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                {#if ex.kind}<span class="tag">{ex.kind}</span>{/if}
                {#if ex.formula}<span class="muted num" style="font-size: 13px">{ex.formula}</span>{/if}
              </div>
            {/if}
            <div style="display: flex; align-items: flex-start; gap: 8px">
              <span class="hz" lang="zh" style="flex: 1; min-width: 0; font-size: {m ? 19 : 22}px; line-height: 1.45">{pre}{#if hl}<b class="hl">{hl}</b>{/if}{post}</span>
              <button type="button" class="np-loa" class:np-loa-m={m} aria-label="Nghe câu" onclick={() => speak(ex.zh)}><Icon name="speaker" size={16} /></button>
            </div>
            {#if vdPinyin && ex.pinyin}<span class="muted" style="font-size: 13.5px">{ex.pinyin}</span>{/if}
            {#if vdNghia && ex.vi}<span style="font-size: 14px">{ex.vi}</span>{/if}
          </div>
        {/each}
      </div>
    {/if}

    {#if p.note}
      <div class="fb" style="background: var(--bo-soft); border-color: #FDE68A">
        <Icon name="flag" size={18} />
        <span style="flex: 1; font-size: 14px">{p.note}</span>
      </div>
    {/if}
  </div>
{/snippet}

<!-- Luyện tập xếp câu: nằm trong khung, ngăn với phần học bằng một đường kẻ. -->
{#snippet xepCau(pr: Practice, m: boolean)}
  <div class="np-xep">
    <p class="kicker">Thử xếp câu</p>
    {#if pr.vi}<p style="font-size: 16px; font-weight: 600; text-align: center">“{pr.vi}”</p>{/if}

    <div class="np-o-xep" class:np-o-trong={picked.length === 0}>
      {#if picked.length === 0}
        <span class="muted" style="font-size: 14px">Bấm các ô chữ bên dưới theo đúng thứ tự</span>
      {:else}
        {#each picked as t, pos (pos)}
          <button type="button" class="wt" disabled={checked} onclick={() => (picked = picked.filter((_, k) => k !== pos))}>
            {pr.tiles[t]}
          </button>
        {/each}
      {/if}
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center">
      {#each tileOrder(pr.tiles) as k (k)}
        <button type="button" class="wt" disabled={checked || picked.includes(k)} onclick={() => (picked = [...picked, k])}>{pr.tiles[k]}</button>
      {/each}
    </div>

    {#if checked}
      <div class="fb" style="background: {isRight ? 'var(--ngoc-soft)' : 'var(--son-soft)'}; border-color: {isRight ? '#9FE0B5' : 'var(--son-line)'}">
        <span class="ok-dot" style="background: {isRight ? 'var(--ngoc)' : 'var(--bad)'}"><Icon name={isRight ? 'check' : 'x'} size={14} /></span>
        <span style="flex: 1">
          <b style="display: block">{isRight ? 'Chính xác!' : 'Chưa đúng'}</b>
          <span class="hz" lang="zh" style="font-size: 17px">{pr.answer}</span>
        </span>
      </div>
    {/if}

    <div class="np-xep-nut" class:np-xep-nut-m={m}>
      <button type="button" class="zk-nut" onclick={resetPractice}><Icon name="repeat" size={16} /> Xếp lại</button>
      <button type="button" class="zk-nut zk-nut-chinh" disabled={checked || picked.length !== pr.tiles.length} onclick={() => (checked = true)}>
        Kiểm tra
      </button>
    </div>
  </div>
{/snippet}

<!-- Một khung trắng chứa trọn màn học như Lật thẻ: dãy điểm ngữ pháp, nội dung điểm, xếp câu
     và hàng nút chuyển điểm ở cuối — không còn hai khối rời trôi trên nền xám. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if points.length === 0}
      <!-- HSK 1 Bài 1–2 chưa có điểm nào: báo rõ thay vì để khung trắng trơn. -->
      <EmptyState title="Bài này chưa có phần ngữ pháp.">
        {#snippet action()}
          {#if data?.next}
            <a href="/learn/grammar/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-sm">Bài tiếp: {data.next.title}</a>
          {:else}
            <a href="/learn/grammar/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>
          {/if}
        {/snippet}
      </EmptyState>
    {:else if g}
      <div class="zk-khung-dau">
        <h2 class="zk-khung-ten">Điểm ngữ pháp</h2>
        <span class="zk-so">{pi + 1} / {points.length}</span>
      </div>

      <!-- Điện thoại: một hàng cuộn ngang, bài nhiều điểm (7 tab) không đẩy nội dung xuống tận dưới. -->
      <div class="zk-tabs" class:np-tabs-m={m} role="tablist" aria-label="Điểm ngữ pháp">
        {#each points as p, k (p.id)}
          <button type="button" role="tab" class="zk-tab" aria-selected={k === pi} onclick={() => goPoint(k)}>
            {k + 1}. <span class="hz" lang="zh">{p.title}</span>
          </button>
        {/each}
      </div>

      {@render diem(g, m)}
      {#if g.practice}{@render xepCau(g.practice, m)}{/if}

      <!-- Chuyển điểm ngay cuối khung: đọc xong một điểm dài khỏi phải cuộn ngược lên dãy tab. -->
      <div class="zk-chan" class:np-chan-m={m}>
        <button type="button" class="zk-nut" disabled={pi === 0} onclick={() => goPoint(pi - 1)}>
          <Icon name="left" size={18} /> Điểm trước
        </button>
        {#if pi < points.length - 1}
          <button type="button" class="zk-nut zk-nut-chinh" onclick={() => goPoint(pi + 1)}>
            Điểm sau <Icon name="right" size={18} />
          </button>
        {:else if data?.next}
          <a href="/learn/grammar/hsk/{levelId}/lesson/{data.next.no}" class="zk-nut zk-nut-chinh np-tiep">
            <span class="np-tiep-chu">Bài tiếp: {data.next.title}</span> <Icon name="right" size={18} />
          </a>
        {:else}
          <a href="/learn/grammar/hsk/{levelId}" class="zk-nut zk-nut-chinh">Về danh sách bài</a>
        {/if}
      </div>
    {/if}
  </section>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/grammar/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <span style="flex: 1; min-width: 0; text-align: center">
        <b style="display: block; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{data?.lesson.title ?? 'Ngữ pháp'}</b>
        <span class="muted" style="font-size: 12px">Ngữ pháp · HSK {levelId} · Bài {lessonNo}</span>
      </span>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">
      {#if failed}
        <EmptyState title="Không mở được bài này." desc="Bài có thể đang ẩn hoặc đã bị xoá.">
          {#snippet action()}<a href="/learn/grammar/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
        </EmptyState>
      {:else if data === null}
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
    <nav class="crumb" style="margin-left: 8px">
      <a href="/learn/grammar/hsk/{levelId}">Ngữ pháp · HSK {levelId}</a> / <b>Bài {lessonNo}{data ? ` · ${data.lesson.title}` : ''}</b>
    </nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="np" /><Avatar size={44} /></div>
  </header>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    {#if failed}
      <EmptyState title="Không mở được bài này." desc="Bài có thể đang ẩn hoặc đã bị xoá.">
        {#snippet action()}<a href="/learn/grammar/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
      </EmptyState>
    {:else if data === null}
      <Skeleton h="520px" r={22} />
    {:else}
      <DauBai
        href="/learn/grammar/hsk/{levelId}"
        kicker="HSK {levelId} · Bài {lessonNo} · {points.length > 0 ? `${points.length} điểm ngữ pháp` : 'Ngữ pháp'}"
        title={data.lesson.title}
      />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  .np-diem {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .np-ten {
    line-height: 1.25;
  }
  .np-cthuc {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    justify-content: center;
  }
  .np-cong {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-3);
  }

  /* Dãy điểm trên điện thoại: cuộn ngang sát hai mép khung (khung điện thoại đệm 14px). */
  .np-tabs-m {
    flex-wrap: nowrap;
    overflow-x: auto;
    margin: 0 -14px;
    padding: 2px 14px 6px;
    scrollbar-width: none;
  }
  .np-tabs-m .zk-tab {
    flex: none;
    height: 44px;
  }

  .np-vidu {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  /* Tiêu đề phần ví dụ và cặp nút Pinyin | Nghĩa chung một hàng. */
  .np-vd-dau {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
  }
  .np-cau {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .np-loa {
    flex: none;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
  }
  .np-loa:hover,
  .np-loa:active {
    color: var(--son-text);
  }
  .np-loa:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  /* Điện thoại: đủ 44px cho ngón tay. */
  .np-loa-m {
    width: 44px;
    height: 44px;
    margin: -4px -6px 0 0;
  }

  .np-xep {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
  }
  .np-o-xep {
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
  .np-o-trong {
    justify-content: center;
  }
  .np-xep-nut {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
  }
  .np-xep-nut-m .zk-nut,
  .np-chan-m .zk-nut {
    flex: 1 1 0;
    min-width: 0;
  }
  .np-chan-m .zk-nut {
    padding: 0 10px;
  }
  /* Tên bài tiếp có thể dài: cắt bớt thay vì đẩy nút tràn ngang. */
  .np-tiep {
    min-width: 0;
    max-width: 62%;
  }
  .np-chan-m .np-tiep {
    max-width: none;
  }
  .np-tiep-chu {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
</style>
