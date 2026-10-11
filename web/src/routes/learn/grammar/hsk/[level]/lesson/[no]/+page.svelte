<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { completePart, lessonByNo, saveProgress } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import { normZh } from '$lib/utils/grade';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Cấu trúc ngữ pháp (07 §11).
  interface Block { label: string; hz: string; color: string }
  interface Example { kind: string; formula: string; zh: string; highlight: string | null; pinyin: string; vi: string }
  interface Practice { answer: string; vi: string; tiles: string[] }
  interface Point {
    id: number;
    title: string;
    subtitle: string;
    explain: string;
    note: string | null;
    blocks: Block[];
    examples: Example[];
    practice: Practice | null;
  }
  interface Data { lesson: LessonRef; points: Point[]; next: NextLesson | null }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<Data | null>(null);
  let failed = $state(false);
  let pi = $state(0);
  let picked = $state<number[]>([]);
  let checked = $state(false);
  let done = $state(false);

  const points = $derived(data?.points ?? []);
  const g = $derived<Point | null>(points[pi] ?? null);
  const built = $derived(picked.map((k) => g?.practice?.tiles[k] ?? '').join(''));
  const isRight = $derived(checked && g?.practice ? normZh(built) === normZh(g.practice.answer) : false);

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) { failed = true; return; }
      data = await api.get<Data>(`/learn/lessons/${l.id}/grammar`);
      pi = 0;
      resetPractice();
      done = false;
      heartbeat.setActivity({ part: 'np', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => { void levelId; void lessonNo; void load(); });

  function resetPractice(): void {
    picked = [];
    checked = false;
  }

  function goPoint(k: number): void {
    pi = k;
    resetPractice();
    if (data) void saveProgress(data.lesson.id, 'np', k + 1, points.length).catch(() => {});
  }

  async function finish(): Promise<void> {
    if (!data) return;
    try {
      await completePart(data.lesson.id, 'np');
      done = true;
      toast.ok('Đã xem xong phần ngữ pháp.');
    } catch {
      toast.show('Chưa lưu được tiến độ.');
    }
  }

  function parts(zh: string, hl: string | null): [string, string, string] {
    if (!hl || !zh.includes(hl)) return [zh, '', ''];
    const i = zh.indexOf(hl);
    return [zh.slice(0, i), hl, zh.slice(i + hl.length)];
  }
</script>

<svelte:head><title>Ngữ pháp · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if failed}
    <EmptyState title="Bài này chưa có phần ngữ pháp.">
      {#snippet action()}<a href="/learn/grammar/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
    </EmptyState>
  {:else if data === null}
    <Skeleton h="360px" r={20} />
  {:else if g}
    <!-- Chọn điểm ngữ pháp -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap">
      {#each points as p, k (p.id)}
        <button type="button" class="btn btn-sm {k === pi ? 'btn-muc' : 'btn-paper'}" onclick={() => goPoint(k)}>
          {k + 1}. <span class="hz" lang="zh">{p.title}</span>
        </button>
      {/each}
    </div>

    <section class="block" style="padding: {m ? 18 : 24}px; display: flex; flex-direction: column; gap: 16px">
      <div>
        <h1 class="hz" lang="zh" style="font-size: {m ? 26 : 32}px">{g.title}</h1>
        {#if g.subtitle}<p class="muted" style="font-size: 15px">{g.subtitle}</p>{/if}
      </div>

      <!-- Khối công thức -->
      {#if g.blocks.length > 0}
        <div class="blocks">
          {#each g.blocks as b, k (k)}
            {#if k > 0}<span class="plus">+</span>{/if}
            <span class="fblk" style="background: {b.color}">
              <span class="muted" style="font-size: 11.5px; font-weight: 700">{b.label}</span>
              <span class="hz" lang="zh" style="font-size: 22px">{b.hz}</span>
            </span>
          {/each}
        </div>
      {/if}

      <p style="font-size: 15px; line-height: 1.65">{g.explain}</p>

      <!-- Ví dụ -->
      <div style="display: flex; flex-direction: column; gap: 10px">
        {#each g.examples as ex, k (k)}
          {@const [pre, hl, post] = parts(ex.zh, ex.highlight)}
          <div class="paper" style="padding: 12px 14px; display: flex; flex-direction: column; gap: 4px">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
              {#if ex.kind}<span class="tag">{ex.kind}</span>{/if}
              {#if ex.formula}<span class="muted num" style="font-size: 13px">{ex.formula}</span>{/if}
              <span style="flex: 1"></span>
              <button type="button" class="btn btn-bare btn-sm" aria-label="Nghe câu" onclick={() => speak(ex.zh)}><Icon name="speaker" size={16} /></button>
            </div>
            <span class="hz" lang="zh" style="font-size: {m ? 19 : 22}px">{pre}{#if hl}<b class="hl">{hl}</b>{/if}{post}</span>
            {#if ex.pinyin}<span class="muted" style="font-size: 13.5px">{ex.pinyin}</span>{/if}
            {#if ex.vi}<span style="font-size: 14px">{ex.vi}</span>{/if}
          </div>
        {/each}
      </div>

      {#if g.note}
        <div class="fb" style="background: var(--bo-soft); border-color: #FDE68A">
          <Icon name="flag" size={18} />
          <span style="flex: 1; font-size: 14px">{g.note}</span>
        </div>
      {/if}
    </section>

    <!-- Luyện tập xếp câu -->
    {#if g.practice}
      <section class="block" style="padding: {m ? 18 : 24}px; display: flex; flex-direction: column; gap: 14px">
        <h2 class="h2">Thử xếp câu</h2>
        {#if g.practice.vi}<p style="font-size: 16px; font-weight: 600; text-align: center">“{g.practice.vi}”</p>{/if}

        <div class="drop" class:empty={picked.length === 0}>
          {#if picked.length === 0}
            <span class="muted" style="font-size: 14px">Bấm các ô chữ bên dưới theo đúng thứ tự</span>
          {:else}
            {#each picked as t, pos (pos)}
              <button type="button" class="wt" disabled={checked} onclick={() => (picked = picked.filter((_, k) => k !== pos))}>
                {g.practice.tiles[t]}
              </button>
            {/each}
          {/if}
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center">
          {#each g.practice.tiles as t, k (k)}
            <button type="button" class="wt" disabled={checked || picked.includes(k)} onclick={() => (picked = [...picked, k])}>{t}</button>
          {/each}
        </div>

        {#if checked}
          <div class="fb" style="background: {isRight ? 'var(--ngoc-soft)' : 'var(--son-soft)'}; border-color: {isRight ? '#9FE0B5' : 'var(--son-line)'}">
            <span class="ok-dot" style="background: {isRight ? 'var(--ngoc)' : 'var(--bad)'}"><Icon name={isRight ? 'check' : 'x'} size={14} /></span>
            <span style="flex: 1">
              <b style="display: block">{isRight ? 'Chính xác!' : 'Chưa đúng'}</b>
              <span class="hz" lang="zh" style="font-size: 17px">{g.practice.answer}</span>
            </span>
          </div>
        {/if}

        <div style="display: flex; gap: 10px; justify-content: flex-end">
          <button type="button" class="btn btn-sm" onclick={resetPractice}><Icon name="repeat" size={15} /> Xếp lại</button>
          <button type="button" class="btn btn-son btn-sm" disabled={checked || picked.length !== g.practice.tiles.length} onclick={() => (checked = true)}>
            Kiểm tra
          </button>
        </div>
      </section>
    {/if}

    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end">
      {#if pi + 1 < points.length}
        <button type="button" class="btn btn-son btn-lg" onclick={() => goPoint(pi + 1)}>Điểm tiếp <Icon name="right" size={16} /></button>
      {:else if done}
        {#if data.next}
          <a href="/learn/grammar/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son btn-lg">Bài tiếp: {data.next.title}</a>
        {:else}
          <a href="/learn/grammar/hsk/{levelId}" class="btn btn-son btn-lg">Về danh sách bài</a>
        {/if}
      {:else}
        <button type="button" class="btn btn-son btn-lg" onclick={finish}><Icon name="check" size={18} /> Xong bài</button>
      {/if}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/grammar/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">Ngữ pháp</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/grammar/hsk/{levelId}">Ngữ pháp · HSK {levelId}</a> / <b>Bài {lessonNo}</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="np" /><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 860px; display: flex; flex-direction: column; gap: 16px">{@render body(false)}</main>
{/if}

<style>
  .blocks {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    justify-content: center;
  }
  .plus {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-3);
  }
  .drop {
    min-height: 76px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: var(--surface-2);
    border: 2px dashed var(--line-3);
    border-radius: var(--r);
  }
  .drop.empty {
    justify-content: center;
  }
</style>
