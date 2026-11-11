<script lang="ts">
  import type { Snippet } from 'svelte';
  import Icon from './Icon.svelte';

  // Khung chung cho các màn tài khoản: /login, /forgot, /reset, /verify-email, /invite. Nền và thẻ trắng giống hệt màn học
  // (người dùng 11/11: bản cũ vẽ hoạt hình Trường Thành, mây, mặt trời, quá khác giao diện chung). Một bố cục cho mọi cỡ
  // màn: cột 420px giữa màn, đủ thấp để ô đăng nhập luôn nằm trong màn hình đầu tiên, không phải cuộn mới thấy nút.
  let {
    children,
    footer,
    backHref = '/login',
    backLabel = 'Quay lại đăng nhập',
  }: {
    children: Snippet;
    /** Dòng dưới thẻ, ví dụ lối học thử không cần tài khoản ở màn đăng nhập. */
    footer?: Snippet;
    /** null = không có nút quay lại (chính màn đăng nhập). */
    backHref?: string | null;
    backLabel?: string;
  } = $props();
</script>

<div class="dn-man">
  <div class="dn-cot">
    <span class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</span>
    <main class="block dn-the">
      {#if backHref}
        <a href={backHref} class="btn btn-text btn-sm dn-lui"><Icon name="chev" size={16} style="transform: rotate(180deg)" /> {backLabel}</a>
      {/if}
      {@render children()}
    </main>
    {#if footer}<div class="dn-cuoi">{@render footer()}</div>{/if}
  </div>
</div>

<style>
  .dn-man {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
    background: var(--ground);
  }
  .dn-cot {
    width: 100%;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
  }
  .dn-the {
    width: 100%;
    padding: 28px 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .dn-lui {
    align-self: flex-start;
    padding: 0;
  }
  .dn-cuoi {
    text-align: center;
    font-size: 14px;
    color: var(--text-2);
  }
  /* Điện thoại: bám đầu màn để bàn phím ảo không đẩy ô nhập ra khỏi tầm nhìn. */
  @media (max-width: 480px) {
    .dn-man {
      align-items: flex-start;
      padding: 20px 16px 28px;
    }
    .dn-the {
      padding: 22px 18px 20px;
    }
  }
</style>
