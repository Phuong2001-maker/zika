<script lang="ts">
  import { page } from '$app/state';
  import { session } from '$lib/stores/session.svelte';

  // Trang 404 (07 §13). Lỗi khác 404 (hiếm, vì SPA) dùng cùng khung với câu chung.
  const notFound = $derived(page.status === 404);
</script>

<svelte:head><title>{notFound ? 'Không tìm thấy trang' : 'Có lỗi xảy ra'} · Zìkǎ</title></svelte:head>

<main class="nf center">
  <div class="stack" style="align-items: center; text-align: center; gap: 18px; max-width: 420px">
    <span class="seal" lang="zh" style="width: 88px; height: 88px; font-size: 52px; border-radius: 20px; border-width: 3px; box-shadow: var(--sh-md); transform: rotate(-5deg)">字</span>
    <h1 style="font-size: 30px">{notFound ? 'Không tìm thấy trang' : 'Có lỗi xảy ra'}</h1>
    <p class="muted" style="font-size: 15.5px">
      {notFound ? 'Trang bạn tìm không có hoặc đã bị chuyển.' : 'Có lỗi xảy ra, thử lại sau.'}
    </p>
    <a href={session.user ? '/learn/flashcards' : '/login'} class="btn btn-son btn-lg">{session.user ? 'Về Bàn học' : 'Về trang đăng nhập'}</a>
  </div>
</main>

<style>
  .nf {
    min-height: 100vh;
    min-height: 100dvh;
    padding: 32px 16px;
  }
</style>
