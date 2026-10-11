// Kiểu toàn cục của SvelteKit.
declare global {
  namespace App {
    interface Error {
      message: string;
      code?: string;
    }
    // Trạng thái điều hướng khi mở màn Lật thẻ từ Chọn bài (07 §4.2).
    interface PageState {
      shuffle?: boolean;
      fromStart?: boolean;
    }
  }
}

export {};
