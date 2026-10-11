<script lang="ts">
  import { onMount } from 'svelte';
  import type { Part } from '$lib/api/types';
  import { PART, PARTS } from '$lib/parts';
  import { session } from '$lib/stores/session.svelte';
  import { feedback } from '$lib/stores/feedback.svelte';
  import Icon from './Icon.svelte';

  // Nút “Chuyển nhanh” 9 chấm có ở MỌI màn người học (05 §7.2).
  // intro = Bàn học: mở sẵn bảng lần đầu mỗi phiên để người mới biết có 6 phần.
  let { current = 'lt', mobile = false, intro = false }: { current?: Part; mobile?: boolean; intro?: boolean } = $props();

  let open = $state(false);
  let root: HTMLDivElement | undefined = $state();
  const cur = $derived(PART[current]);
  const others = $derived(PARTS.filter((p) => p.code !== current));
  const showFeedback = $derived(session.config?.showFeedbackButton ?? true);

  onMount(() => {
    if (intro && !session.switcherIntroShown) {
      session.switcherIntroShown = true;
      open = true;
    }
    const onDoc = (e: MouseEvent) => {
      if (open && root && !root.contains(e.target as Node)) open = false;
    };
    const onKey = (e: KeyboardEvent) => {
      if (open && e.key === 'Escape') open = false;
    };
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  });
</script>

<div style="position: relative" bind:this={root}>
  <button
    type="button"
    class="btn btn-icon"
    class:apps-on={open}
    aria-label="Chuyển nhanh sang phần khác"
    aria-expanded={open}
    onclick={() => (open = !open)}
  >
    <Icon name="apps" size={22} />
  </button>
  {#if open}
    <div class="block swi-panel" style="width: {mobile ? 316 : 360}px; text-align: left">
      <div class="row">
        <span class="ib" style="width: 40px; height: 40px; background: var(--troi); border: 1px solid var(--line); border-radius: 12px; color: var(--text)"><Icon name="apps" size={20} /></span>
        <span style="display: flex; flex-direction: column">
          <b class="disp" style="font-size: 17px; font-weight: 800">Chuyển nhanh</b>
          <span class="muted" style="font-size: 13px">Chọn phần bạn muốn học</span>
        </span>
      </div>
      <a href="/learn/{cur.slug}" class="cur" onclick={() => (open = false)}>
        <span class="ib" style="width: 44px; height: 44px; background: {cur.accent}; border: 1px solid var(--line); border-radius: 12px"><Icon name={cur.icon} size={22} /></span>
        <span style="display: flex; flex-direction: column">
          <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #0D6B3E"><span class="dot"></span>Đang dùng</span>
          <b style="font-size: 16px">{cur.name}</b>
        </span>
      </a>
      <p style="font-size: 11.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--muted)">Chuyển sang · 5 phần</p>
      <nav aria-label="Các phần học" style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4px">
        {#each others as p (p.code)}
          <a href="/learn/{p.slug}" class="swi" onclick={() => (open = false)}><span class="swi-ic" style="background: {p.accent}"><Icon name={p.icon} size={24} /></span>{p.name}</a>
        {/each}
      </nav>
      {#if showFeedback && feedback.available}
        <span class="rule"></span>
        <button
          type="button"
          class="fbrow"
          onclick={() => {
            open = false;
            feedback.open();
          }}><Icon name="msg" size={18} /> Góp ý · báo lỗi màn này</button
        >
      {/if}
    </div>
  {/if}
</div>

<style>
  .cur {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--ngoc-soft);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    text-decoration: none;
    color: var(--text);
  }
  .fbrow {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 0 10px;
    border: 0;
    border-radius: 12px;
    background: none;
    font-weight: 700;
    font-size: 14px;
    color: var(--text-2);
    cursor: pointer;
    text-align: left;
  }
  .fbrow:hover {
    background: var(--ground);
    color: var(--text);
  }
</style>
