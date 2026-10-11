<script lang="ts">
  import '../app.css';
  import type { Snippet } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { onApiEvent } from '$lib/api/client';
  import { session } from '$lib/stores/session.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Toaster from '$lib/components/Toaster.svelte';
  import { isAdminRoute, isGuestOnly, isPublicRoute, loginUrl, safeNext } from '$lib/auth/routes';

  let { children }: { children: Snippet } = $props();

  // Xử lý lỗi API chung (02 §3.3). Đăng ký ở đây để client.ts không phụ thuộc vòng vào store/router.
  onApiEvent('auth', () => {
    session.clear();
    if (!isPublicRoute(page.url.pathname)) void goto(loginUrl(page.url.pathname + page.url.search), { replaceState: true });
  });
  onApiEvent('locked', () => {
    session.clear();
    const email = session.config?.contactEmail;
    toast.show(`Tài khoản của bạn đã bị khoá.${email ? ` Liên hệ ${email} nếu cần hỗ trợ.` : ''}`, 'info', 8000);
    void goto('/login?error=locked', { replaceState: true });
  });
  let lastNetworkToast = 0;
  onApiEvent('network', () => {
    // Nhiều request cùng lỗi một lúc thì chỉ báo một lần.
    if (Date.now() - lastNetworkToast < 4000) return;
    lastNetworkToast = Date.now();
    toast.show('Không kết nối được. Kiểm tra mạng rồi thử lại.');
  });

  void session.init();

  /** Route hiện tại có được hiển thị với trạng thái đăng nhập hiện tại không (tránh nháy nội dung cần đăng nhập). */
  const allowed = $derived.by(() => {
    if (!session.loaded) return false;
    const p = page.url.pathname;
    const u = session.user;
    if (p === '/') return false;
    if (!u) return isPublicRoute(p);
    if (isGuestOnly(p)) return false;
    if (isAdminRoute(p) && u.role === 'learner') return false;
    return true;
  });

  // Chặn/chuyển hướng theo trạng thái đăng nhập (07 §0).
  $effect(() => {
    if (!session.loaded) return;
    const p = page.url.pathname;
    const u = session.user;
    if (p === '/') {
      void goto(u ? '/learn/flashcards' : '/login', { replaceState: true });
    } else if (!u && !isPublicRoute(p)) {
      void goto(loginUrl(p + page.url.search), { replaceState: true });
    } else if (u && isGuestOnly(p)) {
      void goto(safeNext(page.url.searchParams.get('next')), { replaceState: true });
    } else if (u && isAdminRoute(p) && u.role === 'learner') {
      void goto('/learn/flashcards', { replaceState: true });
    }
  });

  // Theo dõi phiên chỉ chạy khi đã đăng nhập (02 §3.4).
  $effect(() => {
    if (session.user) heartbeat.start((a) => (session.announcements = a));
    else heartbeat.stop();
  });

  // Trang quản trị luôn dùng bảng màu sáng (05 §2).
  $effect(() => {
    settings.setForceLight(isAdminRoute(page.url.pathname));
  });
</script>

{#if allowed}
  {@render children()}
{:else}
  <div class="boot center" aria-busy="true" aria-label="Đang tải">
    <span class="seal" lang="zh" style="width: 48px; height: 48px; font-size: 26px">字</span>
  </div>
{/if}

<Toaster />

<style>
  .boot {
    min-height: 100vh;
    min-height: 100dvh;
  }
</style>
