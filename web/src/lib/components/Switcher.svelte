<script lang="ts">
  import { onMount } from 'svelte';
  import type { Part } from '$lib/api/types';
  import { PARTS } from '$lib/parts';
  import { session } from '$lib/stores/session.svelte';
  import { feedback } from '$lib/stores/feedback.svelte';
  import { neo } from '$lib/utils/neo';
  import Icon from './Icon.svelte';

  // Nút “Chuyển nhanh” 9 chấm có ở MỌI màn người học (05 §7.2). Bảng luôn đóng sẵn, bấm nút mới mở.
  let { current = 'lt' }: { current?: Part } = $props();

  let open = $state(false);
  let root: HTMLDivElement | undefined = $state();
  let nut: HTMLButtonElement | undefined = $state();
  const visibleParts = $derived(PARTS.filter((p) => session.can(`learn.${p.slug}`)));
  const cur = $derived(visibleParts.find((p) => p.code === current));
  const others = $derived(visibleParts.filter((p) => p.code !== current));
  const showFeedback = $derived(session.can('community.feedback') && (session.config?.showFeedbackButton ?? true));

  onMount(() => {
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
    bind:this={nut}
    class="btn btn-icon"
    class:apps-on={open}
    aria-label="Chuyển nhanh sang phần khác"
    aria-expanded={open}
    onclick={() => (open = !open)}
  >
    <Icon name="apps" size={22} />
  </button>
  {#if open}
    <div style="display: contents" use:neo={nut}>
      <!-- Nền mờ chỉ hiện trên điện thoại, giống bảng thông báo; nằm trong root nên phải tự đóng. -->
      <button type="button" class="swi-nen" aria-label="Đóng bảng chuyển nhanh" tabindex="-1" onclick={() => (open = false)}></button>
      <div class="block swi-panel" style="text-align: left">
        <div class="row">
          <span
            class="ib"
            style="width: 40px; height: 40px; background: var(--troi); border: 1px solid var(--line); border-radius: 12px; color: var(--text)"
            ><Icon name="apps" size={20} /></span
          >
          <span style="display: flex; flex-direction: column">
            <b class="disp" style="font-size: 17px; font-weight: 800">Chuyển nhanh</b>
            <span class="muted" style="font-size: 13px">Chọn phần bạn muốn học</span>
          </span>
        </div>
        {#if cur}
          <a href="/learn/{cur.slug}" class="cur" onclick={() => (open = false)}>
            <span class="ib" style="width: 44px; height: 44px; background: {cur.accent}; border: 1px solid var(--line); border-radius: 12px"
              ><Icon name={cur.icon} size={22} /></span
            >
            <span style="display: flex; flex-direction: column">
              <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #0D6B3E"
                ><span class="dot"></span>Đang dùng</span
              >
              <b style="font-size: 16px">{cur.name}</b>
            </span>
          </a>
        {/if}
        <p style="font-size: 11.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--muted)">
          Chuyển sang
        </p>
        <nav aria-label="Các phần học và giáo trình" style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4px">
          {#each others as p (p.code)}
            <a href="/learn/{p.slug}" class="swi" onclick={() => (open = false)}
              ><span class="swi-ic" style="background: {p.accent}"><Icon name={p.icon} size={24} /></span>{p.name}</a
            >
          {/each}
          <!-- Giáo trình hiện cho mọi tài khoản (người dùng mở cho học viên 08/11/2026).
             Màu giấy kraft để không lẫn với 6 màu của các phần học. -->
          {#if session.can('books')}<a href="/learn/books" class="swi" onclick={() => (open = false)}
            ><span class="swi-ic" style="background: #EFE0C8"><Icon name="book" size={24} /></span>Giáo trình</a
          >
          {/if}
          {#if session.can('community.chat')}
            <!-- Cấu hình theo vai trò ở Cài đặt; không ẩn cứng theo isAdmin nữa. -->
            <a href="/learn/chat" class="swi" onclick={() => (open = false)}
              ><span class="swi-ic" style="background: #DDEFD9"><Icon name="cup" size={24} /></span>Quán trà</a
            >
          {/if}
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
    </div>
  {/if}
</div>

<style>
  .swi-nen {
    display: none;
  }
  @media (max-width: 767px) {
    .swi-nen {
      display: block;
      position: fixed;
      z-index: 29;
      top: var(--neo-nen, 56px);
      right: 0;
      bottom: 0;
      left: 0;
      border: 0;
      background: rgb(16 18 24 / 32%);
      animation: swi-mo 0.16s ease-out;
    }
  }
  @keyframes swi-mo {
    from {
      opacity: 0;
    }
  }
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
