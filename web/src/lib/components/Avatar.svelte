<script lang="ts">
  import { session } from '$lib/stores/session.svelte';

  // Ảnh đại diện góc phải thanh trên (05 §7.1): link /account; ở trang Tài khoản/Cài đặt có vòng bơ.
  let { size = 44, current = false }: { size?: number; current?: boolean } = $props();
  const u = $derived(session.user);
</script>

<a
  href="/account"
  class="ava"
  aria-label="Tài khoản của {u?.displayName ?? ''}"
  aria-current={current ? 'page' : undefined}
  style="width: {size}px; height: {size}px; {current ? 'box-shadow: 0 0 0 3px #FFD84D;' : ''}"
>
  {#if u?.avatarUrl}
    <img src={u.avatarUrl} alt="" width={size} height={size} referrerpolicy="no-referrer" />
  {:else}
    {u?.initials ?? '?'}
  {/if}
</a>
