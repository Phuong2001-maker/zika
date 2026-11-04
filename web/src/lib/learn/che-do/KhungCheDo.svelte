<script lang="ts">
  import type { Snippet } from 'svelte';

  // Khung chung của 3 chế độ luyện: nhãn tiến độ, số câu (nếu có) và nội dung.
  // Ô `.cd-o` dùng chung; `.cd-phim` dành cho nhãn số ở đáp án Trắc nghiệm.
  let {
    nhan,
    so = '',
    m = false,
    children,
  }: {
    nhan: string;
    so?: string;
    m?: boolean;
    children: Snippet;
  } = $props();
</script>

<!-- Nằm trong khung học của trang (đã có nền trắng, viền, bóng) nên ở đây không vẽ khung nữa. -->
<section class="cd-khung" class:cd-m={m}>
  <div class="cd-tren">
    <span class="cd-nhan">{nhan}</span>
    {#if so}<span class="cd-so num">{so}</span>{/if}
  </div>
  {@render children()}
</section>

<style>
  .cd-khung {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }
  .cd-khung.cd-m {
    gap: 14px;
  }
  .cd-tren {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .cd-nhan {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-3);
  }
  .cd-so {
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--surface-2);
    border: 1px solid var(--line);
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text-2);
    white-space: nowrap;
  }

  /* Ô bấm dùng chung: ô ghép cặp và lựa chọn trắc nghiệm. */
  .cd-khung :global(.cd-o) {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 58px;
    padding: 10px 14px;
    border: 1px solid var(--line-3);
    border-radius: 14px;
    background: var(--surface);
    box-shadow: var(--sh-sm);
    color: var(--text);
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition:
      border-color 0.15s ease,
      background 0.15s ease,
      opacity 0.25s ease;
  }
  .cd-khung :global(.cd-o:hover:not(:disabled)) {
    border-color: var(--ink-strong);
  }
  .cd-khung :global(.cd-o:focus-visible) {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  .cd-khung :global(.cd-o:disabled) {
    cursor: default;
  }
  .cd-khung :global(.cd-o.dang-chon) {
    border-color: var(--son);
    background: var(--son-soft);
  }
  .cd-khung :global(.cd-o.da-dung) {
    border-color: var(--ngoc);
    background: var(--ngoc-soft);
  }
  .cd-khung :global(.cd-o.da-sai) {
    border-color: var(--bad);
    background: var(--son-soft);
  }
  .cd-khung :global(.cd-o.rung) {
    border-color: var(--bad);
    background: var(--son-soft);
    animation: cd-rung 0.4s ease;
  }
  .cd-khung :global(.cd-o.mo-di) {
    opacity: 0.4;
  }
  /* Nhãn phím ở góc ô: chỉ bày khi có bàn phím (component tự kiểm). */
  .cd-khung :global(.cd-phim) {
    flex: none;
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border: 1px solid var(--line-3);
    border-radius: 7px;
    background: var(--surface-2);
    color: var(--text-3);
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
  }
  @keyframes cd-rung {
    0%,
    100% {
      transform: none;
    }
    25% {
      transform: translateX(-5px);
    }
    75% {
      transform: translateX(5px);
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .cd-khung :global(.cd-o.rung) {
      animation: none;
    }
  }
</style>
