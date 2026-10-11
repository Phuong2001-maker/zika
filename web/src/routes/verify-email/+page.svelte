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
  import { emailProblem } from '$lib/utils/validate';

  // [BỔ SUNG] Xác nhận email — tự gọi API khi mở; thành công thì đăng nhập luôn (07 §3).
  const token = page.url.searchParams.get('token') ?? '';
  let view = $state<'checking' | 'expired' | 'resent'>(token ? 'checking' : 'expired');
  let email = $state('');
  let error = $state<string | null>(null);
  let busy = $state(false);

  onMount(async () => {
    if (!token) return;
    try {
      const res = await api.post<{ user: User; csrf: string }>('/auth/verify-email', { token }, { quiet: true });
      await session.signedIn(res.csrf);
      toast.ok('Đã xác nhận email. Chào mừng bạn!');
      await goto('/learn/flashcards', { replaceState: true });
    } catch (e) {
      if (e instanceof ApiError && e.code === 'ACCOUNT_LOCKED') {
        await goto('/login?error=locked', { replaceState: true });
        return;
      }
      view = 'expired';
    }
  });

  async function resend(ev: SubmitEvent) {
    ev.preventDefault();
    error = emailProblem(email);
    if (error) return;
    busy = true;
    try {
      await api.post('/auth/resend-verification', { email: email.trim() }, { quiet: true });
      view = 'resent';
    } catch (e) {
      error = e instanceof ApiError ? (e.fields.email ?? e.message) : 'Có lỗi xảy ra, thử lại sau.';
    } finally {
      busy = false;
    }
  }

  const m = $derived(media.isMobile);
</script>

<svelte:head><title>Xác nhận email · Zìkǎ</title></svelte:head>

<AuthShell>
  {#if view === 'checking'}
    <div class="stack" style="align-items: center; padding: 24px 0" aria-busy="true">
      <span class="seal" lang="zh" style="width: 48px; height: 48px; font-size: 26px">字</span>
      <p class="muted">Đang xác nhận email…</p>
    </div>
  {:else if view === 'expired'}
    <IconTile name="clock" bg="var(--son-soft)" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Link xác nhận đã hết hạn</h1>
    <p class="muted" style="font-size: 14.5px">Link chỉ dùng được 1 lần và có hiệu lực 24 giờ. Nhập email để nhận link mới.</p>
    <form class="stack" style="gap: 14px" onsubmit={resend} novalidate>
      <div>
        <label class="lbl" for="ve-mail">Email</label>
        <input class="inp" id="ve-mail" type="email" autocomplete="email" placeholder="ban@email.com" style={m ? 'font-size: 16px; height: 50px' : ''} bind:value={email} aria-invalid={!!error} />
        {#if error}<p class="err">{error}</p>{/if}
      </div>
      <button type="submit" class="btn btn-son btn-lg" style="width: 100%" disabled={busy}>{busy ? 'Đang gửi…' : 'Gửi lại link xác nhận'}</button>
    </form>
  {:else}
    <IconTile name="check" bg="var(--ngoc-soft)" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Đã gửi email</h1>
    <p style="font-size: 14.5px">Nếu email này đang chờ xác nhận, bạn sẽ nhận được link mới trong vài phút. Không thấy thì xem cả mục Thư rác.</p>
    <a href="/login" class="btn btn-son btn-lg" style="width: 100%">Về trang đăng nhập</a>
  {/if}
</AuthShell>
