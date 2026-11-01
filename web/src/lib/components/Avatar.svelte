<script lang="ts">
  import { session } from '$lib/stores/session.svelte';
  import NotificationBell from './NotificationBell.svelte';

  // Ảnh đại diện góc phải thanh trên (05 §7.1): link /account; ở trang Tài khoản/Cài đặt có vòng bơ.
  // Chuông thông báo đi kèm ngay bên trái, nên màn học nào có ảnh đại diện là có chuông.
  let { size = 44, current = false, bell = true }: { size?: number; current?: boolean; bell?: boolean } = $props();
  const u = $derived(session.user);
</script>

<span class="ava-grp">
  {#if bell && u}<NotificationBell {size} />{/if}
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
</span>

<style>
  .ava-grp {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: none;
  }
</style>
