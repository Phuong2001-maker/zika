<script lang="ts">
  import type { Snippet } from 'svelte';
  import { media } from '$lib/stores/media.svelte';
  import Icon from './Icon.svelte';

  // Khung chung cho /forgot, /reset, /verify-email, /invite (07 §3):
  // máy tính = nền Vạn Lý Trường Thành + 1 thẻ trắng 460px giữa màn; điện thoại = dải ảnh 200px + nội dung.
  let { children, backHref = '/login', backLabel = 'Quay lại đăng nhập' }: { children: Snippet; backHref?: string; backLabel?: string } = $props();
</script>

{#if media.isMobile}
  <div class="mscreen" style="background: var(--surface)">
    <div class="wall" style="height: 200px; flex: none; background-position: 66% 72%; border-bottom: 1px solid var(--line); position: relative">
      <a href={backHref} class="btn btn-sm" style="position: absolute; left: 14px; top: 14px"><Icon name="left" size={16} /> Đăng nhập</a>
    </div>
    <main style="flex: 1; padding: 22px 18px; display: flex; flex-direction: column; gap: 14px">
      {@render children()}
    </main>
  </div>
{:else}
  <div class="wall" style="position: relative; min-height: 100vh; overflow: hidden">
    <div style="position: relative; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 48px 20px">
      <main style="width: 100%; max-width: 460px; background: var(--surface); border: 1px solid var(--line); border-radius: 24px; box-shadow: var(--sh-md); padding: 28px 32px; display: flex; flex-direction: column; gap: 16px">
        <a href={backHref} class="btn btn-text btn-sm" style="align-self: flex-start; padding: 0"><Icon name="left" size={16} /> {backLabel}</a>
        {@render children()}
      </main>
    </div>
  </div>
{/if}
