import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vitest/config';

// API PHP chạy ở máy dev: php -S 127.0.0.1:8787 api/dev-router.php (đổi bằng biến ZIKA_API).
const api = process.env.ZIKA_API ?? 'http://127.0.0.1:8787';

const PROXY = {
  '/api': { target: api, changeOrigin: false },
  '/go': { target: api, changeOrigin: false },
  '/uploads': { target: api, changeOrigin: false },
};

export default defineConfig({
  plugins: [sveltekit()],
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    proxy: PROXY,
  },
  // `npm run preview` phục vụ bản build thật (có service worker) — cũng cần API để thử PWA.
  preview: {
    // Buộc IPv4: để mặc định, vite chỉ nghe ::1 nên trình duyệt phân giải localhost
    // ra 127.0.0.1 sẽ bị từ chối kết nối.
    host: '127.0.0.1',
    port: 4173,
    strictPort: true,
    proxy: PROXY,
  },
  build: {
    target: 'es2022',
  },
  test: {
    include: ['src/**/*.test.ts'],
    environment: 'node',
  },
});
