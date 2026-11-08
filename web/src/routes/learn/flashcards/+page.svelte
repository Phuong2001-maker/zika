<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { api } from '$lib/api/client';
  import type { HomeProgress, LevelInfo } from '$lib/api/types';
  import { session } from '$lib/stores/session.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Topbar from '$lib/components/Topbar.svelte';
  import LevelCard from '$lib/components/LevelCard.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import { taiChuBut } from '$lib/learn/hoa-tiet';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { soHienThi } from '$lib/learn/resume';

  // Bàn học — Lật thẻ · Chọn cấp (07 §4.1).
  heartbeat.setActivity({ part: 'lt', screen: 'home' });
  taiChuBut();

  let levels = $state<LevelInfo[] | null>(null);
  let home = $state<HomeProgress | null>(null);
  let failed = $state(false);

  async function load(): Promise<void> {
    failed = false;
    try {
      const [lv, hm] = await Promise.all([
        api.get<LevelInfo[]>('/learn/levels'),
        api.get<HomeProgress>('/progress/home', { query: { part: 'lt' } }),
      ]);
      levels = lv;
      home = hm;
    } catch {
      failed = true;
    }
  }
  onMount(load);

  const loading = $derived(!failed && (levels === null || home === null));
  const doneOf = (id: number): number => home?.levels.find((l) => l.levelId === id)?.done ?? 0;
  const greeting = $derived(session.user?.displayName || 'bạn');
  // Giáo trình của học viên chưa có bài ở cấp nào thì cấp đó cũng là "sắp có", không cho bấm vào trang rỗng.
  const moLevels = $derived((levels ?? []).filter((l) => l.visible && l.lessonCount > 0));
  const sapCo = $derived((levels ?? []).filter((l) => !l.visible || l.lessonCount === 0));

  // Nhãn 7 ngày gần nhất, ngày cuối là hôm nay (khớp thứ tự mảng `today.week` của API).
  const THU = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
  const nhanTuan = $derived.by(() => {
    const d = new Date().getDay();
    return Array.from({ length: 7 }, (_, k) => THU[(d + k + 1) % 7]);
  });
  const homNayDaHoc = $derived(home?.today?.week[6] ?? false);
  // Chữ Hán đầu tiên của bài học tiếp, in mờ thật to ở góc thẻ làm điểm nhấn.
  const chuNen = $derived([...(home?.continue?.sampleText ?? '')].find((c) => /\p{Script=Han}/u.test(c)) ?? '字');
</script>

<svelte:head><title>Bàn học · Zìkǎ</title></svelte:head>

<!-- Khối “Hôm nay”: việc nên làm ngay (học tiếp bài đang dở) + chuỗi ngày học để có lý do quay lại. -->
{#snippet homNay(m: boolean)}
  {#if home}
    <section class="hn" class:hn-m={m} aria-label="Hôm nay">
      {#if home.continue}
        {@const c = home.continue}
        <a href="/learn/flashcards/hsk/{c.levelId}/lesson/{c.lessonNo}" class="hn-tiep" style="--mau: var(--hsk{c.levelId}, var(--son))">
          <span class="hn-chu-nen" lang="zh" aria-hidden="true">{chuNen}</span>
          <span class="kicker hn-kicker">{c.position > 0 ? 'Học tiếp' : 'Bài tiếp theo'} · {cap.ten(c.levelId)} · Bài {c.lessonNo}</span>
          <span class="hn-ten">{c.title}</span>
          <span class="hz hn-mau" lang="zh">{c.sampleText}</span>
          <span class="hn-tien">
            <span class="num">{c.position > 0 ? `Thẻ ${soHienThi(c.position, c.total)}/${c.total}` : `${c.total} từ`}</span>
            <span class="hn-tien-bar"><Bar value={c.position} total={c.total} /></span>
          </span>
          <span class="btn btn-son hn-nut">{c.position > 0 ? 'Học tiếp' : 'Bắt đầu học'} </span>
        </a>
      {:else if moLevels.length === 0}
        <div class="hn-tiep hn-het">
          <span class="hn-ten">Giáo trình của bạn đang được soạn</span>
          <span class="muted" style="font-size: 14px">Có bài mới là bạn học được ngay tại đây.</span>
        </div>
      {:else}
        <div class="hn-tiep hn-het">
          <span class="stamp" style="font-size: 26px">完成</span>
          <span class="hn-ten">Bạn đã lật hết các bài đang có</span>
          <span class="muted" style="font-size: 14px">Ôn lại bằng bài Tổng hợp của từng {cap.tu}, bài mới sẽ hiện ở đây.</span>
        </div>
      {/if}

      {#if home.today}
        {@const t = home.today}
        <div class="hn-chuoi">
          <div class="hn-so">
            <span class="hn-so-lon num" class:hn-so-0={t.streak === 0}>{t.streak}</span>
            <span style="min-width: 0">
              <b style="display: block">ngày học liên tiếp</b>
              <span class="hn-goi">
                {#if t.streak === 0}Học một bài hôm nay để bắt đầu chuỗi.
                {:else if !homNayDaHoc}Học hôm nay để giữ chuỗi nhé.
                {:else}Hôm nay đã học, giữ vững nhé!{/if}
              </span>
            </span>
          </div>
          <ol class="hn-tuan" aria-label="7 ngày gần nhất">
            {#each t.week as co, k (k)}
              <li class:co class:nay={k === 6} aria-label="{nhanTuan[k]}{k === 6 ? ' (hôm nay)' : ''}: {co ? 'có học' : 'chưa học'}">
                <span class="hn-cham">{#if co}<Icon name="check" size={14} />{/if}</span>
                <span class="hn-thu">{nhanTuan[k]}</span>
              </li>
            {/each}
          </ol>
          <p class="hn-tu">Đã lật xong <b class="num">{t.words}</b> từ</p>
        </div>
      {/if}
    </section>
  {/if}
{/snippet}

<!-- Cấp chưa mở gom thành một dòng: 3 thẻ xám to chiếm nửa màn mà không bấm được. -->
{#snippet sapCoRow()}
  {#if sapCo.length > 0}
    <p class="hn-sapco">
      <span>Sắp có</span>
      {#each sapCo as l (l.id)}<span class="tag">{cap.ten(l.id)}</span>{/each}
      <span>· đang soạn</span>
    </p>
  {/if}
{/snippet}

{#snippet suggestCard()}
  {#if home && home.reviewSuggest}
    {@const s = home.reviewSuggest}
    <section class="suggest">
      <span class="ib" style="width: 46px; height: 46px; background: var(--surface); border-radius: 12px"><Icon name="cards" size={22} /></span>
      <span style="flex: 1; font-size: 14px"><b style="display: block">Tổng hợp {cap.ten(s.levelId)}</b>Bạn đã học xong {s.done}/{s.total} bài. Lật bài Tổng hợp để ôn cả {cap.tu}.</span>
      <a href="/learn/flashcards/hsk/{s.levelId}?review=1" class="btn btn-icon btn-sm" aria-label="Mở Tổng hợp {cap.ten(s.levelId)}"><Icon name="chev" size={18} /></a>
    </section>
  {/if}
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <MTopbar current="lt" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">
      <h1 style="font-size: 22px; line-height: 1.2">Chào {greeting}, hôm nay học gì?</h1>
      {#if failed}
        <EmptyState title="Không tải được dữ liệu.">
          {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
        </EmptyState>
      {:else if loading}
        <Skeleton h="220px" r={20} />
        <div class="lv-grid m"><Skeleton h="120px" r={20} /><Skeleton h="120px" r={20} /></div>
      {:else}
        {@render homNay(true)}
        <h2 class="hn-muc">{cap.chon}</h2>
        <section aria-label={cap.cacCap} class="lv-grid m">
          {#each moLevels as l (l.id)}
            <LevelCard level={l} done={doneOf(l.id)} current={l.id === home?.currentLevelId} mobile />
          {/each}
        </section>
        {@render sapCoRow()}
        {@render suggestCard()}
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="lt" />
  <main class="page" style="display: flex; flex-direction: column; gap: 24px">
    <h1 style="font-size: 32px; line-height: 1.2">Chào {greeting}, hôm nay học gì?</h1>

    {#if failed}
      <EmptyState title="Không tải được dữ liệu.">
        {#snippet action()}<button type="button" class="btn" onclick={load}>Thử lại</button>{/snippet}
      </EmptyState>
    {:else}
      {#if loading}<Skeleton h="240px" r={20} />{:else}{@render homNay(false)}{/if}
      <div style="display: flex; flex-direction: column; gap: 14px">
        <h2 class="hn-muc">{cap.chon}</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 28px; align-items: flex-start">
          <section aria-label={cap.cacCap} class="lv-grid" style="flex: 999 1 640px; min-width: 0">
            {#if loading}
              {#each Array.from({ length: 3 }) as _, i (i)}<Skeleton h="190px" r={20} />{/each}
            {:else}
              {#each moLevels as l (l.id)}
                <LevelCard level={l} done={doneOf(l.id)} current={l.id === home?.currentLevelId} />
              {/each}
            {/if}
          </section>
          {#if !loading && home?.reviewSuggest}
            <aside style="flex: 1 1 340px; min-width: 0; display: flex; flex-direction: column; gap: 18px">
              {@render suggestCard()}
            </aside>
          {/if}
        </div>
        {#if !loading}{@render sapCoRow()}{/if}
      </div>
    {/if}
  </main>
{/if}

<style>
  .lv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
    gap: 18px;
  }
  .lv-grid.m {
    /* minmax(0, …): `1fr` không co nhỏ hơn nội dung, thẻ HSK 4–6 làm lưới tràn ngang ở 375px. */
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
  }
  .suggest {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: var(--tim);
    border: 1px solid var(--line);
    border-radius: 20px;
  }

  /* ---- Khối “Hôm nay” ---- */
  .hn {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
    gap: 18px;
  }
  .hn-m {
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
  }
  .hn-tiep {
    position: relative;
    overflow: hidden;
    isolation: isolate;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 24px 26px;
    border: 1px solid var(--line);
    border-top: 4px solid var(--mau, var(--son));
    border-radius: 20px;
    background: var(--surface);
    box-shadow: var(--sh-sm);
    color: var(--text);
    text-decoration: none;
    transition: box-shadow 0.15s ease, transform 0.15s ease;
  }
  a.hn-tiep:hover {
    box-shadow: var(--sh-md);
    transform: translateY(-1px);
  }
  a.hn-tiep:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  /* Chữ Hán in mờ khổng lồ ở góc: nhận ra ngay đây là web học chữ Hán, mà không chen vào chữ. */
  .hn-chu-nen {
    position: absolute;
    z-index: -1;
    right: -14px;
    bottom: -46px;
    font: 400 210px/1 var(--font-brush);
    color: var(--mau, var(--son));
    opacity: 0.09;
    pointer-events: none;
    user-select: none;
  }
  .hn-kicker {
    font-size: 12px;
    color: var(--mau, var(--son-text));
    filter: saturate(1.1) brightness(0.85);
  }
  .hn-ten {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.2;
  }
  .hn-mau {
    font-size: 30px;
    line-height: 1.3;
  }
  .hn-tien {
    display: flex;
    align-items: center;
    gap: 12px;
    width: min(100%, 360px);
    margin-top: 4px;
    font-size: 13.5px;
    color: var(--text-2);
    white-space: nowrap;
  }
  .hn-tien-bar {
    flex: 1;
    min-width: 0;
  }
  .hn-nut {
    margin-top: 8px;
  }
  .hn-het {
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 12px;
  }

  .hn-chuoi {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 22px 22px 18px;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: var(--surface);
    box-shadow: var(--sh-sm);
  }
  .hn-so {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 15px;
  }
  .hn-so-lon {
    display: grid;
    place-items: center;
    flex: none;
    min-width: 64px;
    height: 64px;
    padding: 0 10px;
    border-radius: 16px;
    background: var(--son);
    color: #fff;
    font-size: 34px;
    font-weight: 800;
    box-shadow: 0 6px 16px -6px rgba(229, 72, 77, 0.55);
  }
  .hn-so-0 {
    background: var(--surface-3);
    color: var(--text-2);
    box-shadow: none;
  }
  .hn-goi {
    font-size: 13.5px;
    color: var(--text-2);
  }
  .hn-tuan {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .hn-tuan li {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }
  .hn-cham {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1.5px dashed var(--line-3);
    color: #fff;
  }
  .hn-tuan li.co .hn-cham {
    border: 0;
    background: var(--son);
  }
  .hn-tuan li.nay:not(.co) .hn-cham {
    border: 2px solid var(--son-line);
  }
  .hn-thu {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-3);
  }
  .hn-tuan li.nay .hn-thu {
    color: var(--son-text);
  }
  .hn-tu {
    padding-top: 14px;
    border-top: 1px solid var(--line);
    font-size: 14px;
    color: var(--text-2);
  }
  .hn-tu b {
    color: var(--text);
    font-size: 16px;
  }

  .hn-muc {
    font-size: 17px;
    font-weight: 800;
  }
  .hn-sapco {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 13.5px;
    color: var(--text-3);
  }

  /* Điện thoại: gọn lại, chữ Hán nền nhỏ hơn để không đè lên nút. */
  .hn-m .hn-tiep {
    padding: 18px 18px 20px;
  }
  .hn-m .hn-chu-nen {
    font-size: 150px;
    bottom: -34px;
  }
  .hn-m .hn-ten {
    font-size: 20px;
  }
  .hn-m .hn-mau {
    font-size: 24px;
  }
  .hn-m .hn-nut {
    align-self: stretch;
  }
  .hn-m .hn-chuoi {
    padding: 16px 16px 14px;
    gap: 14px;
  }
  .hn-m .hn-so-lon {
    min-width: 54px;
    height: 54px;
    font-size: 28px;
  }
</style>
