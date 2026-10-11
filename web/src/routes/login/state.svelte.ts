// Trạng thái + hành vi của màn Đăng nhập / Tạo tài khoản (07 §2).
// Tách khỏi component để khi đổi bố cục máy tính ⇄ điện thoại (xoay máy, kéo cửa sổ) không mất chữ đang gõ.
import { goto } from '$app/navigation';
import { page } from '$app/state';
import { api, apiUrl, ApiError } from '$lib/api/client';
import type { SelfLevel, User } from '$lib/api/types';
import { safeNext } from '$lib/auth/routes';
import { session } from '$lib/stores/session.svelte';
import { toast } from '$lib/stores/toast.svelte';
import { emailProblem, nameProblem, passwordProblem } from '$lib/utils/validate';

const GOOGLE_ERRORS: Record<string, string> = {
  google: 'Không đăng nhập được bằng Google. Thử lại nhé.',
  locked: 'Tài khoản của bạn đã bị khoá.',
  signup_disabled: 'Hiện chưa mở đăng ký tài khoản mới.',
  method_disabled: 'Cách đăng nhập này đang tạm tắt.',
};

export class LoginState {
  tab = $state<'login' | 'reg'>(page.url.searchParams.get('tab') === 'register' ? 'reg' : 'login');
  serverError = $state<string | null>(GOOGLE_ERRORS[page.url.searchParams.get('error') ?? ''] ?? null);
  needVerifyEmail = $state<string | null>(null);

  // Đăng nhập
  email = $state('');
  password = $state('');
  showPw = $state(false);
  remember = $state(true);

  // Tạo tài khoản
  name = $state('');
  regEmail = $state('');
  regPassword = $state('');
  selfLevel = $state<SelfLevel>('beginner');
  accept = $state(false);

  errors = $state<Record<string, string>>({});
  busy = $state(false);
  doc = $state<'privacy' | 'terms' | null>(null);

  /** Đăng ký xong cần xác nhận email → hiện “Kiểm tra email của bạn”. */
  verifySent = $state<{ masked: string; email: string } | null>(null);
  cooldown = $state(0);
  private timer: ReturnType<typeof setInterval> | null = null;

  get next(): string {
    return safeNext(page.url.searchParams.get('next'));
  }

  startCooldown(): void {
    this.cooldown = 60;
    if (this.timer) clearInterval(this.timer);
    this.timer = setInterval(() => {
      this.cooldown -= 1;
      if (this.cooldown <= 0 && this.timer) {
        clearInterval(this.timer);
        this.timer = null;
      }
    }, 1000);
  }

  destroy(): void {
    if (this.timer) clearInterval(this.timer);
  }

  switchTab(t: 'login' | 'reg'): void {
    this.tab = t;
    this.errors = {};
    this.serverError = null;
    this.needVerifyEmail = null;
  }

  googleHref(withLevel: boolean): string {
    return apiUrl('/auth/google/start', {
      remember: this.remember ? 1 : 0,
      next: this.next,
      selfLevel: withLevel ? this.selfLevel : undefined,
    });
  }

  private applyError(e: unknown): void {
    if (e instanceof ApiError) {
      if (e.code === 'VALIDATION' && Object.keys(e.fields).length) {
        this.errors = { ...e.fields };
        return;
      }
      this.serverError = e.message;
      if (e.code === 'EMAIL_NOT_VERIFIED') this.needVerifyEmail = this.email.trim();
    } else {
      this.serverError = 'Có lỗi xảy ra, thử lại sau.';
    }
  }

  login = async (ev: SubmitEvent): Promise<void> => {
    ev.preventDefault();
    this.serverError = null;
    this.needVerifyEmail = null;
    const errs: Record<string, string> = {};
    const em = emailProblem(this.email);
    if (em) errs.email = em;
    if (!this.password) errs.password = 'Hãy nhập Mật khẩu.';
    this.errors = errs;
    if (Object.keys(errs).length) return;
    this.busy = true;
    try {
      const res = await api.post<{ user: User; csrf: string }>(
        '/auth/login',
        { email: this.email.trim(), password: this.password, remember: this.remember },
        { quiet: true },
      );
      await session.signedIn(res.csrf);
      await goto(this.next, { replaceState: true });
    } catch (e) {
      this.applyError(e);
    } finally {
      this.busy = false;
    }
  };

  register = async (ev: SubmitEvent): Promise<void> => {
    ev.preventDefault();
    this.serverError = null;
    const errs: Record<string, string> = {};
    const n = nameProblem(this.name);
    if (n) errs.displayName = n;
    const em = emailProblem(this.regEmail);
    if (em) errs.email = em;
    const pw = passwordProblem(this.regPassword);
    if (pw) errs.password = pw;
    if (!this.accept) errs.acceptTerms = 'Bạn cần đồng ý với Điều khoản và Chính sách riêng tư.';
    this.errors = errs;
    if (Object.keys(errs).length) return;
    this.busy = true;
    try {
      const res = await api.post<{ verifyRequired?: boolean; emailMasked?: string; user?: User; csrf?: string }>(
        '/auth/register',
        { displayName: this.name.trim(), email: this.regEmail.trim(), password: this.regPassword, selfLevel: this.selfLevel, acceptTerms: true },
        { quiet: true },
      );
      if (res.verifyRequired) {
        this.verifySent = { masked: res.emailMasked ?? this.regEmail, email: this.regEmail.trim() };
        this.startCooldown();
      } else if (res.csrf) {
        await session.signedIn(res.csrf);
        await goto(this.next, { replaceState: true });
      }
    } catch (e) {
      this.applyError(e);
    } finally {
      this.busy = false;
    }
  };

  resend = async (addr: string): Promise<void> => {
    try {
      await api.post('/auth/resend-verification', { email: addr }, { quiet: true });
      toast.ok('Đã gửi lại email xác nhận.');
      this.startCooldown();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Có lỗi xảy ra, thử lại sau.');
    }
  };

  backToLogin(): void {
    this.verifySent = null;
    this.switchTab('login');
    this.email = this.regEmail;
  }
}
