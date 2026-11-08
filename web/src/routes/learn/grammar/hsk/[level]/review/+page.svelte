<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';

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

<svelte:head><title>Tổng hợp ngữ pháp {cap.ten(levelId)} · Zìkǎ</title></svelte:head>

<!-- Một khung chứa cả bảng tra: ô tìm ở đầu khung, mỗi bài một nhóm, mỗi cấu trúc một ô nền nhạt. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    <div class="zk-khung-dau">
      <h2 class="zk-khung-ten">Tra cấu trúc</h2>
      <span class="ico-in th-tim" class:th-tim-m={m}>
        <Icon name="search" size={16} />
        <input class="inp" bind:value={q} placeholder="Tìm cấu trúc, ví dụ…" aria-label="Tìm cấu trúc ngữ pháp" />
      </span>
    </div>

    {#if shown.length === 0}
      <EmptyState title={totalPoints === 0 ? `${cap.tuHoa} này chưa có điểm ngữ pháp nào.` : 'Không tìm thấy cấu trúc nào khớp.'} />
    {:else}
      {#each shown as g (g.lessonId)}
        <div class="th-bai">
          <div class="th-bai-dau">
            <h3 class="th-bai-ten">Bài {g.lessonNo} · {g.lessonTitle}</h3>
            <span class="zk-so">{g.points.length} cấu trúc</span>
          </div>
          {#each g.points as p (p.id)}
            <div class="zk-o th-diem">
              <div style="display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap">
                <b class="hz" lang="zh" style="font-size: 19px">{p.title}</b>
                {#if p.subtitle}<span class="muted" style="font-size: 13.5px">{p.subtitle}</span>{/if}
              </div>
              {#if p.blocks.length > 0}
                <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center">
                  {#each p.blocks as b, k (k)}
                    {#if k > 0}<span class="muted">+</span>{/if}
                    <span class="fblk" style="{b.color ? `background: ${b.color}; color: #1F2430; ` : ''}min-width: 70px; padding: 8px 10px">
                      <span style="font-size: 11px; font-weight: 700; color: {b.color ? '#4A505C' : 'var(--text-2)'}">{b.label}</span>
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
              <a href="/learn/grammar/hsk/{levelId}/lesson/{g.lessonNo}" class="btn-text th-xem" class:th-xem-m={m}>Xem chi tiết ›</a>
            </div>
          {/each}
        </div>
      {/each}
    {/if}
  </section>
{/snippet}

{#snippet loi()}
  <EmptyState title="{cap.tuHoa} này chưa có điểm ngữ pháp nào.">
    {#snippet action()}<a href="/learn/grammar/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
  </EmptyState>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <MTopbar current="np" back="/learn/grammar/hsk/{levelId}" backLabel="Về danh sách bài" title="Tổng hợp ngữ pháp" sub="{cap.ten(levelId)}{groups ? ` · ${totalPoints} cấu trúc` : ''}" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">
      {#if failed}
        {@render loi()}
      {:else if groups === null}
        <Skeleton h="420px" r={22} />
      {:else}
        {@render khung(true)}
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="np">
    <nav class="crumb"><a href="/learn/grammar/hsk/{levelId}">Ngữ pháp · {cap.ten(levelId)}</a> / <b>Tổng hợp</b></nav>
  </Topbar>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    {#if failed}
      {@render loi()}
    {:else if groups === null}
      <Skeleton h="520px" r={22} />
    {:else}
      <DauBai
        href="/learn/grammar/hsk/{levelId}"
        kicker="{cap.ten(levelId)} · Tổng hợp · {totalPoints} cấu trúc"
        title="Tổng hợp ngữ pháp {cap.ten(levelId)}"
      />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  /* Ô tìm nằm bên phải đầu khung; điện thoại thì xuống dòng chiếm hết bề ngang. */
  .th-tim {
    flex: 0 1 320px;
    min-width: 0;
  }
  .th-tim-m {
    flex: 1 1 100%;
  }
  /* Mỗi bài một nhóm, ngăn nhau bằng đường kẻ như các phần trong khung Lật thẻ. */
  .th-bai {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
  }
  .th-bai-dau {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px 12px;
    flex-wrap: wrap;
  }
  .th-bai-ten {
    min-width: 0;
    font-size: 16px;
    font-weight: 800;
    line-height: 1.3;
  }
  .th-diem {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .th-xem {
    align-self: flex-start;
    font-size: 13.5px;
  }
  /* Điện thoại: đủ 44px cho ngón tay. */
  .th-xem-m {
    min-height: 44px;
  }
</style>
