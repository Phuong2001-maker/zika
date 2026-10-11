<script lang="ts">
  import { page } from '$app/state';
  import { api, ApiError } from '$lib/api/client';
  import AuthShell from '$lib/components/AuthShell.svelte';
  import IconTile from '$lib/components/IconTile.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { passwordProblem } from '$lib/utils/validate';

  // [BỔ SUNG] Đặt mật khẩu mới — /reset?token= (07 §3), cùng khung với màn Quên mật khẩu.
  const token = page.url.searchParams.get('token') ?? '';
  let pw = $state('');
  let pw2 = $state('');
  let errors = $state<Record<string, string>>({});
  let busy = $state(false);
  let view = $state<'form' | 'done' | 'expired'>(token ? 'form' : 'expired');
  let serverError = $state<string | null>(null);

  async function save(ev: SubmitEvent) {
    ev.preventDefault();
    serverError = null;
    const errs: Record<string, string> = {};
    const p = passwordProblem(pw);
    if (p) errs.password = p;
    if (!errs.password && pw !== pw2) errs.password2 = 'Hai mật khẩu chưa khớp.';
    errors = errs;
    if (Object.keys(errs).length) return;
    busy = true;
    try {
      await api.post('/auth/reset', { token, password: pw }, { quiet: true });
      view = 'done';
    } catch (e) {
      if (e instanceof ApiError && e.code === 'TOKEN_INVALID') view = 'expired';
      else if (e instanceof ApiError && e.fields.password) errors = { password: e.fields.password };
      else serverError = e instanceof ApiError ? e.message : 'Có lỗi xảy ra, thử lại sau.';
    } finally {
      busy = false;
    }
  }

  const m = $derived(media.isMobile);
  const inp = $derived(m ? 'font-size: 16px; height: 50px' : '');
</script>

<svelte:head><title>Đặt mật khẩu mới · Zìkǎ</title></svelte:head>

<AuthShell>
  {#if view === 'form'}
    <IconTile name="lock" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Đặt mật khẩu mới</h1>
    <form class="stack" style="gap: 14px" onsubmit={save} novalidate>
      <div>
        <label class="lbl" for="rs-pw">Mật khẩu mới</label>
        <input class="inp" id="rs-pw" type="password" autocomplete="new-password" placeholder="Ít nhất 8 ký tự, có chữ và số" style={inp} bind:value={pw} aria-invalid={!!errors.password} />
        {#if errors.password}<p class="err">{errors.password}</p>{/if}
      </div>
      <div>
        <label class="lbl" for="rs-pw2">Nhập lại mật khẩu</label>
        <input class="inp" id="rs-pw2" type="password" autocomplete="new-password" style={inp} bind:value={pw2} aria-invalid={!!errors.password2} />
        {#if errors.password2}<p class="err">{errors.password2}</p>{/if}
      </div>
      {#if serverError}<div class="err-box" role="alert">{serverError}</div>{/if}
      <button type="submit" class="btn btn-son btn-lg" style="width: 100%" disabled={busy}>{busy ? 'Đang lưu…' : 'Lưu mật khẩu mới'}</button>
    </form>
  {:else if view === 'done'}
    <IconTile name="check" bg="var(--ngoc-soft)" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Đã đổi mật khẩu</h1>
    <p style="font-size: 14.5px">Bạn đã có thể đăng nhập bằng mật khẩu mới.</p>
    <a href="/login" class="btn btn-son btn-lg" style="width: 100%">Đăng nhập</a>
  {:else}
    <IconTile name="clock" bg="var(--son-soft)" size={m ? 52 : 56} radius={m ? 14 : 16} />
    <h1 style="font-size: {m ? 26 : 28}px; line-height: 1.2">Link đã hết hạn</h1>
    <p class="muted" style="font-size: 14.5px">Link đặt lại chỉ dùng được 1 lần và có hiệu lực 30 phút.</p>
    <a href="/forgot" class="btn btn-son btn-lg" style="width: 100%">Gửi link mới</a>
  {/if}
</AuthShell>
