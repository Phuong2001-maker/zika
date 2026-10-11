<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { media } from '$lib/stores/media.svelte';
  import { loadFont } from '$lib/utils/fonts';
  import LoginForm from './LoginForm.svelte';
  import { LoginState } from './state.svelte';

  // Màn Đăng nhập / Tạo tài khoản (07 §2) — mockup Main (1440) và M-Login (390).
  // Trạng thái form nằm ở đây, dùng chung cho 2 bố cục.
  const st = new LoginState();
  onMount(() => loadFont('Ma Shan Zheng'));
  onDestroy(() => st.destroy());
</script>

<svelte:head><title>Đăng nhập · Zìkǎ</title></svelte:head>

{#if media.isMobile}
  <div class="mscreen" style="background: var(--surface)">
    <div class="wall" style="position: relative; height: 290px; flex: none; background-position: 66% 72%; border-bottom: 1px solid var(--line)">
      <div style="position: relative; height: 100%; display: flex; flex-direction: column; justify-content: space-between; padding: 18px 18px 30px">
        <span class="logo" style="align-self: flex-start; background: #fff; color: #14161C; border: 2px solid #14161C; border-radius: 12px; padding: 5px 12px 5px 5px; font-size: 17px; box-shadow: 2px 2px 0 #14161C">
          <span class="seal" lang="zh" style="width: 30px; height: 30px; font-size: 17px">字</span>Zìkǎ
        </span>
        <div class="sticker" style="align-self: flex-start; padding: 10px 16px; transform: rotate(-2.5deg); box-shadow: 4px 4px 0 #14161C">
          <p class="brush" lang="zh" style="font-size: 36px; line-height: 1.1">不到长城非好汉</p>
          <p style="font-size: 12.5px; font-weight: 600">Chưa tới Trường Thành, chưa phải hảo hán.</p>
        </div>
      </div>
    </div>
    <main style="flex: 1; padding: 18px 18px 24px">
      <h1 class="sr-only">Đăng nhập Zìkǎ</h1>
      <LoginForm {st} mobile />
    </main>
  </div>
{:else}
  <div class="wall" style="position: relative; min-height: 100vh; overflow: hidden">
    <div style="position: relative; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 48px; max-width: 1280px; min-height: 100vh; margin: 0 auto; padding: 48px clamp(20px, 5vw, 72px)">
      <div style="flex: 1 1 420px; display: flex; flex-direction: column; gap: 22px; align-items: flex-start">
        <span class="logo" style="background: #fff; color: #14161C; border: 2px solid #14161C; border-radius: 14px; padding: 8px 16px 8px 8px; box-shadow: 3px 3px 0 #14161C">
          <span class="seal" lang="zh" style="width: 36px; height: 36px; font-size: 20px">字</span>Zìkǎ
        </span>
        <div class="sticker" style="padding: 22px 28px 20px; transform: rotate(-2.5deg); max-width: 440px">
          <p class="brush" lang="zh" style="font-size: 64px; line-height: 1.1">不到长城<br />非好汉</p>
          <p style="font-size: 16px; font-weight: 700; margin-top: 10px">Bù dào Chángchéng fēi hǎohàn</p>
          <p style="font-size: 15px; color: #2E323C">Chưa tới Trường Thành, chưa phải hảo hán.</p>
        </div>
        <p style="background: #fff; color: #14161C; border: 2px solid #14161C; border-radius: 12px; padding: 10px 14px; font-weight: 600; font-size: 15px; transform: rotate(1deg); box-shadow: 3px 3px 0 #14161C">
          Chọn bộ thẻ, mỗi ngày lật một ít. Miễn phí.
        </p>
      </div>
      <main style="flex: 0 1 440px; width: 100%; background: var(--surface); border: 1px solid var(--line); border-radius: 24px; padding: 28px 32px 24px; box-shadow: var(--sh-md)">
        {#if !st.verifySent}
          <h1 style="font-size: 28px; line-height: 1.2; margin-bottom: 4px">Chào bạn!</h1>
          <p class="muted" style="font-size: 14px; margin-bottom: 18px">Đăng nhập để lật thẻ tiếp nhé.</p>
        {/if}
        <LoginForm {st} />
      </main>
    </div>
  </div>
{/if}
