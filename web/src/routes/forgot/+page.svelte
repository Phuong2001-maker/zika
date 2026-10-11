<script lang="ts">
  import { onDestroy } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import AuthShell from '$lib/components/AuthShell.svelte';
  import GoogleLogo from '$lib/components/GoogleLogo.svelte';
  import IconTile from '$lib/components/IconTile.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { emailProblem } from '$lib/utils/validate';

  // Quên mật khẩu (07 §3) — mockup Forgot / M-Forgot. Server luôn trả “đã gửi” để không lộ email có tồn tại.
  let email = $state('');
  let error = $state<string | null>(null);
  let serverError = $state<string | null>(null);
  let busy = $state(false);
  let sentTo = $state<string | null>(null);
  let cooldown = $state(0);
  let timer: ReturnType<typeof setInterval> | null = null;

  function startCooldown() {
    cooldown = 60;
    if (timer) clearInterval(timer);
    timer = setInterval(() => {
      cooldown -= 1;
      if (cooldown <= 0 && timer) {
        clearInterval(timer);
        timer = null;
      }
    }, 1000);
  }
  onDestroy(() => timer && clearInterval(timer));

  async function send(ev?: SubmitEvent) {
    ev?.preventDefault();
    serverError = null;
    error = emailProblem(email);
    if (error) return;
    busy = true;
    try {
      const res = await api.post<{ sent: boolean; emailMasked: string }>('/auth/forgot', { email: email.trim() }, { quiet: true });
      sentTo = res.emailMasked;
      startCooldown();
    } catch (e) {
      serverError = e instanceof ApiError ? (e.fields.email ?? e.message) : 'Có lỗi xảy ra, thử lại sau.';
    } finally {
      busy = false;
    }
  }

  const m = $derived(media.isMobile);
</script>

<svelte:head><title>Quên mật khẩu · Zìkǎ</title></svelte:head>

<AuthShell>
  {#if !sentTo}
    <IconTile name="lock" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Quên mật khẩu?</h1>
    <p class="muted" style="font-size: {m ? 14 : 14.5}px">
      {m ? 'Nhập email đã đăng ký, chúng tôi gửi link đặt lại mật khẩu.' : 'Nhập email bạn dùng để đăng ký. Chúng tôi sẽ gửi link đặt lại mật khẩu.'}
    </p>
    <form class="stack" style="gap: {m ? 14 : 16}px" onsubmit={send} novalidate>
      <div>
        <label class="lbl" for="fg-mail">Email</label>
        <input class="inp" id="fg-mail" type="email" autocomplete="email" placeholder="ban@email.com" style={m ? 'font-size: 16px; height: 50px' : ''} bind:value={email} aria-invalid={!!error} />
        {#if error}<p class="err">{error}</p>{/if}
      </div>
      {#if serverError}<div class="err-box" role="alert">{serverError}</div>{/if}
      <button type="submit" class="btn btn-son btn-lg" style="width: 100%" disabled={busy}>{busy ? 'Đang gửi…' : 'Gửi link đặt lại'}</button>
    </form>
    {#if m}
      <p style="font-size: 13px; padding: 10px 12px; background: var(--surface-2); border-radius: 12px">
        Đăng ký bằng Google thì không có mật khẩu, hãy bấm “Tiếp tục với Google” ở màn đăng nhập.
      </p>
    {:else}
      <div style="display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; background: var(--surface-2); border-radius: 12px; font-size: 13.5px">
        <GoogleLogo /><span>Nếu bạn đăng ký bằng Google thì không có mật khẩu. Hãy quay lại và bấm <b>Tiếp tục với Google</b>.</span>
      </div>
    {/if}
  {:else}
    <IconTile name="check" bg="var(--ngoc-soft)" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2" aria-live="polite">Đã gửi email</h1>
    <p style="font-size: {m ? 14 : 14.5}px">
      {#if m}
        Kiểm tra hộp thư <b>{sentTo}</b>. Link có hiệu lực 30 phút.
      {:else}
        Kiểm tra hộp thư <b>{sentTo}</b>. Link đặt lại có hiệu lực trong 30 phút. Không thấy thì xem cả mục Thư rác.
      {/if}
    </p>
    <a href="/login" class="btn btn-son btn-lg" style="width: 100%">Về trang đăng nhập</a>
    <p class="muted" style="font-size: 13.5px; text-align: center">
      Chưa nhận được?
      {#if cooldown > 0}
        <span>Gửi lại sau {cooldown} giây</span>
      {:else}
        <button type="button" class="btn btn-text btn-sm" style="font-size: 13.5px" onclick={() => send()}>Gửi lại</button>
      {/if}
    </p>
  {/if}
</AuthShell>
