<script lang="ts">
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import type { LessonItem, LevelLessons, LevelProgress, Part, ProgressRow } from '$lib/api/types';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { num } from '$lib/utils/format';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import NotificationBell from '$lib/components/NotificationBell.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
  import { PART, PART_TEXTS } from '$lib/parts';
  import { levelLessons, levelProgress, progressOf } from '$lib/learn/lesson';
  import { soHienThi } from '$lib/learn/resume';
  import { SO_HAN, taiChuBut } from '$lib/learn/hoa-tiet';

  // Khuôn “Chọn bài” dùng chung cho 5 phần (07 §5.3). Bố cục theo màn Chọn bài của Lật thẻ
  // (khung đầu tô màu cấp, lưới bài, cột chi tiết) để 6 phần trông như một ứng dụng.
  let { part }: { part: Exclude<Part, 'lt'> } = $props();

  const info = $derived(PART[part]);
  const texts = $derived(PART_TEXTS[part]);
  const levelId = $derived(Number(page.params.level));

  let data = $state<LevelLessons | null>(null);
  let prog = $state<LevelProgress | null>(null);
  let failed = $state(false);
  let picked = $state<number | 'all' | null>(null);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const [d, p] = await Promise.all([levelLessons(levelId, true), levelProgress(levelId, part)]);
      data = d;
      prog = p;
      // Mặc định chọn bài đang dở, không có thì bài đầu chưa xong.
      const doing = d.lessons.find((l) => (progressOf(p, l.id)?.position ?? 0) > 0);
      const todo = d.lessons.find((l) => progressOf(p, l.id)?.status !== 'done');
      picked = (doing ?? todo ?? d.lessons[0])?.id ?? null;
    } catch {
      failed = true;
    }
  }

  taiChuBut();
  onMount(() => {
    heartbeat.setActivity({ part, screen: 'level', level: levelId });
    void load();
  });

  $effect(() => {
    void levelId;
    void load();
  });

  /** Số lượng của phần này trong một bài (từ / câu / chữ / điểm). */
  const countOf = (l: LessonItem): number => l.counts[part] ?? 0;
  const donVi = $derived({ bt: 'câu', kt: 'câu', lv: 'chữ', ht: 'câu', np: 'điểm' }[part]);

  const lessons = $derived(data?.lessons ?? []);
  const doneCount = $derived(lessons.filter((l) => progressOf(prog, l.id)?.status === 'done').length);
  const tongSo = $derived(lessons.reduce((s, l) => s + countOf(l), 0));
  const current = $derived(picked === 'all' ? null : lessons.find((l) => l.id === picked) ?? null);
  const currentProg = $derived<ProgressRow | null>(current ? progressOf(prog, current.id) : null);

  /** Dòng mô tả dưới tên bài, theo từng phần. */
  function subOf(l: LessonItem): string {
    return l.sub?.[part] ?? `${countOf(l)} ${donVi}`;
  }

  /** `dot`: đã xong thì hiện chấm xanh như thẻ bài Lật thẻ; điểm Kiểm tra vẫn hiện thành nhãn vì có số. */
  function statusOf(l: LessonItem): { label: string; cls: string; dot?: boolean } {
    const p = progressOf(prog, l.id);
    if (countOf(l) === 0) return { label: 'Sắp có', cls: 'tag' };
    if (part === 'kt' && p?.bestScore != null) {
      return { label: `Điểm ${num(p.bestScore)}/${num(p.maxScore ?? 0)}`, cls: 'tag tag-ok' };
    }
    if (p?.status === 'done') return { label: 'Đã xong', cls: 'tag tag-ok', dot: true };
    if ((p?.position ?? 0) > 0 && texts.doingTag) return { label: texts.doingTag, cls: 'tag tag-g' };
    return { label: '', cls: '' };
  }

  /** Phần trăm vạch tiến độ trên thẻ bài và ở cột chi tiết. */
  function pctOf(p: ProgressRow | null | undefined): number {
    if (!p) return 0;
    if (p.status === 'done') return 100;
    return p.position > 0 && p.total > 0 ? (p.position / p.total) * 100 : 0;
  }

  const startHref = $derived(
    picked === 'all'
      ? `/learn/${info.slug}/hsk/${levelId}/review`
      : current
        ? `/learn/${info.slug}/hsk/${levelId}/lesson/${current.no}`
        : '',
  );

  /** Số liệu khối Tổng hợp của cấp, mỗi phần một kiểu. */
  const reviewCount = $derived.by(() => {
    const rv = data?.review;
    if (!rv) return 0;
    return part === 'bt' ? rv.bt.count : part === 'lv' ? rv.lv.charCount : part === 'ht' ? rv.ht.dialogueCount : part === 'np' ? rv.np.pointCount : 0;
  });
</script>

<svelte:head><title>{info.name} · {cap.ten(levelId)} · Zìkǎ</title></svelte:head>

<!-- Nhãn góc phải thẻ bài: dấu triện “完” khi xong, không thì nhãn trạng thái (đang làm, điểm, sắp có). -->
{#snippet nhan(l: LessonItem, m: boolean)}
  {@const st = statusOf(l)}
  {#if st.dot}
    <span class="dau-xong" lang="zh" title="Đã {texts.past} xong">完</span>
  {:else if st.label}
    <span class={st.cls} style={m ? '' : 'height: 22px; font-size: 11px; padding: 0 7px'}>{st.label}</span>
  {/if}
{/snippet}

{#snippet lessonCard(l: LessonItem, m: boolean)}
  {@const p = progressOf(prog, l.id)}
  {@const soon = countOf(l) === 0}
  {#if m}
    <!-- Điện thoại: hàng ngang như thẻ bài Lật thẻ — ô số bài, tên + mô tả + vạch, nhãn bên phải. -->
    <button
      type="button"
      class="les pl-hang"
      class:on={picked === l.id}
      class:done={p?.status === 'done'}
      class:soon
      disabled={soon}
      aria-pressed={picked === l.id}
      onclick={() => (picked = l.id)}
    >
      <span class="pl-so bignum">{l.no}</span>
      <span class="pl-giua">
        <span class="pl-ten">{l.title}</span>
        <span class="pl-phu">{subOf(l)}</span>
        {#if !soon}<Bar pct={pctOf(p)} h={7} />{/if}
      </span>
      <span class="pl-cuoi">{@render nhan(l, true)}</span>
    </button>
  {:else}
    <button
      type="button"
      class="les"
      class:on={picked === l.id}
      class:done={p?.status === 'done'}
      class:soon
      disabled={soon}
      aria-pressed={picked === l.id}
      onclick={() => (picked = l.id)}
    >
      <span class="pl-dau">
        <span class="disp" style="font-size: 13px; font-weight: 800; color: var(--text-3)">Bài {l.no}</span>
        {@render nhan(l, false)}
      </span>
      <span class="pl-ten">{l.title}</span>
      <!-- Mô tả Ngữ pháp là danh sách cấu trúc, có thể dài: cho 2 dòng rồi cắt, không đẩy thẻ cao lệch. -->
      <span class="pl-phu">{subOf(l)}</span>
      {#if !soon}<span style="margin-top: auto"><Bar pct={pctOf(p)} h={7} /></span>{/if}
    </button>
  {/if}
{/snippet}

{#snippet allCard(m: boolean)}
  {#if reviewCount > 0 || part === 'kt'}
    <button type="button" class="les les-all" class:on={picked === 'all'} aria-pressed={picked === 'all'} onclick={() => (picked = 'all')}>
      <span class="ib" style="width: {m ? 44 : 56}px; height: {m ? 44 : 56}px; background: var(--surface); border-radius: 14px; border-width: 2px"><Icon name="cards" size={m ? 22 : 26} /></span>
      <span style="flex: 1 1 240px; min-width: 0; display: flex; flex-direction: column; gap: 2px">
        <b class="disp" style="font-size: {m ? 16 : 18}px; font-weight: 800">{texts.allTitle(levelId)}</b>
        <span style="font-size: 13.5px">{texts.allDesc(levelId, reviewCount, lessons.length)}</span>
      </span>
      {#if !m}
        <span style="flex: 0 1 200px; display: flex; flex-direction: column; gap: 6px">
          <span class="num" style="font-size: 13px; font-weight: 700">{doneCount} / {lessons.length} bài đã {texts.past} xong</span>
          <Bar value={doneCount} total={lessons.length} trackStyle="background: var(--surface)" />
        </span>
      {/if}
    </button>
  {/if}
{/snippet}

<!-- Cột chi tiết (máy tính): đầu khối giống Lật thẻ — ô số cấp, dòng nhỏ, tên, số lượng; rồi ô trạng thái. -->
{#snippet panel()}
  <aside class="block" style="flex: 1 1 340px; min-width: 0; padding: 22px 24px; display: flex; flex-direction: column; gap: 16px">
    {#if picked === 'all'}
      <div style="display: flex; align-items: center; gap: 14px">
        <span class="ib lv{levelId}" style="width: 60px; height: 60px; border-radius: 16px"><span class="bignum" style="font-size: 30px">{levelId}</span></span>
        <div style="min-width: 0">
          <p class="muted" style="font-size: 13px; font-weight: 700">{cap.ten(levelId)} · cuối {cap.tu}</p>
          <h2 style="font-size: 24px; font-weight: 800; line-height: 1.15">{texts.allTitle(levelId)}</h2>
        </div>
      </div>
      <p class="muted" style="font-size: 14px">{texts.allDesc(levelId, reviewCount, lessons.length)}</p>
      <div class="pl-tt">
        <span class="num" style="font-size: 13.5px; font-weight: 700">{doneCount} / {lessons.length} bài đã {texts.past} xong</span>
        <Bar value={doneCount} total={lessons.length} />
      </div>
      <ul style="display: flex; flex-direction: column; gap: 6px">
        {#each texts.allLines(reviewCount, lessons.length, { minutes: Math.round((data?.review.kt.mockTests[0]?.durationSec ?? 3300) / 60) }) as line (line)}
          <li style="display: flex; gap: 8px; font-size: 14px"><Icon name="check" size={16} /> {line}</li>
        {/each}
      </ul>
      <a href={startHref} class="btn btn-son btn-lg" style="width: 100%">{texts.allStart}</a>
    {:else if current}
      <div style="display: flex; align-items: center; gap: 14px">
        <span class="ib lv{levelId}" style="width: 60px; height: 60px; border-radius: 16px"><span class="bignum" style="font-size: 30px">{levelId}</span></span>
        <div style="min-width: 0">
          <p class="muted" style="font-size: 13px; font-weight: 700">{cap.ten(levelId)} · Bài {current.no}</p>
          <h2 style="font-size: 24px; font-weight: 800; line-height: 1.15">{current.title}</h2>
          <p class="num" style="font-size: 14px; font-weight: 600">{subOf(current)}</p>
        </div>
      </div>
      {#if current.sampleText}<p class="hz" lang="zh" style="font-size: 16px; color: var(--text-2)">{current.sampleText}</p>{/if}

      <div class="pl-tt">
        {#if part === 'kt' && currentProg?.bestScore != null}
          <span style="font-size: 13.5px; font-weight: 700">Điểm cao nhất: {num(currentProg.bestScore)} / {num(currentProg.maxScore ?? 0)}</span>
        {:else if currentProg && currentProg.position > 0 && currentProg.total > 0}
          <span class="num" style="font-size: 13.5px; font-weight: 700">Đang ở {soHienThi(currentProg.position, currentProg.total)} / {currentProg.total}</span>
        {:else if currentProg?.status === 'done'}
          <span style="font-size: 13.5px; font-weight: 700; color: var(--green-text)">Đã {texts.past} xong · làm lại để ôn</span>
        {:else}
          <span class="muted" style="font-size: 13.5px; font-weight: 700">Chưa {texts.past}</span>
        {/if}
        <Bar pct={pctOf(currentProg)} />
      </div>

      {#if countOf(current) === 0}
        <p class="muted" style="font-size: 14px">Phần này của bài chưa soạn xong.</p>
      {:else}
        <a href={startHref} class="btn btn-son btn-lg" style="width: 100%">
          {currentProg && currentProg.position > 0 ? texts.contStart : texts.nextStart}
        </a>
      {/if}
    {/if}
  </aside>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/{info.slug}" class="btn btn-bare" aria-label="Về chọn {cap.tu}"><Icon name="chev" size={22} style="transform: rotate(180deg)" /></a>
      <span class="lv{levelId}" style="flex: none; padding: 4px 10px; border: 1px solid var(--line); border-radius: 10px; font-weight: 800; font-size: 13px">{cap.ten(levelId)}</span>
      <!-- Tên phần dài ("Cấu trúc ngữ pháp") được co lại bằng dấu …, không đẩy nút ra ngoài màn 375px. -->
      <b style="flex: 1 1 auto; min-width: 0; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{info.name}</b>
      <span style="flex: none; display: inline-flex; align-items: center; gap: 8px"><NotificationBell size={40} /><Switcher current={part} /></span>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">
      {#if failed}
        <EmptyState title="Không tải được danh sách bài.">
          {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
        </EmptyState>
      {:else if data === null}
        <Skeleton h="64px" r={16} /><Skeleton h="72px" r={16} /><Skeleton h="72px" r={16} /><Skeleton h="72px" r={16} />
      {:else}
        <div class="lv{levelId} lv-nen" style="border: 1px solid var(--line); border-radius: 16px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; --mau: var(--hsk{levelId})">
          <span class="lv-hoa" lang="zh" aria-hidden="true" style="right: 8px; top: -14px; font-size: 92px">{SO_HAN[levelId]}</span>
          <div style="display: flex; justify-content: space-between; gap: 10px; font-size: 13px; font-weight: 700">
            <span class="num">{lessons.length} bài · {num(tongSo)} {donVi}</span>
            <span class="num">{doneCount}/{lessons.length} bài đã {texts.past}</span>
          </div>
          <Bar value={doneCount} total={lessons.length} trackStyle="background: var(--surface)" />
        </div>
        {#if lessons.length === 0}
          <EmptyState title="{cap.tuHoa} này chưa có bài nào." desc="Admin đang soạn, bạn quay lại sau nhé." />
        {:else}
          <div style="display: flex; justify-content: space-between; align-items: baseline">
            <h2 class="h2">Các bài</h2>
            <span class="muted num" style="font-size: 13px; font-weight: 600">{doneCount}/{lessons.length} bài xong</span>
          </div>
          <div style="display: flex; flex-direction: column; gap: 10px">
            {#each lessons as l (l.id)}{@render lessonCard(l, true)}{/each}
            {@render allCard(true)}
          </div>
        {/if}
      {/if}
    </main>
    {#if startHref && (picked === 'all' || current)}
      <div class="msheet">
        <div style="display: flex; align-items: center; gap: 12px">
          <span class="ib lv{levelId}" style="width: 46px; height: 46px; border-radius: 12px"><span class="bignum" style="font-size: 22px">{levelId}</span></span>
          <div style="flex: 1; min-width: 0">
            <p class="muted" style="font-size: 12px; font-weight: 700">{picked === 'all' ? `${cap.ten(levelId)} · Tổng hợp` : `${cap.ten(levelId)} · Bài ${current?.no}`}</p>
            <b style="display: block; font-size: 16px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{picked === 'all' ? texts.allTitle(levelId) : current?.title}</b>
            <p class="muted" style="font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
              {picked === 'all' ? texts.allDesc(levelId, reviewCount, lessons.length) : current ? subOf(current) : ''}
            </p>
          </div>
        </div>
        <a href={startHref} class="btn btn-son btn-lg" style="width: 100%">
          {picked === 'all' ? texts.allStart : currentProg && currentProg.position > 0 ? texts.contStart : texts.nextStart}
        </a>
      </div>
    {/if}
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current={part} />
  <main class="page page-wide" style="max-width: 1240px; display: flex; flex-direction: column; gap: 24px">
    <nav aria-label="Đường dẫn" class="crumb">
      <a href="/learn/{info.slug}">{info.name}</a><span>/</span><b>{cap.ten(levelId)}</b>
    </nav>

    {#if failed}
      <EmptyState title="Không tải được danh sách bài.">
        {#snippet action()}<button type="button" class="btn" onclick={load}>Thử lại</button>{/snippet}
      </EmptyState>
    {:else if data === null}
      <Skeleton h="130px" r={24} />
      <Skeleton h="400px" r={22} />
    {:else}
      <!-- Khung đầu tô màu cấp, chép đúng khuôn màn Chọn bài của Lật thẻ. -->
      <section class="lv{levelId} lv-nen" style="border: 1px solid var(--line); border-radius: 24px; box-shadow: var(--sh-md); padding: 22px 26px; display: flex; flex-wrap: wrap; gap: 18px 32px; align-items: center; --mau: var(--hsk{levelId})">
        <span class="lv-hoa" lang="zh" aria-hidden="true" style="right: 22%; top: -34px; font-size: 200px">{SO_HAN[levelId]}</span>
        <BackLink href="/learn/{info.slug}" label="Chọn {cap.tu}" />
        <div style="display: flex; align-items: baseline; gap: 6px"><span class="disp" style="font-size: 20px; font-weight: 800">{cap.nhan}</span><span class="bignum" style="font-size: 72px">{levelId}</span></div>
        <div style="flex: 1 1 300px; display: flex; flex-direction: column; gap: 8px">
          <h1 style="font-size: 30px; line-height: 1.2">{info.name}</h1>
          <p class="num" style="font-weight: 600">{lessons.length} bài · {num(tongSo)} {donVi}</p>
          <Bar value={doneCount} total={lessons.length} trackStyle="background: var(--surface); max-width: 480px" />
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px">
          <span class="bignum" style="font-size: 44px">{doneCount}/{lessons.length}</span>
          <span style="font-size: 13px; font-weight: 700">bài đã {texts.past} xong</span>
        </div>
      </section>

      {#if lessons.length === 0}
        <EmptyState title="{cap.tuHoa} này chưa có bài nào." desc="Admin đang soạn, bạn quay lại sau nhé." />
      {:else}
        <div style="display: flex; flex-wrap: wrap; gap: 28px; align-items: flex-start">
          <section style="flex: 999 1 620px; min-width: 0; display: flex; flex-direction: column; gap: 14px" aria-label="Các bài">
            <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px">
              <h2 class="h2">Các bài</h2>
              <span class="muted num" style="font-size: 14px; font-weight: 600">{doneCount} / {lessons.length} bài đã {texts.past} xong</span>
            </div>
            <div class="pl-luoi">
              {#each lessons as l (l.id)}{@render lessonCard(l, false)}{/each}
              {@render allCard(false)}
            </div>
          </section>
          {@render panel()}
        </div>
      {/if}
    {/if}
  </main>
{/if}

<style>
  /* Tiền tố pl- (PartLevel): app.css đã có .les-grid, .row, .tag… — tránh bẫy số 1. */
  .pl-luoi {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(180px, 100%), 1fr));
    gap: 12px;
  }
  .pl-dau {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
    min-height: 22px;
  }
  .pl-ten {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.25;
  }
  .pl-phu {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-2);
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
  }
  .les.soon .pl-phu {
    color: inherit;
  }
  /* Ô trạng thái trong cột chi tiết, cùng kiểu ô "Đang ở thẻ…" của Lật thẻ. */
  .pl-tt {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px 14px;
    background: var(--surface-2);
    border-radius: 14px;
  }

  /* Bản điện thoại: hàng ngang. */
  .pl-hang {
    flex-direction: row;
    align-items: center;
    gap: 12px;
  }
  .pl-so {
    width: 40px;
    height: 40px;
    flex: none;
    display: grid;
    place-items: center;
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: 20px;
    background: var(--surface);
  }
  .pl-hang.done .pl-so {
    background: var(--bo-paper);
  }
  .pl-giua {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .pl-cuoi {
    flex: none;
    display: flex;
    align-items: center;
  }
</style>
