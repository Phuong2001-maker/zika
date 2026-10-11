<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Bảng tra ngữ pháp cả cấp (04 §4.9 part = np).
  interface Block { label: string; hz: string; color: string }
  interface Example { formula: string; zh: string; vi: string }
  interface Point { id: number; title: string; subtitle: string; blocks: Block[]; example: Example | null }
  interface Group { lessonId: number; lessonNo: number; lessonTitle: string; points: Point[] }

  const levelId = $derived(Number(page.params.level));

  let groups = $state<Group[] | null>(null);
  let failed = $state(false);
  let q = $state('');

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.get<{ groups: Group[] }>(`/learn/levels/${levelId}/review/np`);
      groups = res.groups;
      heartbeat.setActivity({ part: 'np', screen: 'review', level: levelId });
    } catch {
      failed = true;
    }
  }
  onMount(load);

  /** Lọc theo tên cấu trúc hoặc ví dụ. */
  const shown = $derived.by(() => {
    const k = q.trim().toLowerCase();
    if (k === '') return groups ?? [];
    return (groups ?? [])
      .map((g) => ({
        ...g,
        points: g.points.filter(
          (p) => p.title.toLowerCase().includes(k) || p.subtitle.toLowerCase().includes(k) || (p.example?.zh ?? '').includes(k),
        ),
      }))
      .filter((g) => g.points.length > 0);
  });
  const totalPoints = $derived((groups ?? []).reduce((s, g) => s + g.points.length, 0));
</script>

<svelte:head><title>Tổng hợp ngữ pháp HSK {levelId} · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Cấp này chưa có điểm ngữ pháp nào.">
      {#snippet action()}<a href="/learn/grammar/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if groups === null}
    <Skeleton h="300px" r={16} />
  {:else}
    <span class="ico-in">
      <Icon name="search" size={16} />
      <input class="inp" bind:value={q} placeholder="Tìm cấu trúc, ví dụ…" />
    </span>

    {#if shown.length === 0}
      <EmptyState title="Không tìm thấy cấu trúc nào khớp." />
    {:else}
      {#each shown as g (g.lessonId)}
        <section class="block" style="padding: {m ? 16 : 20}px; display: flex; flex-direction: column; gap: 12px">
          <h2 class="h2">Bài {g.lessonNo} · {g.lessonTitle}</h2>
          {#each g.points as p (p.id)}
            <div class="point">
              <div style="display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap">
                <b class="hz" lang="zh" style="font-size: 19px">{p.title}</b>
                {#if p.subtitle}<span class="muted" style="font-size: 13.5px">{p.subtitle}</span>{/if}
              </div>
              {#if p.blocks.length > 0}
                <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center">
                  {#each p.blocks as b, k (k)}
                    {#if k > 0}<span class="muted">+</span>{/if}
                    <span class="fblk" style="background: {b.color}; min-width: 70px; padding: 8px 10px">
                      <span class="muted" style="font-size: 11px; font-weight: 700">{b.label}</span>
                      <span class="hz" lang="zh" style="font-size: 17px">{b.hz}</span>
                    </span>
                  {/each}
                </div>
              {/if}
              {#if p.example}
                <p style="font-size: 14px">
                  {#if p.example.formula}<span class="muted num" style="font-size: 13px">{p.example.formula} · </span>{/if}
                  <span class="hz" lang="zh" style="font-size: 17px">{p.example.zh}</span>
                  {#if p.example.vi}<span class="muted"> — {p.example.vi}</span>{/if}
                </p>
              {/if}
              <a href="/learn/grammar/hsk/{levelId}/lesson/{g.lessonNo}" class="btn-text" style="font-size: 13.5px; align-self: flex-start">Xem chi tiết ›</a>
            </div>
          {/each}
        </section>
      {/each}
    {/if}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/grammar/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Tổng hợp ngữ pháp</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/grammar/hsk/{levelId}">Ngữ pháp · HSK {levelId}</a> / <b>Tổng hợp</b></nav>
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 880px; display: flex; flex-direction: column; gap: 16px">
    <div>
      <h1 style="font-size: 28px">Tổng hợp ngữ pháp HSK {levelId}</h1>
      <p class="muted" style="font-size: 14px">{totalPoints} cấu trúc của cả cấp, tra nhanh theo công thức.</p>
    </div>
    {@render body(false)}
  </main>
{/if}

<style>
  .point {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px 0;
    border-top: 1px solid var(--line-2);
  }
  .point:first-of-type {
    border-top: 0;
  }
</style>
