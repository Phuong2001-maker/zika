<script lang="ts">
  import type { Snippet } from 'svelte';
  import { onMount, tick } from 'svelte';
  import Icon from './Icon.svelte';

  // Hộp thoại dùng chung (05 §7.10). Điện thoại: tấm trượt từ đáy (CSS trong app.css).
  // Đóng bằng Esc, bấm nền, hoặc nút ×. Giữ focus bên trong hộp khi mở.
  let {
    title,
    onclose,
    children,
    actions,
    width = 460,
    closable = true,
    labelledby,
    anchor = 'sheet',
    mobileFullscreen = false,
  }: {
    title?: string;
    onclose: () => void;
    children: Snippet;
    actions?: Snippet;
    width?: number;
    closable?: boolean;
    labelledby?: string;
    /** Điện thoại: 'sheet' là tấm trượt từ đáy; 'top' treo ngay dưới thanh đầu trang, cho hộp mở từ nút trên thanh đó. */
    anchor?: 'sheet' | 'top';
    /** Chế độ trang chi tiết: đầu và nút thao tác cố định trên điện thoại. */
    mobileFullscreen?: boolean;
  } = $props();

  let box: HTMLDivElement | undefined = $state();
  const titleId = `mt-${Math.random().toString(36).slice(2, 8)}`;
  let prevFocus: HTMLElement | null = null;

  function focusables(): HTMLElement[] {
    if (!box) return [];
    return Array.from(
      box.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
    );
  }

  function onkeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && closable) {
      e.stopPropagation();
      onclose();
    } else if (e.key === 'Tab') {
      const f = focusables();
      if (f.length === 0) return;
      const first = f[0];
      const last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  onMount(() => {
    prevFocus = document.activeElement as HTMLElement | null;
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    void tick().then(() => {
      const f = focusables();
      // Ưu tiên ô nhập đầu tiên, không thì nút đầu tiên sau nút ×.
      // Nút đó có thể nằm cuối hộp dài: không cuộn theo, kẻo vừa mở đã mất tiêu đề.
      const input = f.find((el) => el.matches('input, textarea, select'));
      if (input) input.focus();
      else (f[1] ?? f[0])?.focus({ preventScroll: true });
    });
    return () => {
      document.body.style.overflow = prevOverflow;
      prevFocus?.focus?.();
    };
  });
</script>

<div class="modal-backdrop" class:modal-backdrop-top={anchor === 'top'} class:md-full-backdrop={mobileFullscreen} role="presentation" onclick={(e) => e.target === e.currentTarget && closable && onclose()} {onkeydown}>
  <div
    class="modal"
    class:md-full={mobileFullscreen}
    role="dialog"
    aria-modal="true"
    aria-labelledby={labelledby ?? (title ? titleId : undefined)}
    bind:this={box}
    style:max-width="{width}px"
  >
    {#if title || closable}
      <div class="row" class:md-full-head={mobileFullscreen} style="align-items: flex-start">
        {#if mobileFullscreen && closable}<button type="button" class="btn btn-bare md-back" aria-label="Quay lại" onclick={onclose}><Icon name="chev" size={21} style="transform: rotate(180deg)" /></button>{/if}
        {#if title}<h2 id={titleId} class="grow">{title}</h2>{:else}<span class="grow"></span>{/if}
        {#if closable}
          <button type="button" class="btn btn-bare btn-sm" class:md-full-close={mobileFullscreen} aria-label="Đóng" onclick={onclose} style="margin: -6px -8px 0 0">
            <Icon name="x" size={20} />
          </button>
        {/if}
      </div>
    {/if}
    {#if mobileFullscreen}<div class="md-full-body">{@render children()}</div>{:else}{@render children()}{/if}
    {#if actions}
      <div class="modal-actions" class:md-full-actions={mobileFullscreen}>{@render actions()}</div>
    {/if}
  </div>
</div>

<style>
  .md-back { display: none; }
  .md-full-body { min-width: 0; }
  /* Chỉ ẩn thanh cuộn của trang chi tiết, vẫn cho cuộn và dùng bàn phím. */
  .md-full, .md-full-body { scrollbar-width: none; }
  .md-full::-webkit-scrollbar, .md-full-body::-webkit-scrollbar { display: none; }
  @media (max-width: 767px) {
    .md-full-backdrop { padding: 0; align-items: stretch; }
    .md-full-backdrop .md-full {
      max-width: none !important;
      height: 100dvh;
      max-height: 100dvh;
      border: 0;
      border-radius: 0;
      padding: 0;
      gap: 0;
      overflow: hidden;
    }
    .md-full-head {
      flex: none;
      align-items: center !important;
      gap: 10px;
      padding: calc(10px + env(safe-area-inset-top)) 16px 10px;
      border-bottom: 1px solid var(--line);
    }
    .md-full-head h2 { font-size: 18px; line-height: 1.4; overflow-wrap: anywhere; }
    .md-back { display: inline-flex; flex: none; width: 44px; height: 44px; padding: 0; }
    .md-full-close { display: none; }
    .md-full-body { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 4px 16px 20px; }
    .md-full-actions {
      flex: none;
      padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
      border-top: 1px solid var(--line);
      background: var(--surface);
    }
    .md-full-actions :global(.btn) { flex: 1 1 auto; min-height: 44px; }
  }
</style>
