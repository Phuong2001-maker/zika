// Trạng thái 3 chế độ luyện từ ở màn Lật thẻ. Để ngoài component vì màn điện thoại và máy tính
// dựng hai cây khác nhau: kéo cửa sổ qua 768px giữa chừng thì đối tượng này vẫn giữ nguyên tiến độ.

import { SvelteSet } from 'svelte/reactivity';
import type { Word } from '$lib/api/types';
import { shuffle } from '$lib/utils/shuffle';
import { chiaDeu, ghepDung, goDung, taoDapAn } from './cham';

export type CheDo = 'the' | 'ghep' | 'tn' | 'go';

export const CHE_DO: { ma: CheDo; ten: string }[] = [
  { ma: 'the', ten: 'Lật thẻ' },
  { ma: 'ghep', ten: 'Ghép cặp' },
  { ma: 'tn', ten: 'Trắc nghiệm' },
  { ma: 'go', ten: 'Gõ chữ' },
];

export interface KetQua {
  dung: number;
  tong: number;
  giay: number;
  /** Từ trả lời sai ít nhất một lần, hoặc phải xem gợi ý / bỏ qua — đem đi luyện lại. */
  sai: Word[];
}

/** Một lượt chơi: đồng hồ, danh sách từ cần ôn, báo ra ngoài khi xong. */
abstract class Phien {
  readonly batDau = Date.now();
  xongLuc = $state<number | null>(null);
  readonly sai = new SvelteSet<number>();

  constructor(
    readonly tu: Word[],
    private readonly onXong?: () => void,
  ) {}

  get xong(): boolean {
    return this.xongLuc !== null;
  }

  giay(now: number): number {
    return ((this.xongLuc ?? now) - this.batDau) / 1000;
  }

  ketQua(): KetQua {
    const sai = this.tu.filter((w) => this.sai.has(w.id));
    return { dung: this.tu.length - sai.length, tong: this.tu.length, giay: this.giay(Date.now()), sai };
  }

  protected ketThuc(): void {
    if (this.xong) return;
    this.xongLuc = Date.now();
    this.onXong?.();
  }
}

/** Ghép chữ Hán với nghĩa, mỗi lượt tối đa 6 cặp. */
export class GhepCap extends Phien {
  readonly luot: Word[][];
  trang = $state(0);
  trai = $state.raw<Word[]>([]);
  phai = $state.raw<Word[]>([]);
  chonTrai = $state<number | null>(null);
  chonPhai = $state<number | null>(null);
  readonly xongTrai = new SvelteSet<number>();
  readonly xongPhai = new SvelteSet<number>();
  /** Cặp vừa ghép sai, để rung đỏ trong chốc lát rồi bỏ chọn. */
  rung = $state<{ trai: number; phai: number } | null>(null);
  daGhep = $state(0);

  constructor(tu: Word[], onXong?: () => void) {
    super(tu, onXong);
    this.luot = chiaDeu(shuffle(tu), 6);
    this.moLuot(0);
  }

  private moLuot(k: number): void {
    const l = this.luot[k] ?? [];
    this.trang = k;
    this.trai = shuffle(l);
    this.phai = shuffle(l);
    this.xongTrai.clear();
    this.xongPhai.clear();
    this.chonTrai = null;
    this.chonPhai = null;
  }

  /** Bấm một ô. Trả 'dung' / 'sai' khi vừa đủ một cặp để component phát tiếng hoặc rung. */
  chon(ben: 'trai' | 'phai', i: number): 'dung' | 'sai' | null {
    if (this.xong || this.rung) return null;
    if (ben === 'trai') {
      if (this.xongTrai.has(i)) return null;
      this.chonTrai = this.chonTrai === i ? null : i;
    } else {
      if (this.xongPhai.has(i)) return null;
      this.chonPhai = this.chonPhai === i ? null : i;
    }
    const ti = this.chonTrai;
    const pi = this.chonPhai;
    if (ti === null || pi === null) return null;

    const t = this.trai[ti];
    const p = this.phai[pi];
    if (ghepDung(t, p)) {
      this.xongTrai.add(ti);
      this.xongPhai.add(pi);
      this.daGhep++;
      this.chonTrai = null;
      this.chonPhai = null;
      if (this.xongTrai.size === this.trai.length) {
        if (this.trang + 1 >= this.luot.length) this.ketThuc();
        // Dừng một nhịp cho người học thấy cặp cuối mờ đi rồi mới sang lượt mới.
        else setTimeout(() => this.moLuot(this.trang + 1), 450);
      }
      return 'dung';
    }

    // Nhầm thì cả hai từ đều đáng ôn lại: chưa nhớ chữ này, hoặc lẫn nghĩa kia với nó.
    this.sai.add(t.id);
    this.sai.add(p.id);
    this.rung = { trai: ti, phai: pi };
    setTimeout(() => {
      this.rung = null;
      this.chonTrai = null;
      this.chonPhai = null;
    }, 450);
    return 'sai';
  }
}

/** Nhìn chữ Hán, chọn 1 trong 4 nghĩa. Trả lời sai thì từ đó được hỏi lại một lần ở cuối. */
export class TracNghiem extends Phien {
  hangDoi = $state.raw<Word[]>([]);
  vt = $state(0);
  dapAn = $state.raw<string[]>([]);
  dungIdx = $state(0);
  chon = $state<number | null>(null);
  private readonly hoiLai = new SvelteSet<number>();

  /** `kho` là cả bài: luyện lại vài từ sai vẫn lấy được đủ đáp án nhiễu. */
  constructor(
    tu: Word[],
    private readonly kho: Word[],
    onXong?: () => void,
  ) {
    super(tu, onXong);
    this.hangDoi = shuffle(tu);
    this.moCau();
  }

  get cau(): Word | null {
    return this.hangDoi[this.vt] ?? null;
  }

  get daTraLoi(): boolean {
    return this.chon !== null;
  }

  private moCau(): void {
    const w = this.cau;
    if (!w) return;
    const { dapAn, dung } = taoDapAn(w, this.kho);
    this.dapAn = dapAn;
    this.dungIdx = dung;
    this.chon = null;
  }

  traLoi(i: number): boolean | null {
    const w = this.cau;
    if (!w || this.chon !== null || this.xong || i < 0 || i >= this.dapAn.length) return null;
    this.chon = i;
    if (i === this.dungIdx) return true;
    this.sai.add(w.id);
    if (!this.hoiLai.has(w.id)) {
      this.hoiLai.add(w.id);
      this.hangDoi = [...this.hangDoi, w];
    }
    return false;
  }

  tiep(): void {
    if (this.chon === null || this.xong) return;
    if (this.vt + 1 >= this.hangDoi.length) {
      this.ketThuc();
      return;
    }
    this.vt++;
    this.moCau();
  }
}

/** Nhìn nghĩa, gõ chữ Hán hoặc pinyin. Gợi ý lần 1 hiện pinyin, lần 2 hiện chữ Hán. */
export class GoChu extends Phien {
  readonly ds: Word[];
  vt = $state(0);
  nhap = $state('');
  goiY = $state(0);
  trangThai = $state<'go' | 'dung' | 'sai'>('go');

  constructor(tu: Word[], onXong?: () => void) {
    super(tu, onXong);
    this.ds = shuffle(tu);
  }

  get cau(): Word | null {
    return this.ds[this.vt] ?? null;
  }

  kiemTra(): void {
    const w = this.cau;
    if (!w || this.trangThai !== 'go' || this.nhap.trim() === '') return;
    const ok = goDung(this.nhap, w);
    this.trangThai = ok ? 'dung' : 'sai';
    // Đúng nhờ xem gợi ý vẫn đưa vào danh sách ôn: chưa tự nhớ ra được.
    if (!ok || this.goiY > 0) this.sai.add(w.id);
  }

  boQua(): void {
    const w = this.cau;
    if (!w || this.trangThai !== 'go') return;
    this.sai.add(w.id);
    this.trangThai = 'sai';
  }

  xinGoiY(): void {
    if (this.trangThai === 'go' && this.goiY < 2) this.goiY++;
  }

  tiep(): void {
    if (this.trangThai === 'go' || this.xong) return;
    if (this.vt + 1 >= this.ds.length) {
      this.ketThuc();
      return;
    }
    this.vt++;
    this.nhap = '';
    this.goiY = 0;
    this.trangThai = 'go';
  }
}
