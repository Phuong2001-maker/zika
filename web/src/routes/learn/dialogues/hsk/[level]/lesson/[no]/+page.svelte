<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
  import { completePart, lessonByNo, saveProgress } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Đọc hội thoại (07 §10).
  interface Speaker { id: number; name: string; avatarChar: string; color: string; roleVi: string }
  interface Line { id: number; speakerId: number; zh: string; highlight: string | null; pinyin: string; vi: string; audioUrl: string | null }
  /** Một bài khoá (课文一, 课文二…): mỗi cảnh có nhân vật và lời thoại riêng. */
  interface BaiKhoa { id: number; no: number; title: string; scene: string; speakers: Speaker[]; lines: Line[] }
  interface Data {
    lesson: LessonRef;
    dialogues: BaiKhoa[];
    next: NextLesson | null;
  }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<Data | null>(null);
  let failed = $state(false);
  let dangDoc = $state<string>('');   // 'chỉ số bài khoá:chỉ số câu' đang đọc
  let showPy = $state(true);
  let showVi = $state(true);
  let moTa = $state<Record<number, boolean>>({});   // bài khoá nào đang mở rộng phần tình huống
  let done = $state(false);

  const khoi = $derived(data?.dialogues ?? []);
  const tongCau = $derived(khoi.reduce((n, k) => n + k.lines.length, 0));

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) { failed = true; return; }
      const res = await api.get<Data>(`/learn/lessons/${l.id}/dialogue`);
      data = res;
      dangDoc = '';
      moTa = {};
      done = false;
      showPy = settings.value.dialogue.showPinyin;
      showVi = settings.value.dialogue.showVi;
      heartbeat.setActivity({ part: 'ht', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => { void levelId; void lessonNo; void load(); });

  /** Số câu đã đi qua tính từ đầu bài, để lưu tiến độ chung cho cả bài. */
  function viTri(ki: number, i: number): number {
    return khoi.slice(0, ki).reduce((n, k) => n + k.lines.length, 0) + i + 1;
  }

  function playLine(ki: number, i: number): void {
    dangDoc = `${ki}:${i}`;
    const line = khoi[ki]?.lines[i];
    if (line) void speak(line.zh, line.audioUrl);
    if (data) void saveProgress(data.lesson.id, 'ht', viTri(ki, i), tongCau).catch(() => {});
  }

  /** Đọc liền cả một bài khoá, từng câu một. */
  async function playKhoi(ki: number): Promise<void> {
    const k = khoi[ki];
    if (!k) return;
    for (let i = 0; i < k.lines.length; i++) {
      dangDoc = `${ki}:${i}`;
      await speak(k.lines[i].zh, k.lines[i].audioUrl);
      await new Promise((r) => setTimeout(r, 900));
    }
  }

  async function finish(): Promise<void> {
    if (!data) return;
    try {
      await completePart(data.lesson.id, 'ht');
      done = true;
      toast.ok('Đã xong bài hội thoại.');
    } catch {
      toast.show('Chưa lưu được tiến độ.');
    }
  }

  const speakerOf = (k: BaiKhoa, id: number): Speaker | undefined => k.speakers.find((s) => s.id === id);

  /** Tô sáng từ mới trong câu. */
  function parts(line: Line): [string, string, string] {
    if (!line.highlight || !line.zh.includes(line.highlight)) return [line.zh, '', ''];
    const i = line.zh.indexOf(line.highlight);
    return [line.zh.slice(0, i), line.highlight, line.zh.slice(i + line.highlight.length)];
  }
</script>

<svelte:head><title>{data ? `${data.lesson.title} · Hội thoại` : 'Hội thoại'} · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Bài này chưa có hội thoại.">
      {#snippet action()}<a href="/learn/dialogues/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if data === null}
    <Skeleton h="360px" r={20} />
  {:else if khoi.length === 0}
    <EmptyState title="Bài này chưa có hội thoại." />
  {:else}
    <!-- Hàng công cụ của cả bài: chỉ còn bật/tắt pinyin và nghĩa. -->
    <div class="dau">
      <div style="min-width: 0">
        <span class="kicker">Hội thoại · Bài {lessonNo}</span>
        <h1 style="font-size: {m ? 19 : 24}px; line-height: 1.25; margin-top: 2px">{data.lesson.title}</h1>
        <p class="muted" style="font-size: 13px">{khoi.length} bài khoá · {tongCau} câu</p>
      </div>
      <div class="seg">
        <button type="button" class:on={showPy} onclick={() => (showPy = !showPy)}>Pinyin</button>
        <button type="button" class:on={showVi} onclick={() => (showVi = !showVi)}>Nghĩa</button>
      </div>
    </div>

    <!-- Mỗi bài khoá một khối riêng: đọc tới đâu biết đang ở cảnh nào. -->
    {#each khoi as k, ki (k.id)}
      <section class="block khoi" style="padding: {m ? 14 : 18}px {m ? 14 : 20}px">
        <div class="tren">
          <span class="so">{ki + 1}</span>
          <div style="flex: 1 1 180px; min-width: 0">
            <h2 style="font-size: {m ? 16 : 19}px; line-height: 1.3">{k.title}</h2>
            {#if k.scene}
              <p class="mota" class:mo={moTa[ki]}>{k.scene}</p>
              {#if k.scene.length > 90}
                <button type="button" class="btn btn-text btn-sm them" onclick={() => (moTa = { ...moTa, [ki]: !moTa[ki] })}>
                  {moTa[ki] ? 'Thu gọn' : 'Xem tình huống'}
                </button>
              {/if}
            {/if}
          </div>
          <button type="button" class="btn btn-sm nghe" onclick={() => playKhoi(ki)} aria-label="Nghe bài khoá {ki + 1}">
            <Icon name="speaker" size={16} /> Nghe
          </button>
        </div>

        <ul style="display: flex; flex-direction: column; gap: 10px">
          {#each k.lines as line, i (line.id)}
            {@const sp = speakerOf(k, line.speakerId)}
            {@const [pre, hl, post] = parts(line)}
            <li style="display: flex; gap: 10px">
              <span class="who hz" lang="zh" style="background: {sp?.color ?? 'var(--surface)'}">{sp?.avatarChar ?? '?'}</span>
              <button
                type="button"
                class="bub"
                class:now={dangDoc === `${ki}:${i}`}
                style="flex: 1 1 auto; max-width: 86%; text-align: left; cursor: pointer"
                onclick={() => playLine(ki, i)}
              >
                {#if sp}<span class="muted" style="font-size: 12px; font-weight: 700">{sp.name}{sp.roleVi ? ` · ${sp.roleVi}` : ''}</span>{/if}
                <span class="hz" lang="zh" style="font-size: {m ? 19 : 22}px">{pre}{#if hl}<b class="hl">{hl}</b>{/if}{post}</span>
                {#if showPy && line.pinyin}<span class="muted" style="font-size: 13.5px">{line.pinyin}</span>{/if}
                {#if showVi && line.vi}<span style="font-size: 14px">{line.vi}</span>{/if}
              </button>
            </li>
          {/each}
        </ul>
      </section>
    {/each}

    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end">
      {#if done}
        {#if data.next}
          <a href="/learn/dialogues/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son btn-lg">Bài tiếp: {data.next.title}</a>
        {:else}
          <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-son btn-lg">Về danh sách bài</a>
        {/if}
      {:else}
        <button type="button" class="btn btn-son btn-lg" onclick={finish}><Icon name="check" size={18} /> Đã đọc xong</button>
      {/if}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Hội thoại</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/dialogues/hsk/{levelId}">Hội thoại · HSK {levelId}</a> / <b>Bài {lessonNo}</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="ht" /><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 820px; display: flex; flex-direction: column; gap: 16px">
    <BackLink href="/learn/dialogues/hsk/{levelId}" label="Chọn bài" />
    {@render body(false)}</main>
{/if}

<style>
  .dau {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .khoi {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .tren {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
  }
  /* Số bài khoá: người đọc liếc là biết đang ở cảnh thứ mấy. */
  .so {
    flex: none;
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--son-soft);
    color: var(--son-text);
    font-weight: 800;
    font-size: 14px;
  }
  .nghe {
    flex: none;
  }
  /* Tình huống có thể dài vài câu, thu lại hai dòng cho khỏi đè mất phần thoại. */
  .mota {
    margin: 2px 0 0;
    font-size: 13.5px;
    color: var(--text-2);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
  }
  .mota.mo {
    -webkit-line-clamp: unset;
    line-clamp: unset;
  }
  .them {
    padding: 0;
  }
</style>
