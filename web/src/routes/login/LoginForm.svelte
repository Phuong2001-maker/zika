<script lang="ts">
  import GoogleLogo from '$lib/components/GoogleLogo.svelte';
  import DocModal from '$lib/components/DocModal.svelte';
  import IconTile from '$lib/components/IconTile.svelte';
  import { session } from '$lib/stores/session.svelte';
  import type { LoginState } from './state.svelte';

  // Form Đăng nhập / Tạo tài khoản dùng chung cho 2 bố cục (07 §2). `mobile` chỉ đổi vài chi tiết hiển thị.
  let { st, mobile = false }: { st: LoginState; mobile?: boolean } = $props();

  const cfg = $derived(session.config);
  const googleOn = $derived(cfg?.googleEnabled ?? false);
  const emailOn = $derived(cfg?.emailEnabled ?? true);
  const signupOn = $derived(cfg?.allowSignup ?? true);
  const inpStyle = $derived(mobile ? 'font-size: 16px; height: 50px' : '');
  const LEVELS: [string, string][] = [
    ['beginner', 'Mới bắt đầu'],
    ['hsk12', 'HSK 1–2'],
    ['hsk34', 'HSK 3–4'],
    ['hsk5', 'HSK 5+'],
  ];
</script>

{#if st.verifySent}
  <!-- [BỔ SUNG] Đăng ký xong, cần xác nhận email (07 §2.3) -->
  <div class="stack" style="gap: 16px">
    <IconTile name="check" bg="var(--ngoc-soft)" />
    <h1 style="font-size: {mobile ? 26 : 28}px; line-height: 1.2">Kiểm tra email của bạn</h1>
    <p style="font-size: 14.5px">Chúng tôi đã gửi link xác nhận tới <b>{st.verifySent.masked}</b>. Link có hiệu lực trong 24 giờ.</p>
    <button type="button" class="btn btn-son btn-lg" style="width: 100%" onclick={() => st.backToLogin()}>Về trang đăng nhập</button>
    <p class="muted" style="font-size: 13.5px; text-align: center">
      Chưa nhận được?
      {#if st.cooldown > 0}
        <span>Gửi lại sau {st.cooldown} giây</span>
      {:else}
        <button type="button" class="btn btn-text btn-sm" style="font-size: 13.5px" onclick={() => st.verifySent && st.resend(st.verifySent.email)}>Gửi lại</button>
      {/if}
    </p>
  </div>
{:else}
  <div class="tabs" style="margin-bottom: {mobile ? 16 : 20}px; width: 100%" role="tablist">
    <button type="button" role="tab" aria-selected={st.tab === 'login'} class="tab" class:on={st.tab === 'login'} onclick={() => st.switchTab('login')} style="flex: 1; justify-content: center">Đăng nhập</button>
    <button type="button" role="tab" aria-selected={st.tab === 'reg'} class="tab" class:on={st.tab === 'reg'} onclick={() => st.switchTab('reg')} style="flex: 1; justify-content: center">Tạo tài khoản</button>
  </div>

  {#if st.tab === 'login'}
    <form class="stack" style="gap: {mobile ? 14 : 16}px" onsubmit={st.login} novalidate>
      {#if googleOn}
        <a class="btn btn-google" style="width: 100%; min-height: 50px" href={st.googleHref(false)}><GoogleLogo /> Tiếp tục với Google</a>
        {#if emailOn}
          <div class="divider"><span class="rule" style="flex: 1"></span>hoặc dùng email<span class="rule" style="flex: 1"></span></div>
        {/if}
      {/if}
      {#if emailOn}
        <div>
          <label class="lbl" for="lg-em">Email</label>
          <input class="inp" id="lg-em" type="email" autocomplete="email" placeholder="ban@email.com" style={inpStyle} bind:value={st.email} aria-invalid={!!st.errors.email} aria-describedby={st.errors.email ? 'lg-em-e' : undefined} />
          {#if st.errors.email}<p class="err" id="lg-em-e">{st.errors.email}</p>{/if}
        </div>
        <div>
          {#if mobile}
            <label class="lbl" for="lg-pw">Mật khẩu</label>
          {:else}
            <div style="display: flex; justify-content: space-between; align-items: baseline">
              <label class="lbl" for="lg-pw">Mật khẩu</label>
              <a href="/forgot" style="font-size: 13px; font-weight: 700">Quên mật khẩu?</a>
            </div>
          {/if}
          <div style="position: relative">
            <input class="inp" id="lg-pw" type={st.showPw ? 'text' : 'password'} autocomplete="current-password" placeholder="Ít nhất 8 ký tự" style="padding-right: 64px; {inpStyle}" bind:value={st.password} aria-invalid={!!st.errors.password} aria-describedby={st.errors.password ? 'lg-pw-e' : undefined} />
            <button type="button" class="btn btn-text btn-sm" style="position: absolute; right: 8px; top: {mobile ? 6 : 5}px" onclick={() => (st.showPw = !st.showPw)} aria-pressed={st.showPw}>{st.showPw ? 'Ẩn' : 'Hiện'}</button>
          </div>
          {#if st.errors.password}<p class="err" id="lg-pw-e">{st.errors.password}</p>{/if}
        </div>
        {#if mobile}
          <div style="display: flex; justify-content: space-between; align-items: center">
            <label class="chk"><input type="checkbox" bind:checked={st.remember} /> Ghi nhớ</label>
            <a href="/forgot" style="font-size: 14px; font-weight: 700; min-height: 44px; display: inline-flex; align-items: center">Quên mật khẩu?</a>
          </div>
        {:else}
          <label class="chk"><input type="checkbox" bind:checked={st.remember} /> Ghi nhớ trên máy này</label>
        {/if}
      {/if}
      {#if st.serverError}
        <div class="err-box" role="alert">
          {st.serverError}
          {#if st.needVerifyEmail}
            <div style="margin-top: 6px">
              {#if st.cooldown > 0}
                <span>Đã gửi. Gửi lại sau {st.cooldown} giây</span>
              {:else}
                <button type="button" class="btn btn-text btn-sm" style="padding: 0; color: inherit" onclick={() => st.needVerifyEmail && st.resend(st.needVerifyEmail)}>Gửi lại email xác nhận</button>
              {/if}
            </div>
          {/if}
        </div>
      {/if}
      {#if emailOn}
        <button type="submit" class="btn btn-son btn-lg" style="width: 100%" disabled={st.busy}>{st.busy ? 'Đang đăng nhập…' : 'Đăng nhập'}</button>
      {/if}
    </form>
  {:else if !signupOn}
    <div class="err-box" role="alert">Hiện chưa mở đăng ký tài khoản mới.</div>
  {:else}
    <form class="stack" style="gap: 14px" onsubmit={st.register} novalidate>
      {#if googleOn}
        <a class="btn btn-google" style="width: 100%; min-height: 50px" href={st.googleHref(true)}><GoogleLogo /> Đăng ký bằng Google</a>
        {#if emailOn}
          <div class="divider"><span class="rule" style="flex: 1"></span>hoặc dùng email<span class="rule" style="flex: 1"></span></div>
        {/if}
      {/if}
      {#if emailOn}
        <div>
          <label class="lbl" for="rg-name">Tên hiển thị</label>
          <input class="inp" id="rg-name" type="text" autocomplete="nickname" placeholder="Ví dụ: Minh Anh" style={inpStyle} bind:value={st.name} aria-invalid={!!st.errors.displayName} />
          {#if st.errors.displayName}<p class="err">{st.errors.displayName}</p>{/if}
        </div>
        <div>
          <label class="lbl" for="rg-em">Email</label>
          <input class="inp" id="rg-em" type="email" autocomplete="email" placeholder="ban@email.com" style={inpStyle} bind:value={st.regEmail} aria-invalid={!!st.errors.email} />
          {#if st.errors.email}<p class="err">{st.errors.email}</p>{/if}
        </div>
        <div>
          <label class="lbl" for="rg-pw">Mật khẩu</label>
          <input class="inp" id="rg-pw" type="password" autocomplete="new-password" placeholder="Ít nhất 8 ký tự, có chữ và số" style={inpStyle} bind:value={st.regPassword} aria-invalid={!!st.errors.password} />
          {#if st.errors.password}<p class="err">{st.errors.password}</p>{/if}
        </div>
      {/if}
      <fieldset style="border: 0; padding: 0; margin: 0">
        <legend class="lbl" style="padding: 0">Bạn đang học tới đâu?</legend>
        <div style="display: flex; flex-wrap: wrap; gap: 8px">
          {#each LEVELS as [v, label] (v)}
            <label class="pick"><input type="radio" name="rg-lv" value={v} bind:group={st.selfLevel} /><span>{label}</span></label>
          {/each}
        </div>
      </fieldset>
      {#if emailOn}
        <label class="chk" style="align-items: flex-start; line-height: 1.45; padding-top: {mobile ? 0 : 4}px">
          <input type="checkbox" style="margin-top: 1px" bind:checked={st.accept} aria-invalid={!!st.errors.acceptTerms} />
          <span>
            Tôi đồng ý với
            <a href="/pages/terms" onclick={(e) => { e.preventDefault(); st.doc = 'terms'; }}>Điều khoản</a>
            và
            <a href="/pages/privacy" onclick={(e) => { e.preventDefault(); st.doc = 'privacy'; }}>Chính sách riêng tư</a>{mobile ? '.' : ', kể cả việc ghi lại thời gian học để thống kê.'}
          </span>
        </label>
        {#if st.errors.acceptTerms}<p class="err" style="margin-top: -8px">{st.errors.acceptTerms}</p>{/if}
      {/if}
      {#if st.serverError}<div class="err-box" role="alert">{st.serverError}</div>{/if}
      {#if emailOn}
        <button type="submit" class="btn btn-son btn-lg" style="width: 100%" disabled={st.busy}>{st.busy ? 'Đang tạo tài khoản…' : 'Tạo tài khoản'}</button>
      {/if}
    </form>
  {/if}
{/if}

{#if st.doc}
  <DocModal slug={st.doc} onclose={() => (st.doc = null)} />
{/if}

<style>
  .divider {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
  }
</style>
