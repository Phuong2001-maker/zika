// Gọi API: fetch + CSRF + lỗi chuẩn (02 §3.3).
// Thành công → { data, meta }; lỗi → ném ApiError { status, code, message, fields }.

const BASE: string = (import.meta.env.VITE_API_BASE as string | undefined) ?? '';

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
    public fields: Record<string, string> = {},
    public extra: Record<string, unknown> = {},
  ) {
    super(message);
  }
}

export interface ApiResult<T> {
  data: T;
  meta?: Record<string, unknown>;
}

type Hook = (e: ApiError, path: string) => void;
const hooks: { auth?: Hook; locked?: Hook; network?: Hook } = {};

/** Layout gốc đăng ký cách xử lý 401 / tài khoản bị khoá / mất mạng (tránh phụ thuộc vòng). */
export function onApiEvent(kind: keyof typeof hooks, fn: Hook): void {
  hooks[kind] = fn;
}

let csrf = '';
export function setCsrf(token: string): void {
  csrf = token;
}

/** Token CSRF hiện tại (cookie zk_csrf không HttpOnly nên đọc được; ưu tiên cookie vì server có thể đổi). */
export function getCsrf(): string {
  if (typeof document !== 'undefined') {
    const m = document.cookie.match(/(?:^|;\s*)zk_csrf=([^;]+)/);
    if (m) csrf = decodeURIComponent(m[1]);
  }
  return csrf;
}

/** Chế độ “Xem như người học” của admin: thêm ?preview=1 cho mọi lời gọi /learn/**. */
let preview = false;
export function setPreview(on: boolean): void {
  preview = on;
}
export function isPreview(): boolean {
  return preview;
}

export interface RequestOptions {
  query?: Record<string, string | number | boolean | null | undefined>;
  signal?: AbortSignal;
  /** Không tự chuyển về /login khi 401 (ví dụ lần gọi /me đầu tiên). */
  quiet?: boolean;
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries(query ?? {})) {
    if (v !== undefined && v !== null && v !== '') params.set(k, String(v));
  }
  if (preview && path.startsWith('/learn/')) params.set('preview', '1');
  const qs = params.toString();
  return `${BASE}/api${path}${qs ? (path.includes('?') ? '&' : '?') + qs : ''}`;
}

async function request<T>(method: string, path: string, body?: unknown, opts: RequestOptions = {}): Promise<ApiResult<T>> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  let payload: BodyInit | undefined;
  if (body instanceof FormData) {
    payload = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }
  if (method !== 'GET') headers['X-CSRF-Token'] = getCsrf();

  let res: Response;
  try {
    res = await fetch(buildUrl(path, opts.query), {
      method,
      headers,
      body: payload,
      credentials: BASE ? 'include' : 'same-origin',
      signal: opts.signal,
    });
  } catch (e) {
    if ((e as Error)?.name === 'AbortError') throw e;
    const err = new ApiError(0, 'NETWORK', 'Không kết nối được. Kiểm tra mạng rồi thử lại.');
    hooks.network?.(err, path);
    throw err;
  }

  if (res.status === 204) return { data: undefined as T };

  let json: { data?: T; meta?: Record<string, unknown>; error?: { code: string; message: string; fields?: Record<string, string> } & Record<string, unknown> } | null = null;
  try {
    json = await res.json();
  } catch {
    json = null;
  }

  if (!res.ok) {
    const e = json?.error;
    const { code = 'SERVER', message = 'Có lỗi xảy ra, thử lại sau.', fields = {}, ...extra } = e ?? {};
    const err = new ApiError(res.status, code, message, fields, extra);
    if (res.status === 401 && !opts.quiet) hooks.auth?.(err, path);
    if (code === 'ACCOUNT_LOCKED') hooks.locked?.(err, path);
    if (res.status >= 500) hooks.network?.(err, path);
    throw err;
  }
  return { data: json?.data as T, meta: json?.meta };
}

export const api = {
  get: <T>(path: string, opts?: RequestOptions) => request<T>('GET', path, undefined, opts).then((r) => r.data),
  getFull: <T>(path: string, opts?: RequestOptions) => request<T>('GET', path, undefined, opts),
  post: <T>(path: string, body?: unknown, opts?: RequestOptions) => request<T>('POST', path, body ?? {}, opts).then((r) => r.data),
  put: <T>(path: string, body?: unknown, opts?: RequestOptions) => request<T>('PUT', path, body ?? {}, opts).then((r) => r.data),
  patch: <T>(path: string, body?: unknown, opts?: RequestOptions) => request<T>('PATCH', path, body ?? {}, opts).then((r) => r.data),
  del: <T>(path: string, body?: unknown, opts?: RequestOptions) => request<T>('DELETE', path, body, opts).then((r) => r.data),
  /** Gửi multipart (ảnh, mp3…): body là FormData. */
  upload: <T>(path: string, form: FormData, opts?: RequestOptions) => request<T>('POST', path, form, opts).then((r) => r.data),
};

/**
 * sendBeacon khi rời trang: không gửi được header nên token CSRF đi trong form (02 §6.2).
 * Trả false nếu trình duyệt không hỗ trợ (khi đó gọi fetch keepalive).
 */
export function beacon(path: string, fields: Record<string, string>): boolean {
  const fd = new FormData();
  fd.set('csrf', getCsrf());
  for (const [k, v] of Object.entries(fields)) fd.set(k, v);
  const url = `${BASE}/api${path}`;
  if (typeof navigator !== 'undefined' && navigator.sendBeacon?.(url, fd)) return true;
  fetch(url, { method: 'POST', body: fd, keepalive: true, credentials: BASE ? 'include' : 'same-origin' }).catch(() => {});
  return false;
}

/** URL tuyệt đối tới API (cho thẻ <a> tải file CSV, link Google…). */
export function apiUrl(path: string, query?: RequestOptions['query']): string {
  return buildUrl(path, query);
}
