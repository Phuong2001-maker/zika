// Nén ảnh đại diện ngay trên trình duyệt trước khi gửi: ảnh chụp điện thoại 3–5MB còn ~15KB,
// tải lên nhanh trên 3G và server không phải giải nén ảnh to (RAM hosting có hạn).
// Server vẫn tự cắt/nén lại lần nữa (Upload::image) — đây chỉ để gửi đi cho nhẹ.

/** Cạnh ảnh gửi lên: gấp đôi cỡ server lưu (192px) để lần nén thứ hai không làm nhoè. */
export const CANH_GUI = 384;

/** Ô vuông cắt giữa ảnh (ảnh đại diện luôn hiện tròn). */
export function catVuong(w: number, h: number): { sx: number; sy: number; canh: number } {
  const canh = Math.min(w, h);
  return { sx: Math.floor((w - canh) / 2), sy: Math.floor((h - canh) / 2), canh };
}

/**
 * Đọc tệp ảnh, cắt vuông giữa, thu về CANH_GUI px, trả WebP (hoặc JPEG khi trình duyệt không
 * mã hoá được WebP — Safari cũ trả về PNG nặng hơn nhiều).
 */
export async function nenAnhDaiDien(tep: File): Promise<Blob> {
  // imageOrientation: ảnh chụp dọc trên điện thoại lưu xoay bằng EXIF, không đọc thì bị nằm ngang.
  const bm = await createImageBitmap(tep, { imageOrientation: 'from-image' });
  const { sx, sy, canh } = catVuong(bm.width, bm.height);
  const ra = Math.min(CANH_GUI, canh);
  const cv = document.createElement('canvas');
  cv.width = ra;
  cv.height = ra;
  const ctx = cv.getContext('2d');
  if (!ctx) throw new Error('Trình duyệt không vẽ được ảnh.');
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(bm, sx, sy, canh, canh, 0, 0, ra, ra);
  bm.close();

  const xuat = (type: string, q: number) => new Promise<Blob | null>((ok) => cv.toBlob(ok, type, q));
  const webp = await xuat('image/webp', 0.9);
  if (webp && webp.type === 'image/webp') return webp;
  const jpg = await xuat('image/jpeg', 0.9);
  if (!jpg) throw new Error('Không nén được ảnh.');
  return jpg;
}
