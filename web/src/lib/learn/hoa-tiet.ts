import { loadFont } from '$lib/utils/fonts';

/** Số Hán kiểu chữ kép (壹 贰 叁…) của cấp HSK, làm hoạ tiết mờ ở thẻ cấp và đầu trang cấp.
 *  Không dùng 一 二 三: viết bút lông chỉ là vài nét ngang, in mờ trông như vết bẩn. */
export const SO_HAN = ['', '壹', '贰', '叁', '肆', '伍', '陆'];

/** Hoạ tiết viết bằng font bút lông; chỉ tải khi màn có hoạ tiết (font chữ Hán nặng). */
export function taiChuBut(): void {
  loadFont('Ma Shan Zheng');
}
