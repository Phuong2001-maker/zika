<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { api, ApiError } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';

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

<!-- Một khung chứa trọn danh sách đề (khuôn chung của các màn học); mỗi đề là một ô con. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    <div class="zk-khung-dau">
      <h2 class="zk-khung-ten">Chọn đề thi thử</h2>
      {#if mocks && mocks.length > 0}<span class="zk-so">{mocks.length} đề</span>{/if}
    </div>
    {#if mocks && mocks.length === 0}
      <EmptyState title="{cap.tuHoa} này chưa có đề thi thử." desc="Admin đang soạn, bạn quay lại sau nhé." />
    {:else if mocks}
      <div class="kt-ds-de">
        {#each mocks as mk (mk.id)}
          {@const s = sums[mk.id]}
          <article class="zk-o kt-de">
            <h3 class="kt-de-ten">{mk.title}</h3>
            <p class="muted num" style="font-size: 14px">
              {num(mk.questionCount)} câu · khoảng {Math.round(mk.durationSec / 60)} phút · đạt từ {mk.passPoints}/{mk.maxPoints} điểm
            </p>
            <ul class="kt-de-y">
              <li><Icon name="check" size={16} /> Phần nghe và phần đọc như đề thật</li>
              <li><Icon name="check" size={16} /> Chấm điểm ngay khi nộp</li>
            </ul>
            {#if s?.bestScore != null}
              <p style="font-size: 14px; font-weight: 700; color: {s.passed ? 'var(--green-text)' : 'var(--text)'}">
                Điểm cao nhất: {num(s.bestScore)} / {num(s.maxScore ?? mk.maxPoints)} · đã thi {s.attemptsUsed} lần
              </p>
            {/if}
            <button type="button" class="btn btn-son btn-lg kt-de-nut" disabled={starting === mk.id} onclick={() => start(mk)}>
              {starting === mk.id ? 'Đang mở…' : 'Bắt đầu thi thử'}
            </button>
          </article>
        {/each}
      </div>
    {/if}
  </section>
{/snippet}

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Không tải được đề thi thử.">
      {#snippet action()}<a href="/learn/tests/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if mocks === null}
    <Skeleton h="260px" r={22} />
  {:else}
    {@render khung(m)}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <MTopbar current="kt" back="/learn/tests/hsk/{levelId}" backLabel="Về danh sách bài" title="Thi thử HSK {levelId}" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">{@render body(true)}</main>
  </div>
{:else}
  <Topbar current="kt">
    <nav class="crumb"><a href="/learn/tests/hsk/{levelId}">Kiểm tra · {cap.ten(levelId)}</a> / <b>Thi thử</b></nav>
  </Topbar>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    <DauBai href="/learn/tests/hsk/{levelId}" kicker="{cap.ten(levelId)} · Kiểm tra cuối {cap.tu}" title="Thi thử HSK {levelId}" />
    {@render body(false)}
  </main>
{/if}

<style>
  .kt-ds-de {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
    gap: 14px;
  }
  .kt-de {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px 18px;
  }
  .kt-de-ten {
    font-size: 17px;
    font-weight: 800;
    overflow-wrap: break-word;
  }
  .kt-de-y {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 14px;
  }
  .kt-de-y li {
    display: flex;
    gap: 8px;
  }
  /* Nút đặt cuối ô để các ô cùng hàng có nút thẳng hàng dù nội dung dài ngắn khác nhau. */
  .kt-de-nut {
    margin-top: auto;
  }
</style>

