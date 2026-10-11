<script lang="ts">
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
  import { speak, stopSpeak } from '$lib/learn/speech';

  // Nghe liền các hội thoại của cả cấp (04 §4.9 part = ht).
  interface Speaker { id: number; name: string; avatarChar: string; color: string }
  interface Line { id: number; speakerId: number; zh: string; pinyin: string; vi: string; audioUrl: string | null }
  interface Dlg { lessonId: number; lessonNo: number; lessonTitle: string; title: string; scene: string; speakers: Speaker[]; lines: Line[] }

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
      openId = res.dialogues[0]?.lessonId ?? null;
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

<svelte:head><title>Ôn hội thoại HSK {levelId} · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Cấp này chưa có hội thoại nào.">
      {#snippet action()}<a href="/learn/dialogues/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if list === null}
    <Skeleton h="300px" r={16} />
  {:else}
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
      <button type="button" class="btn btn-sm" class:apps-on={showPy} onclick={() => (showPy = !showPy)}>Pinyin</button>
      <button type="button" class="btn btn-sm" class:apps-on={showVi} onclick={() => (showVi = !showVi)}>Nghĩa</button>
      {#if playing}
        <button type="button" class="btn btn-sm" onclick={stop}><Icon name="x" size={15} /> Dừng</button>
      {/if}
    </div>

    {#each list as d (d.lessonId)}
      <section class="block" style="padding: {m ? 16 : 20}px; display: flex; flex-direction: column; gap: 10px">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap">
          <button type="button" class="btn btn-bare btn-sm" aria-label="Mở hoặc đóng"
                  onclick={() => (openId = openId === d.lessonId ? null : d.lessonId)}>
            <Icon name={openId === d.lessonId ? 'down' : 'right'} size={18} />
          </button>
          <div style="flex: 1; min-width: 0">
            <b style="font-size: 16px">{d.title}</b>
            <p class="muted" style="font-size: 13px">Bài {d.lessonNo} · {d.lessonTitle} · {d.lines.length} câu</p>
          </div>
          <button type="button" class="btn btn-sm" disabled={playing} onclick={() => playAll(d)}>
            <Icon name="play" size={15} /> Nghe liền
          </button>
          <a href="/learn/dialogues/hsk/{levelId}/lesson/{d.lessonNo}" class="btn btn-sm btn-paper">Mở bài</a>
        </div>

        {#if openId === d.lessonId}
          <ul style="display: flex; flex-direction: column; gap: 8px">
            {#each d.lines as l (l.id)}
              {@const sp = speakerOf(d, l.speakerId)}
              <li style="display: flex; gap: 10px">
                <span class="who hz" lang="zh" style="background: {sp?.color ?? 'var(--surface)'}; width: 34px; height: 34px; font-size: 14px">
                  {sp?.avatarChar ?? '?'}
                </span>
                <button type="button" class="bub" style="flex: 1; text-align: left; cursor: pointer" onclick={() => speak(l.zh, l.audioUrl)}>
                  <span class="hz" lang="zh" style="font-size: {m ? 17 : 19}px">{l.zh}</span>
                  {#if showPy && l.pinyin}<span class="muted" style="font-size: 13px">{l.pinyin}</span>{/if}
                  {#if showVi && l.vi}<span style="font-size: 13.5px">{l.vi}</span>{/if}
                </button>
              </li>
            {/each}
          </ul>
        {/if}
      </section>
    {/each}
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Ôn hội thoại</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/dialogues/hsk/{levelId}">Hội thoại · HSK {levelId}</a> / <b>Ôn cả cấp</b></nav>
    <div style="margin-left: auto"><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 860px; display: flex; flex-direction: column; gap: 16px">
    <h1 style="font-size: 28px">Ôn hội thoại HSK {levelId}</h1>
    {@render body(false)}
  </main>
{/if}
