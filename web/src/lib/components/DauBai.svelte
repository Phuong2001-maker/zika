<script lang="ts">
  import type { Snippet } from 'svelte';
  import BackLink from './BackLink.svelte';

  // Đầu bài dùng chung cho mọi màn học (máy tính): đường về + dòng nhỏ "HSK 1 · Bài 2 · …" +
  // tên bài, phần phải tuỳ màn (đồng hồ, điểm…). Đi kèm một khung .zk-khung ngay bên dưới.
  let {
    href,
    label = 'Chọn bài',
    kicker,
    title,
    right,
    hideBack = false,
  }: { href: string; label?: string; kicker: string; title: string; right?: Snippet; hideBack?: boolean } = $props();
</script>

<div class="zk-dau">
  {#if !hideBack}<BackLink {href} {label} />{/if}
  <div class="zk-dau-chu">
    <p class="kicker zk-dau-kicker">{kicker}</p>
    <h1 class="zk-dau-ten">{title}</h1>
  </div>
  {#if right}<div class="zk-dau-phai">{@render right()}</div>{/if}
</div>

<style>
  .zk-dau {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .zk-dau-chu {
    flex: 1;
    min-width: 0;
  }
  .zk-dau-kicker {
    font-size: 12px;
  }
  .zk-dau-ten {
    margin-top: 2px;
    font-size: 22px;
    line-height: 1.25;
    overflow-wrap: break-word;
  }
  .zk-dau-phai {
    flex: none;
    display: flex;
    align-items: center;
    gap: 10px;
  }
</style>
