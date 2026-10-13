<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api/client';
  import type { HomeProgress, LevelInfo } from '$lib/api/types';
  import { session } from '$lib/stores/session.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import LevelCard from '$lib/components/LevelCard.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Bàn học — Lật thẻ · Chọn cấp (07 §4.1).
  heartbeat.setActivity({ part: 'lt', screen: 'home' });

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
  const greeting = $derived(session.user?.displayName ?? '');
</script>

<svelte:head><title>Bàn học · Zìkǎ</title></svelte:head>

{#snippet suggestCard()}
  {#if home && home.reviewSuggest}
    {@const s = home.reviewSuggest}
    <section class="suggest">
      <span class="ib" style="width: 46px; height: 46px; background: var(--surface); border-radius: 12px"><Icon name="cards" size={22} /></span>
      <span style="flex: 1; font-size: 14px"><b style="display: block">Tổng hợp HSK {s.levelId}</b>Bạn đã học xong {s.done}/{s.total} bài. Lật bài Tổng hợp để ôn cả cấp.</span>
      <a href="/learn/flashcards/hsk/{s.levelId}?review=1" class="btn btn-icon btn-sm" aria-label="Mở Tổng hợp HSK {s.levelId}"><Icon name="right" size={18} /></a>
    </section>
  {/if}
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 30px; height: 30px; font-size: 17px">字</span>Zìkǎ</a>
      <div style="margin-left: auto; display: flex; align-items: center; gap: 10px">
        <Switcher current="lt" mobile />
        <Avatar size={44} />
      </div>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 18px">
      <div>
        <h1 style="font-size: 22px; line-height: 1.2">Hôm nay học cấp nào?</h1>
        <p class="muted" style="margin-top: 4px; font-size: 14px">Bấm vào một cấp để chọn bài.</p>
      </div>
      {#if failed}
        <EmptyState title="Không tải được dữ liệu.">
          {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
        </EmptyState>
      {:else if loading}
        <div class="lv-grid m"><Skeleton h="120px" r={20} /><Skeleton h="120px" r={20} /></div>
      {:else}
        <section aria-label="Các cấp HSK" class="lv-grid m">
          {#each levels ?? [] as l (l.id)}
            <LevelCard level={l} done={doneOf(l.id)} current={l.id === home?.currentLevelId} mobile />
          {/each}
        </section>
        {@render suggestCard()}
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="lt" />
  <main class="page" style="display: flex; flex-direction: column; gap: 28px">
    <div>
      <h1 style="font-size: 34px; line-height: 1.2">Hôm nay học cấp nào{greeting ? `, ${greeting}` : ''}?</h1>
      <p class="muted" style="margin-top: 6px">Mỗi cấp HSK chia thành nhiều bài. Bấm vào một cấp để xem các bài và chọn bài muốn lật.</p>
    </div>

    {#if failed}
      <EmptyState title="Không tải được dữ liệu.">
        {#snippet action()}<button type="button" class="btn" onclick={load}>Thử lại</button>{/snippet}
      </EmptyState>
    {:else}
      <div style="display: flex; flex-wrap: wrap; gap: 28px; align-items: flex-start">
        <section aria-label="Các cấp HSK" class="lv-grid" style="flex: 999 1 640px; min-width: 0">
          {#if loading}
            {#each Array.from({ length: 6 }) as _, i (i)}<Skeleton h="190px" r={20} />{/each}
          {:else}
            {#each levels ?? [] as l (l.id)}
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
    grid-template-columns: 1fr 1fr;
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
</style>
