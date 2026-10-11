<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { api, ApiError } from '$lib/api/client';
  import type { User } from '$lib/api/types';
  import AuthShell from '$lib/components/AuthShell.svelte';
  import IconTile from '$lib/components/IconTile.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { toast } from '$lib/stores/toast.svelte';

  // [BỔ SUNG] Nhận lời mời quản trị — /invite?token= (07 §3).
  const token = page.url.searchParams.get('token') ?? '';
  let info = $state<{ valid: boolean; email: string | null; role: 'editor' | 'admin' | null } | null>(null);
  let busy = $state(false);
  let error = $state<string | null>(null);

  onMount(async () => {
    if (!/^[A-Za-z0-9_-]{20,100}$/.test(token)) {
      info = { valid: false, email: null, role: null };
      return;
    }
    try {
      info = await api.get(`/auth/invite/${token}`, { quiet: true });
    } catch {
      info = { valid: false, email: null, role: null };
    }
  });

  const roleLabel = $derived(info?.role === 'admin' ? 'Quản trị viên' : 'Biên tập nội dung');
  const sameEmail = $derived(!!session.user && !!info?.email && session.user.email.toLowerCase() === info.email.toLowerCase());

  async function accept() {
    busy = true;
    error = null;
    try {
      const res = await api.post<{ user: User }>('/auth/invite/accept', { token }, { quiet: true });
      session.setUser(res.user);
      toast.ok('Đã nhận lời mời.');
      await goto('/admin');
    } catch (e) {
      error = e instanceof ApiError ? e.message : 'Có lỗi xảy ra, thử lại sau.';
    } finally {
      busy = false;
    }
  }

  const m = $derived(media.isMobile);
  const back = $derived(session.user ? '/learn/flashcards' : '/login');
</script>

<svelte:head><title>Lời mời quản trị · Zìkǎ</title></svelte:head>

<AuthShell backHref={back} backLabel={session.user ? 'Về Bàn học' : 'Quay lại đăng nhập'}>
  {#if !info}
    <div class="skel" style="height: 180px"></div>
  {:else if !info.valid}
    <IconTile name="clock" bg="var(--son-soft)" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Lời mời đã hết hạn</h1>
    <p class="muted" style="font-size: 14.5px">Link đã hết hạn hoặc đã được dùng. Hãy nhờ quản trị viên gửi lời mời mới.</p>
  {:else}
    <IconTile name="users" bg="var(--tim)" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Bạn được mời làm {roleLabel} của Zìkǎ</h1>
    {#if !session.user}
      <p class="muted" style="font-size: 14.5px">Lời mời gửi tới <b>{info.email}</b>. Đăng nhập bằng email này để nhận.</p>
      <a href={`/login?next=${encodeURIComponent(`/invite?token=${token}`)}`} class="btn btn-son btn-lg" style="width: 100%">Đăng nhập để nhận lời mời</a>
    {:else if sameEmail}
      {#if error}<div class="err-box" role="alert">{error}</div>{/if}
      <button type="button" class="btn btn-son btn-lg" style="width: 100%" disabled={busy} onclick={accept}>{busy ? 'Đang nhận…' : 'Nhận lời mời'}</button>
    {:else}
      <div class="err-box" role="alert">Lời mời gửi tới {info.email}. Hãy đăng nhập bằng email đó.</div>
    {/if}
  {/if}
</AuthShell>
