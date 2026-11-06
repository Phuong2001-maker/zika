import { setPreview } from '$lib/api/client';
import type { LayoutLoad } from './$types';

// SPA tĩnh (02 §3.1): không render phía server, không prerender, URL không có “/” cuối.
export const ssr = false;
export const prerender = false;
export const trailingSlash = 'never';

/**
 * Nút Xem thử ở trang soạn bài mở màn người học kèm ?preview=1. Đặt ở load vì chạy trước khi màn con gọi API.
 * Giữ chế độ này khi đi tiếp trong khu học, rời khu học thì tắt.
 */
export const load: LayoutLoad = ({ url }) => {
  if (url.searchParams.get('preview') === '1') {
    setPreview(true, Number(url.searchParams.get('curriculum')) || null);
  } else if (!url.pathname.startsWith('/learn/')) {
    setPreview(false);
  }
  return {};
};
