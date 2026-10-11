// Điểm ngắt (05 §6): người học < 768px → bố cục điện thoại; quản trị < 900px.
// Mỗi màn render ĐÚNG MỘT bố cục (Desktop hoặc Mobile), không render cả hai rồi ẩn bằng CSS.

class Media {
  isMobile = $state(false);
  isAdminMobile = $state(false);
  reducedMotion = $state(false);

  constructor() {
    if (typeof window === 'undefined') return;
    const bind = (q: string, set: (v: boolean) => void) => {
      const mq = window.matchMedia(q);
      set(mq.matches);
      mq.addEventListener('change', (e) => set(e.matches));
    };
    bind('(max-width: 767px)', (v) => (this.isMobile = v));
    bind('(max-width: 899px)', (v) => (this.isAdminMobile = v));
    bind('(prefers-reduced-motion: reduce)', (v) => (this.reducedMotion = v));
  }
}

export const media = new Media();
