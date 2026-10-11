<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api/client';
  import type { HomeProgress, LevelInfo, Part } from '$lib/api/types';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { ago } from '$lib/utils/format';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import LevelCard from '$lib/components/LevelCard.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { PART, PART_TEXTS } from '$lib/parts';

  // Khuôn “Chọn cấp” dùng chung cho 5 phần: Bài tập, Kiểm tra, Luyện viết, Hội thoại, Ngữ pháp (07 §5.3).
  let { part }: { part: Exclude<Part, 'lt'> } = $props();

  const info = $derived(PART[part]);
  const texts = $derived(PART_TEXTS[part]);

  let levels = $state<LevelInfo[] | null>(null);
  let home = $state<HomeProgress | null>(null);
  let failed = $state(false);

  async function load(): Promise<void> {
    failed = false;
    try {
      const [lv, hm] = await Promise.all([
        api.get<LevelInfo[]>('/learn/levels'),
        api.get<HomeProgress>('/progress/home', { query: { part } }),
      ]);
      levels = lv;
      home = hm;
    } catch {
      failed = true;
    }
  }

  onMount(() => {
    heartbeat.setActivity({ part, screen: 'home' });
    void load();
  });

  const loading = $derived(!failed && (levels === null || home === null));
  const cont = $derived(home?.continue ?? null);
  const inProgress = $derived(cont !== null && cont.position > 0);
  const doneOf = (id: number): number => home?.levels.find((l) => l.levelId === id)?.done ?? 0;

  const contHref = $derived(cont ? `/learn/${info.slug}/hsk/${cont.levelId}/lesson/${cont.lessonNo}` : '');
</script>

<svelte:head><title>{info.name} · Zìkǎ</title></svelte:head>

{#snippet contBlock(m: boolean)}
  <section class="block" style="padding: {m ? 18 : 22}px {m ? 18 : 24}px; display: flex; flex-direction: column; gap: {m ? 12 : 16}px">
    <p class="kicker">{inProgress ? texts.contTitle : cont ? texts.nextTitle : 'Tuyệt vời'}</p>
    {#if cont}
      <div style="display: flex; align-items: center; gap: 14px">
        <span class="ib lv{cont.levelId}" style="width: {m ? 48 : 60}px; height: {m ? 48 : 60}px; border-radius: 16px">
          <span class="bignum" style="font-size: {m ? 24 : 30}px">{cont.levelId}</span>
        </span>
        <div style="min-width: 0">
          <p class="muted" style="font-size: 13px; font-weight: 700">HSK {cont.levelId} · Bài {cont.lessonNo}</p>
          <h2 style="font-size: {m ? 18 : 24}px; font-weight: 800; line-height: 1.15">{cont.title}</h2>
          {#if !m && cont.sampleText}<p class="hz" lang="zh" style="font-size: 16px; color: var(--text-2)">{cont.sampleText}</p>{/if}
        </div>
      </div>
      {#if inProgress}
        <div style="display: flex; flex-direction: column; gap: 6px">
          <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 700">
            <span>Đang ở {cont.position + 1} / {cont.total}</span>
            <span class="muted">{ago(cont.updatedAt)}</span>
          </div>
          <Bar value={cont.position} total={cont.total} />
        </div>
      {/if}
      <a href={contHref} class="btn btn-son btn-lg" style="width: 100%">{inProgress ? texts.contStart : texts.nextStart}</a>
      {#if !m}
        <a href="/learn/{info.slug}/hsk/{cont.levelId}" class="btn btn-text btn-sm" style="align-self: center">Xem các bài HSK {cont.levelId}</a>
      {/if}
    {:else}
      <p class="muted" style="font-size: 14px">Bạn đã {texts.past} hết mọi bài đang có.</p>
    {/if}
  </section>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 30px; height: 30px; font-size: 17px">字</span>Zìkǎ</a>
      <div style="margin-left: auto; display: flex; align-items: center; gap: 10px">
        <Switcher current={part} mobile />
        <Avatar size={44} />
      </div>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 18px">
      <div>
        <h1 style="font-size: 22px; line-height: 1.2">{info.name}</h1>
        <p class="muted" style="margin-top: 4px; font-size: 14px">Bấm vào một cấp để chọn bài.</p>
      </div>
      {#if failed}
        <EmptyState title="Không tải được dữ liệu.">
          {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
        </EmptyState>
      {:else if loading}
        <Skeleton h="150px" r={20} />
        <div class="lv-grid m"><Skeleton h="120px" r={16} /><Skeleton h="120px" r={16} /></div>
      {:else}
        {@render contBlock(true)}
        <section aria-label="Các cấp HSK" class="lv-grid m">
          {#each levels ?? [] as l (l.id)}
            <LevelCard level={l} done={doneOf(l.id)} current={l.id === home?.currentLevelId} slug={info.slug} past={texts.past} mobile />
          {/each}
        </section>
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current={part} />
  <main class="page" style="display: flex; flex-direction: column; gap: 28px">
    <div>
      <h1 style="font-size: 34px; line-height: 1.2">{info.name}</h1>
      <p class="muted" style="margin-top: 6px; max-width: 70ch">{texts.homeDesc}</p>
    </div>

    {#if failed}
      <EmptyState title="Không tải được dữ liệu.">
        {#snippet action()}<button type="button" class="btn" onclick={load}>Thử lại</button>{/snippet}
      </EmptyState>
    {:else}
      <div style="display: flex; flex-wrap: wrap; gap: 28px; align-items: flex-start">
        <section aria-label="Các cấp HSK" class="lv-grid" style="flex: 999 1 640px; min-width: 0">
          {#if loading}
            {#each Array.from({ length: 6 }) as _, k (k)}<Skeleton h="190px" r={16} />{/each}
          {:else}
            {#each levels ?? [] as l (l.id)}
              <LevelCard level={l} done={doneOf(l.id)} current={l.id === home?.currentLevelId} slug={info.slug} past={texts.past} />
            {/each}
          {/if}
        </section>
        <aside style="flex: 1 1 340px; min-width: 0; display: flex; flex-direction: column; gap: 18px">
          {#if loading}<Skeleton h="240px" r={20} />{:else}{@render contBlock(false)}{/if}
        </aside>
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
</style>
