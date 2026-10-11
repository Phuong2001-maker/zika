// Thông báo nổi góc dưới (05 §7.10).

export interface ToastItem {
  id: number;
  text: string;
  kind: 'info' | 'ok';
  action?: { label: string; run: () => void };
}

class Toasts {
  items = $state<ToastItem[]>([]);
  private seq = 0;

  show(text: string, kind: ToastItem['kind'] = 'info', ms = 3500, action?: ToastItem['action']): number {
    // Không lặp cùng một câu đang hiện.
    const dup = this.items.find((t) => t.text === text);
    if (dup) return dup.id;
    const id = ++this.seq;
    this.items = [...this.items, { id, text, kind, action }];
    if (ms > 0) setTimeout(() => this.dismiss(id), ms);
    return id;
  }

  ok(text: string, ms = 3000): number {
    return this.show(text, 'ok', ms);
  }

  dismiss(id: number): void {
    this.items = this.items.filter((t) => t.id !== id);
  }
}

export const toast = new Toasts();
