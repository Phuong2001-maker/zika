// Hộp Góp ý (07 §1.3): màn nào cũng mở được từ bảng Chuyển nhanh, kèm ngữ cảnh tự động.
// Màn đang hiển thị cập nhật ngữ cảnh bằng feedback.setContext(); component FeedbackModal (gắn ở layout) đọc store này.
import type { Part } from '$lib/api/types';

export interface FeedbackContext {
  part?: Part | null;
  levelId?: number | null;
  lessonId?: number | null;
  lessonNo?: number | null;
  questionId?: number | null;
  questionNo?: number | null;
  /** Mở từ màn làm bài → mặc định loại “Lỗi nội dung”. */
  doing?: boolean;
}

class FeedbackStore {
  /** Đã có hộp Góp ý được gắn (layout người học) — Switcher chỉ hiện dòng Góp ý khi true. */
  available = $state(false);
  isOpen = $state(false);
  ctx = $state<FeedbackContext>({});
  /** Ngữ cảnh mở một lần (ví dụ “Báo đáp án sai” của câu 7) — ưu tiên hơn ngữ cảnh màn. */
  override = $state<FeedbackContext | null>(null);

  setContext(c: FeedbackContext): void {
    this.ctx = c;
  }

  open(extra?: FeedbackContext): void {
    this.override = extra ? { ...this.ctx, ...extra } : null;
    this.isOpen = true;
  }

  close(): void {
    this.isOpen = false;
    this.override = null;
  }
}

export const feedback = new FeedbackStore();
