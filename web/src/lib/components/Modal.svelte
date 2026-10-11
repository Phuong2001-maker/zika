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
  }: {
    title?: string;
    onclose: () => void;
    children: Snippet;
    actions?: Snippet;
    width?: number;
    closable?: boolean;
    labelledby?: string;
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
      (f.find((el) => el.matches('input, textarea, select')) ?? f[1] ?? f[0])?.focus();
    });
    return () => {
      document.body.style.overflow = prevOverflow;
      prevFocus?.focus?.();
    };
  });
</script>

<div class="modal-backdrop" role="presentation" onclick={(e) => e.target === e.currentTarget && closable && onclose()} {onkeydown}>
  <div
    class="modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby={labelledby ?? (title ? titleId : undefined)}
    bind:this={box}
    style:max-width="{width}px"
  >
    {#if title || closable}
      <div class="row" style="align-items: flex-start">
        {#if title}<h2 id={titleId} class="grow">{title}</h2>{:else}<span class="grow"></span>{/if}
        {#if closable}
          <button type="button" class="btn btn-bare btn-sm" aria-label="Đóng" onclick={onclose} style="margin: -6px -8px 0 0">
            <Icon name="x" size={20} />
          </button>
        {/if}
      </div>
    {/if}
    {@render children()}
    {#if actions}
      <div class="modal-actions">{@render actions()}</div>
    {/if}
  </div>
</div>
