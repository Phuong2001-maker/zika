import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vitest/config';

// API PHP chạy ở máy dev: php -S 127.0.0.1:8787 backend/dev-router.php (đổi bằng biến ZIKA_API).
const api = process.env.ZIKA_API ?? 'http://127.0.0.1:8787';

export default defineConfig({
  plugins: [sveltekit()],
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: api, changeOrigin: false },
      '/go': { target: api, changeOrigin: false },
      '/uploads': { target: api, changeOrigin: false },
    },
  },
  build: {
    target: 'es2022',
  },
  test: {
    include: ['src/**/*.test.ts'],
    environment: 'node',
  },
});
