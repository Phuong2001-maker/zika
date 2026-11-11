<script lang="ts">
  import { onDestroy } from 'svelte';
  import { media } from '$lib/stores/media.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { homePath } from '$lib/auth/routes';
  import AuthShell from '$lib/components/AuthShell.svelte';
  import LoginForm from './LoginForm.svelte';
  import { LoginState } from './state.svelte';

  // Màn Đăng nhập / Tạo tài khoản (07 §2). Dùng khung chung AuthShell: nền và thẻ trắng như màn học (đổi 11/11).
  // Trạng thái form nằm ở đây, dùng chung cho mọi cỡ màn.
  const st = new LoginState();
  onDestroy(() => st.destroy());
</script>

<svelte:head><title>Đăng nhập · Zìkǎ</title></svelte:head>

<AuthShell backHref={null}>
  {#if !st.verifySent}
    <div>
      <h1 style="font-size: {media.isMobile ? 24 : 26}px; line-height: 1.25">Chào bạn!</h1>
      <p class="muted" style="font-size: 14px; margin-top: 4px">Đăng nhập để lưu tiến độ và học tiếp mọi cấp.</p>
    </div>
  {/if}
  <div>
    <LoginForm {st} mobile={media.isMobile} />
  </div>
  {#snippet footer()}
    <!-- Khách học thử được trọn cấp 1 ở mọi phần (isGuestTrialRoute), nên đăng nhập không phải lối duy nhất. -->
    Chưa muốn tạo tài khoản? <a href={homePath('learner', session.quyen)} style="font-weight: 700">Học thử ngay</a>
  {/snippet}
</AuthShell>
