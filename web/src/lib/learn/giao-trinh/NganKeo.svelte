<script lang="ts">
  import type { Snippet } from 'svelte';
  import { onMount, tick } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';

  // Ngăn kéo của màn đọc giáo trình: máy tính trượt từ cạnh phải (sách vẫn lộ bên trái để biết đang ở đâu),
  // điện thoại là tấm trượt từ đáy cho ngón cái với tới. Đóng bằng Esc, bấm nền, nút ×; giữ focus bên trong.
  let {
    title,
    sub = '',
    mobile,
    onclose,
    children,
  }: { title: string; sub?: string; mobile: boolean; onclose: () => void; children: Snippet } = $props();

  let hop: HTMLDivElement | undefined = $state();
  const id = `ngan-${Math.random().toString(36).slice(2, 8)}`;

  function focusables(): HTMLElement[] {
    return hop ? Array.from(hop.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')) : [];
  }

  function onkeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
      e.stopPropagation();
      onclose();
    } else if (e.key === 'Tab') {
      const f = focusables();
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) {
        e.preventDefault();
        f[f.length - 1].focus();
      } else if (!e.shiftKey && document.activeElement === f[f.length - 1]) {
        e.preventDefault();
        f[0].focus();
      }
    }
  }

  onMount(() => {
    const truoc = document.activeElement as HTMLElement | null;
    const tran = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    // Focus vào mục đang chọn (bài đang đọc, file đang nghe) nếu có, không thì nút đóng.
    void tick().then(() => (hop?.querySelector<HTMLElement>('[aria-current="true"]') ?? focusables()[0])?.focus({ preventScroll: true }));
    return () => {
      document.body.style.overflow = tran;
      truoc?.focus?.({ preventScroll: true });
    };
  });
</script>

<div class="gt-nen" class:gt-nen-m={mobile} role="presentation" onclick={(e) => e.target === e.currentTarget && onclose()} {onkeydown}>
  <div class="gt-ngan" class:gt-ngan-m={mobile} role="dialog" aria-modal="true" aria-labelledby={id} bind:this={hop}>
    {#if mobile}<span class="gt-tay" aria-hidden="true"></span>{/if}
    <header class="gt-ngan-dau">
      <div style="min-width: 0">
        <h2 id={id}>{title}</h2>
        {#if sub}<p class="muted">{sub}</p>{/if}
      </div>
      <button type="button" class="btn btn-bare btn-sm" aria-label="Đóng" onclick={onclose}><Icon name="x" size={20} /></button>
    </header>
    <div class="gt-ngan-than">{@render children()}</div>
  </div>
</div>

<style>
  .gt-nen {
    position: fixed; inset: 0; z-index: 80;
    display: flex; justify-content: flex-end;
    background: rgba(16, 24, 40, .32);
    animation: gt-hien .15s ease-out;
  }
  .gt-nen-m { align-items: flex-end; }
  .gt-ngan {
    display: flex; flex-direction: column;
    width: min(400px, 100%); height: 100%;
    background: var(--surface); border-left: 1px solid var(--line); box-shadow: var(--sh-lg);
    animation: gt-truot-ngang .2s cubic-bezier(.2, .8, .2, 1);
  }
  .gt-ngan-m {
    width: 100%; height: auto; max-height: 82dvh;
    border-left: 0; border-top: 1px solid var(--line); border-radius: 20px 20px 0 0;
    padding-bottom: env(safe-area-inset-bottom);
    animation-name: gt-truot-doc;
  }
  .gt-tay { align-self: center; width: 40px; height: 4px; margin-top: 8px; border-radius: 99px; background: var(--line-3); }
  .gt-ngan-dau {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    padding: 18px 16px 12px 20px; border-bottom: 1px solid var(--line);
  }
  .gt-ngan-m .gt-ngan-dau { padding-top: 10px; }
  .gt-ngan-dau h2 { font-size: 18px; font-weight: 700; line-height: 1.3; }
  .gt-ngan-dau p { margin-top: 2px; font-size: 13px; }
  .gt-ngan-than { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 8px 12px 20px; }
  @keyframes gt-hien { from { opacity: 0; } }
  @keyframes gt-truot-ngang { from { transform: translateX(24px); opacity: .4; } }
  @keyframes gt-truot-doc { from { transform: translateY(40px); opacity: .4; } }
  @media (prefers-reduced-motion: reduce) {
    .gt-nen, .gt-ngan { animation: none; }
  }
</style>
