import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** @type {import('@sveltejs/kit').Config} */
const config = {
  preprocess: vitePreprocess(),
  kit: {
    // SPA tĩnh: mọi route không phải file thật → index.html (.htaccess xử lý trên hosting).
    adapter: adapter({ pages: 'build', assets: 'build', fallback: 'index.html', precompress: false }),
    csp: {
      mode: 'hash',
      directives: {
        'default-src': ['self'],
        'script-src': ['self'],
        'style-src': ['self', 'unsafe-inline', 'https://fonts.googleapis.com'],
        'font-src': ['self', 'https://fonts.gstatic.com'],
        'img-src': ['self', 'data:', 'blob:', 'https://lh3.googleusercontent.com'],
        'connect-src': ['self'],
        'media-src': ['self', 'blob:'],
        'worker-src': ['self'],
        'base-uri': ['self'],
        'form-action': ['self'],
      },
    },
  },
};

export default config;
