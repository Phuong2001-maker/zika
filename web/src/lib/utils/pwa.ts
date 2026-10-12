// Đăng ký service worker và báo khi có bản mới (02 §3.5).
// Service worker chỉ chạy ở bản build thật (dev server không sinh file /service-worker.js).

/**
 * Đăng ký service worker. Khi có bản mới đã tải xong và đang chờ, gọi `onUpdate`
 * với hàm để kích hoạt bản mới rồi tải lại trang.
 *
 * @returns hàm huỷ đăng ký bộ lắng nghe (dùng trong `$effect`/`onMount`).
 */
export function registerServiceWorker(onUpdate: (apply: () => void) => void): () => void {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return () => {};

  let reloading = false;
  const onControllerChange = (): void => {
    // Bản mới đã nắm quyền → tải lại đúng một lần.
    if (reloading) return;
    reloading = true;
    location.reload();
  };
  navigator.serviceWorker.addEventListener('controllerchange', onControllerChange);

  void navigator.serviceWorker
    .register('/service-worker.js', { scope: '/' })
    .then((reg) => {
      const apply = (): void => {
        reg.waiting?.postMessage({ type: 'skip-waiting' });
      };
      // Bản mới đã chờ sẵn từ lần mở trước.
      if (reg.waiting && navigator.serviceWorker.controller) onUpdate(apply);

      reg.addEventListener('updatefound', () => {
        const next = reg.installing;
        if (!next) return;
        next.addEventListener('statechange', () => {
          // `controller` null = lần cài đầu tiên, không phải bản cập nhật.
          if (next.state === 'installed' && navigator.serviceWorker.controller) onUpdate(apply);
        });
      });
    })
    .catch(() => {
      /* chạy ở dev hoặc trình duyệt chặn: bỏ qua, web vẫn chạy bình thường */
    });

  return () => navigator.serviceWorker.removeEventListener('controllerchange', onControllerChange);
}
