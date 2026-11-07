// Cách gọi cấp theo giáo trình của người đang học: "HSK 1" hay "Quyển 1". URL vẫn là /hsk/1 vì chỉ là đường dẫn.
import { api } from '$lib/api/client';
import { session } from './session.svelte';

class CapHoc {
  /** Admin xem thử bài của giáo trình khác giáo trình của mình thì gọi theo giáo trình đang xem. */
  private nhanXemTruoc = $state<string | null>(null);
  private idXemTruoc: number | null = null;

  get nhan(): string {
    return this.nhanXemTruoc ?? session.user?.levelLabel ?? 'HSK';
  }

  private get laHsk(): boolean {
    return this.nhan === 'HSK';
  }

  /** Danh từ chung để ghép câu: "ôn cả cấp", "ôn cả quyển". */
  get tu(): string {
    return this.laHsk ? 'cấp' : this.nhan.toLocaleLowerCase('vi');
  }

  /** Như `tu` nhưng đứng đầu câu: "Quyển này chưa có bài nào." */
  get tuHoa(): string {
    return this.laHsk ? 'Cấp' : this.nhan;
  }

  get chon(): string {
    return this.laHsk ? 'Chọn cấp HSK' : `Chọn ${this.tu}`;
  }

  get cacCap(): string {
    return this.laHsk ? 'Các cấp HSK' : `Các ${this.tu}`;
  }

  ten(n: number): string {
    return `${this.nhan} ${n}`;
  }

  /** "cả cấp HSK 1" / "cả quyển 1": ghép `tu` với `ten` sẽ thành "cả quyển Quyển 1". */
  caTen(n: number): string {
    return this.laHsk ? `cả cấp HSK ${n}` : `cả ${this.tu} ${n}`;
  }

  async xemTruoc(curriculumId: number | null): Promise<void> {
    if (curriculumId === this.idXemTruoc) return;
    this.idXemTruoc = curriculumId;
    this.nhanXemTruoc = null;
    if (curriculumId === null) return;
    try {
      const ds = await api.get<{ id: number; levelLabel: string }[]>('/admin/curricula', { quiet: true });
      if (this.idXemTruoc === curriculumId) this.nhanXemTruoc = ds.find((c) => c.id === curriculumId)?.levelLabel ?? null;
    } catch {
      // Chỉ là nhãn của chế độ xem thử: không tải được thì gọi theo giáo trình của tài khoản.
    }
  }
}

export const cap = new CapHoc();
