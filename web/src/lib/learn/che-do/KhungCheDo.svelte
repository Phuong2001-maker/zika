<script lang="ts">
  import type { Snippet } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { dinhDangGiay } from './cham';

  // Khung chung của 3 chế độ luyện: hàng trên (nhãn + số), thân, hàng dưới (đồng hồ + phím tắt).
  // Style ô bấm `.cd-o` và nhãn phím `.cd-phim` để ở đây cho cả Ghép cặp lẫn Trắc nghiệm dùng chung.
  let {
    nhan,
    so,
    batDau,
    xongLuc,
    phimTat = '',
    m = false,
    children,
  }: {
    nhan: string;
    so: string;
    batDau: number;
    xongLuc: number | null;
    /** Gợi ý phím, chỉ hiện ở máy có bàn phím. */
    phimTat?: string;
    m?: boolean;
    children: Snippet;
  } = $props();

  let now = $state(Date.now());
  $effect(() => {
    if (xongLuc !== null) return;
    const t = setInterval(() => (now = Date.now()), 500);
    return () => clearInterval(t);
  });
  const giay = $derived(((xongLuc ?? now) - batDau) / 1000);
</script>

<section class="block cd-khung" class:cd-m={m}>
  <div class="cd-tren">
    <span class="cd-nhan">{nhan}</span>
    <span class="cd-so num">{so}</span>
  </div>
  {@render children()}
  <div class="cd-duoi">
    <span class="cd-gio num"><Icon name="clock" size={14} /> {dinhDangGiay(giay)}</span>
    {#if phimTat && media.coBanPhim}<span class="cd-goi-phim">{phimTat}</span>{/if}
  </div>
</section>

<style>
  .cd-khung {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 22px 24px;
  }
  .cd-khung.cd-m {
    gap: 14px;
    padding: 16px 14px;
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
  .cd-duoi {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    font-size: 13px;
    color: var(--text-2);
  }
  .cd-gio {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
  }
  .cd-goi-phim {
    color: var(--text-3);
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
