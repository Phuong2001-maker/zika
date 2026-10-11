<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { api, ApiError } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Thi thử cuối cấp — danh sách đề (07 §7, 04 §4.9 part = kt).
  interface Mock { id: number; title: string; questionCount: number; durationSec: number; maxPoints: number; passPoints: number }
  interface Summary { bestScore: number | null; maxScore: number | null; passed: boolean; attemptsUsed: number }

  const levelId = $derived(Number(page.params.level));

  let mocks = $state<Mock[] | null>(null);
  let sums = $state<Record<number, Summary>>({});
  let failed = $state(false);
  let starting = $state(0);

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.get<{ mockTests: Mock[] }>(`/learn/levels/${levelId}/review/kt`);
      mocks = res.mockTests;
      // Lấy điểm cao nhất của từng đề để hiện trên thẻ.
      const entries = await Promise.all(
        res.mockTests.map(async (m) => [m.id, await api.get<Summary>(`/tests/mock/${m.id}/summary`)] as const),
      );
      sums = Object.fromEntries(entries);
      heartbeat.setActivity({ part: 'kt', screen: 'review', level: levelId });
    } catch {
      failed = true;
    }
  }
  onMount(load);

  async function start(m: Mock): Promise<void> {
    starting = m.id;
    try {
      const res = await api.post<{ attemptId?: number; resultAttemptId?: number }>('/tests/start', { mockTestId: m.id });
      const id = res.attemptId ?? res.resultAttemptId;
      if (id) void goto(`/learn/tests/hsk/${levelId}/mock/${m.id}`);
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không mở được đề thi.');
    } finally {
      starting = 0;
    }
  }
</script>

<svelte:head><title>Thi thử HSK {levelId} · Zìkǎ</title></svelte:head>

{#snippet body(_m: boolean)}
  {#if failed}
    <EmptyState title="Không tải được đề thi thử.">
      {#snippet action()}<a href="/learn/tests/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if mocks === null}
    <Skeleton h="220px" r={16} />
  {:else if mocks.length === 0}
    <EmptyState title="Cấp này chưa có đề thi thử." desc="Admin đang soạn, bạn quay lại sau nhé." />
  {:else}
    <div class="grid">
      {#each mocks as mk (mk.id)}
        {@const s = sums[mk.id]}
        <article class="block card">
          <h2 class="h2">{mk.title}</h2>
          <p class="muted num" style="font-size: 14px">
            {num(mk.questionCount)} câu · khoảng {Math.round(mk.durationSec / 60)} phút · đạt từ {mk.passPoints}/{mk.maxPoints} điểm
          </p>
          <ul style="display: flex; flex-direction: column; gap: 4px; font-size: 14px">
            <li style="display: flex; gap: 8px"><Icon name="check" size={16} /> Phần nghe và phần đọc như đề thật</li>
            <li style="display: flex; gap: 8px"><Icon name="check" size={16} /> Chấm điểm ngay khi nộp</li>
          </ul>
          {#if s?.bestScore != null}
            <p style="font-size: 14px; font-weight: 700; color: {s.passed ? 'var(--green-text)' : 'var(--text)'}">
              Điểm cao nhất: {num(s.bestScore)} / {num(s.maxScore ?? mk.maxPoints)} · đã thi {s.attemptsUsed} lần
            </p>
          {/if}
          <button type="button" class="btn btn-son btn-lg" disabled={starting === mk.id} onclick={() => start(mk)}>
            {starting === mk.id ? 'Đang mở…' : 'Bắt đầu thi thử'}
          </button>
        </article>
      {/each}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/tests/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Thi thử HSK {levelId}</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/tests/hsk/{levelId}">Kiểm tra · HSK {levelId}</a> / <b>Thi thử</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="kt" /><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 860px; display: flex; flex-direction: column; gap: 16px">
    <h1 style="font-size: 28px">Thi thử HSK {levelId}</h1>
    {@render body(false)}
  </main>
{/if}

<style>
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr));
    gap: 16px;
  }
  .card {
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
</style>
