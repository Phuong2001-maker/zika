<script lang="ts">
  import type { Snippet } from 'svelte';

  // Trạng thái trống / lỗi tải (05 §7.10, 07 §1.6).
  let {
    title,
    desc,
    children,
    action,
  }: { title: string; desc?: string; children?: Snippet; action?: Snippet } = $props();
</script>

<div class="empty">
  <p style="font-weight: 700; color: var(--text-2)">{title}</p>
  {#if desc}<p class="muted" style="font-size: 14px">{desc}</p>{/if}
  {#if children}{@render children()}{/if}
  {#if action}<div style="margin-top: 8px">{@render action()}</div>{/if}
</div>
