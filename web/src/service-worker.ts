/// <reference types="@sveltejs/kit" />
/// <reference lib="webworker" />

import { build, files, version } from '$service-worker';

// Service worker của Zìkǎ (02 §3.5).
// Viết tay thay vì dùng Workbox: chỉ cần 4 chiến lược, không đáng thêm một phụ thuộc build.
//  - precache  : mã nguồn build (/_app/immutable/**) + file tĩnh trong static/
//  - SWR       : nội dung bài học (/api/learn/**) → mở lại bài đã học khi mất mạng
//  - CacheFirst: file nét chữ, ảnh, âm thanh trong /uploads/** và font Google
//  - NetworkOnly: mọi thứ còn lại (đăng nhập, tiến độ, kiểm tra, quản trị, ?preview=1)

const sw = self as unknown as ServiceWorkerGlobalScope;

const PRECACHE = `zika-app-${version}`;
const LEARN = 'zika-learn';
const MEDIA = 'zika-media';
const FONTS = 'zika-fonts';
const MINE = [PRECACHE, LEARN, MEDIA, FONTS];

/** Nội dung bài học giữ tối đa 300 mục, 30 ngày (02 §3.5). */
const LEARN_MAX = 300;
const LEARN_TTL = 30 * 24 * 60 * 60 * 1000;
const MEDIA_TTL = 60 * 24 * 60 * 60 * 1000;
const FONT_TTL = 365 * 24 * 60 * 60 * 1000;

/** Đường dẫn API không bao giờ được cache — dữ liệu riêng của từng người, phải luôn mới. */
const NEVER = ['/api/me', '/api/auth/', '/api/admin/', '/api/visit/', '/api/progress/', '/api/tests/'];

// Mọi file build + file tĩnh đều nằm trong precache. Bỏ qua file cài đặt một lần.
const ASSETS = [...build, ...files.filter((f) => f !== '/robots.txt')];

sw.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(PRECACHE)
      .then((c) => c.addAll(ASSETS))
      .then(() => sw.skipWaiting()),
  );
});

sw.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      // Xoá bản build cũ, giữ các kho dữ liệu dùng lại được.
      for (const key of await caches.keys()) {
        if (!MINE.includes(key)) await caches.delete(key);
      }
      await sw.clients.claim();
    })(),
  );
});

// Trang gọi khi người dùng bấm “Tải lại” ở thông báo có bản mới.
sw.addEventListener('message', (event) => {
  if ((event.data as { type?: string } | undefined)?.type === 'skip-waiting') void sw.skipWaiting();
});

/** Thời điểm lưu vào cache, nhét vào header để biết mục đã quá hạn chưa. */
const STAMP = 'x-zika-cached-at';

function stamp(res: Response): Response {
  const h = new Headers(res.headers);
  h.set(STAMP, String(Date.now()));
  return new Response(res.body, { status: res.status, statusText: res.statusText, headers: h });
}

function fresh(res: Response, ttl: number): boolean {
  const at = Number(res.headers.get(STAMP) ?? 0);
  return at > 0 && Date.now() - at < ttl;
}

/** Giữ kho không phình: xoá các mục cũ nhất khi vượt `max`. */
async function trim(cacheName: string, max: number): Promise<void> {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  if (keys.length <= max) return;
  for (const req of keys.slice(0, keys.length - max)) await cache.delete(req);
}

/** Dùng bản cache ngay, đồng thời tải bản mới về cho lần sau. */
async function staleWhileRevalidate(req: Request, cacheName: string, ttl: number, max?: number): Promise<Response> {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(req);
  const net = fetch(req)
    .then(async (res) => {
      if (res.ok) {
        await cache.put(req, stamp(res.clone()));
        if (max) await trim(cacheName, max);
      }
      return res;
    })
    .catch(() => null);
  if (hit && fresh(hit, ttl)) {
    void net;
    return hit;
  }
  return (await net) ?? hit ?? Response.error();
}

/** Có trong cache thì dùng luôn, không có mới tải. */
async function cacheFirst(req: Request, cacheName: string, ttl: number): Promise<Response> {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(req);
  if (hit && fresh(hit, ttl)) return hit;
  try {
    const res = await fetch(req);
    if (res.ok || res.type === 'opaque') await cache.put(req, stamp(res.clone()));
    return res;
  } catch {
    return hit ?? Response.error();
  }
}

sw.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  const sameOrigin = url.origin === location.origin;

  // Font Google: tải một lần, dùng cả năm.
  if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
    event.respondWith(cacheFirst(req, FONTS, FONT_TTL));
    return;
  }
  if (!sameOrigin) return;

  // File build và file tĩnh: lấy thẳng từ precache.
  if (ASSETS.includes(url.pathname)) {
    event.respondWith(
      caches.open(PRECACHE).then(async (c) => (await c.match(url.pathname)) ?? fetch(req)),
    );
    return;
  }

  // Ảnh, âm thanh, dữ liệu nét chữ: đổi rất ít, giữ 60 ngày.
  if (url.pathname.startsWith('/uploads/')) {
    event.respondWith(cacheFirst(req, MEDIA, MEDIA_TTL));
    return;
  }

  if (url.pathname.startsWith('/api/')) {
    // Bài Nháp admin xem trước và các API riêng tư: luôn hỏi server.
    if (url.searchParams.has('preview') || NEVER.some((p) => url.pathname.startsWith(p))) return;
    if (url.pathname.startsWith('/api/learn/')) {
      event.respondWith(staleWhileRevalidate(req, LEARN, LEARN_TTL, LEARN_MAX));
    }
    return;
  }

  // SPA: mọi đường dẫn khác trả về index.html đã precache (vào được khi mất mạng).
  if (req.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          return await fetch(req);
        } catch {
          return (await caches.match('/index.html')) ?? Response.error();
        }
      })(),
    );
  }
});
