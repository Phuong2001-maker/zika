// Font phụ chỉ tải khi cần (02 §3.1): mỗi font chèn <link> đúng 1 lần.
const URLS: Record<string, string> = {
  'Ma Shan Zheng': 'https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&display=swap',
  'Noto Serif SC': 'https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@500&display=swap',
};

const loaded = new Set<string>();

export function loadFont(family: keyof typeof URLS | string): void {
  if (typeof document === 'undefined' || loaded.has(family) || !URLS[family]) return;
  loaded.add(family);
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = URLS[family];
  document.head.appendChild(link);
}
