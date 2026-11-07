<script lang="ts">
  import { cap } from '$lib/stores/cap.svelte';
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import { speak, stopSpeak } from '$lib/learn/speech';

  // Nghe liền các hội thoại của cả cấp (04 §4.9 part = ht).
  interface Speaker { id: number; name: string; avatarChar: string; color: string }
  interface Line { id: number; speakerId: number; zh: string; pinyin: string; vi: string; audioUrl: string | null }
  /** Một bài khoá trong danh sách ôn; `id` là của bài khoá, một bài có thể có nhiều. */
  interface Dlg { id: number; no: number; lessonId: number; lessonNo: number; lessonTitle: string; title: string; scene: string; speakers: Speaker[]; lines: Line[] }

  const levelId = $derived(Number(page.params.level));

  let list = $state<Dlg[] | null>(null);
  let failed = $state(false);
  let openId = $state<number | null>(null);
  let playing = $state(false);
  let showPy = $state(true);
  let showVi = $state(true);

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.get<{ dialogues: Dlg[] }>(`/learn/levels/${levelId}/review/ht`);
      list = res.dialogues;
      openId = res.dialogues[0]?.id ?? null;
      showPy = settings.value.dialogue.showPinyin;
      showVi = settings.value.dialogue.showVi;
      heartbeat.setActivity({ part: 'ht', screen: 'review', level: levelId });
    } catch {
      failed = true;
    }
  }
  onMount(load);

  /** Nghe liền cả đoạn như podcast. */
  async function playAll(d: Dlg): Promise<void> {
    playing = true;
    for (const l of d.lines) {
      if (!playing) break;
      await speak(l.zh, l.audioUrl);
      await new Promise((r) => setTimeout(r, 800));
    }
    playing = false;
  }

  function stop(): void {
    playing = false;
    stopSpeak();
  }

  const speakerOf = (d: Dlg, id: number): Speaker | undefined => d.speakers.find((s) => s.id === id);
</script>

<svelte:head><title>Ôn hội thoại {cap.ten(levelId)} · Zìkǎ</title></svelte:head>

<!-- Một khung chứa cả danh sách: Pinyin | Nghĩa (cùng kiểu nút với màn đọc bài) ở đầu khung,
     mỗi bài khoá một ô nền nhạt mở ra được. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if list === null || list.length === 0}
      <EmptyState title="{cap.tuHoa} này chưa có hội thoại nào." />
    {:else}
      <div class="zk-khung-dau">
        <h2 class="zk-khung-ten">{list.length} bài khoá</h2>
        <div class="ht-cong-cu">
          {#if playing}
            <button type="button" class="btn btn-sm" class:ht-nut-m={m} onclick={stop}><Icon name="x" size={15} /> Dừng</button>
          {/if}
          <div class="seg">
            <button type="button" class:on={showPy} aria-pressed={showPy} onclick={() => (showPy = !showPy)}>Pinyin</button>
            <button type="button" class:on={showVi} aria-pressed={showVi} onclick={() => (showVi = !showVi)}>Nghĩa</button>
          </div>
        </div>
      </div>

      {#each list as d (d.id)}
        <div class="zk-o ht-muc">
          <div class="ht-muc-dau">
            <button type="button" class="btn btn-bare btn-sm" class:ht-mo-m={m} aria-label="Mở hoặc đóng" aria-expanded={openId === d.id}
                    onclick={() => (openId = openId === d.id ? null : d.id)}>
              <Icon name={openId === d.id ? 'down' : 'chev'} size={18} />
            </button>
            <div class="ht-muc-chu">
              <b style="font-size: 16px">{d.title}</b>
              <p class="muted" style="font-size: 13px">Bài {d.lessonNo} · {d.lessonTitle} · {d.lines.length} câu</p>
            </div>
            <div class="ht-muc-nut">
              <button type="button" class="btn btn-sm" class:ht-nut-m={m} disabled={playing} onclick={() => playAll(d)}>
                <Icon name="play" size={15} /> Nghe liền
              </button>
              <a href="/learn/dialogues/hsk/{levelId}/lesson/{d.lessonNo}" class="btn btn-sm btn-paper" class:ht-nut-m={m}>Mở bài</a>
            </div>
          </div>

          {#if openId === d.id}
            <ul style="display: flex; flex-direction: column; gap: 8px">
              {#each d.lines as l (l.id)}
                {@const sp = speakerOf(d, l.speakerId)}
                <li style="display: flex; gap: 10px">
                  <span class="who hz" lang="zh" style="background: {sp?.color ?? 'var(--surface)'}{sp?.color ? '; color: #1F2430' : ''}; width: 34px; height: 34px; font-size: 14px">
                    {sp?.avatarChar ?? '?'}
                  </span>
                  <button type="button" class="bub" style="flex: 1; min-width: 0; text-align: left; cursor: pointer" onclick={() => speak(l.zh, l.audioUrl)}>
                    <span class="hz" lang="zh" style="font-size: {m ? 17 : 19}px">{l.zh}</span>
                    {#if showPy && l.pinyin}<span class="muted" style="font-size: 13px">{l.pinyin}</span>{/if}
                    {#if showVi && l.vi}<span style="font-size: 13.5px">{l.vi}</span>{/if}
                  </button>
                </li>
              {/each}
            </ul>
          {/if}
        </div>
      {/each}
    {/if}
  </section>
{/snippet}

{#snippet loi()}
  <EmptyState title="{cap.tuHoa} này chưa có hội thoại nào.">
    {#snippet action()}<a href="/learn/dialogues/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
  </EmptyState>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="chev" size={20} style="transform: rotate(180deg)" /></a>
      <span style="flex: 1; min-width: 0; text-align: center">
        <b style="display: block; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">Ôn hội thoại</b>
        <span class="muted" style="font-size: 12px">{cap.ten(levelId)} · Ôn cả {cap.tu}</span>
      </span>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">
      {#if failed}
        {@render loi()}
      {:else if list === null}
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
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/dialogues/hsk/{levelId}">Hội thoại · {cap.ten(levelId)}</a> / <b>Ôn cả {cap.tu}</b></nav>
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    {#if failed}
      {@render loi()}
    {:else if list === null}
      <Skeleton h="520px" r={22} />
    {:else}
      <DauBai
        href="/learn/dialogues/hsk/{levelId}"
        kicker="{cap.ten(levelId)} · Ôn cả {cap.tu} · {list.length} bài khoá"
        title="Ôn hội thoại {cap.ten(levelId)}"
      />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  .ht-cong-cu {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .ht-muc {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .ht-muc-dau {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  /* Chừa chỗ tối thiểu cho tên bài: hẹp hơn thì hai nút tự xuống dòng thay vì ép tên còn một chữ. */
  .ht-muc-chu {
    flex: 1 1 160px;
    min-width: 0;
  }
  .ht-muc-nut {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
  }
  /* Điện thoại: đủ 44px cho ngón tay. */
  .ht-nut-m {
    min-height: 44px;
  }
  /* Hai lớp cho chắc thắng .btn-bare.btn-sm của app.css (cùng độ ưu tiên thì phụ thuộc thứ tự nạp). */
  .ht-muc-dau .ht-mo-m {
    width: 44px;
    min-height: 44px;
  }
</style>
