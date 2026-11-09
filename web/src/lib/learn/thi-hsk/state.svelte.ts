// Trạng thái màn làm đề Thi thử HSK 3.0. Bố cục máy tính và điện thoại dùng chung đối tượng này: kéo cửa sổ qua
// 768px không mất câu đang làm, không ngắt đồng hồ.

import { api, ApiError } from '$lib/api/client';
import { toast } from '$lib/stores/toast.svelte';
import { settings } from '$lib/stores/settings.svelte';
import { answered, type Answer, type Payload } from '$lib/utils/grade';
import { nhomTheoPhan, type Phan } from './cau-truc';
import { donBanGhiCu } from './ghi-am';

export type CheDo = 'exam' | 'practice';

export interface CauHoi {
  id: number;
  no: number;
  type: string;
  score: number;
  section: Phan | null;
  payload: Payload;
}

export interface LuotLam {
  attemptId: number;
  mode: CheDo;
  mock: { id: number; title: string; levelId: number; kind: string; forContest?: boolean } | null;
  /** Lượt gắn với cuộc thi đang mở: tên cuộc thi. */
  contestTitle?: string | null;
  serverNow: string;
  deadlineAt: string;
  durationSec: number;
  listenLimit: number;
  autoSubmit: boolean;
  questions: CauHoi[];
  answers: Record<string, Answer>;
  listenCounts: Record<string, number>;
}

/** Kết quả chấm ngay một câu ở chế độ luyện tập (POST /tests/attempts/{id}/check). */
export interface KiemTra {
  graded: boolean;
  correct: boolean;
  rightAnswerText: string;
  explain: string | null;
  rightIndex: number | null;
  sample?: string;
  sampleVi?: string;
}

export interface DongKetQua {
  qid: number;
  no: number;
  section: Phan | null;
  promptText: string;
  yourAnswerText: string;
  rightAnswerText: string;
  explain: string | null;
}

export interface KetQua {
  attemptId: number;
  score: number;
  maxScore: number;
  percent: number;
  usedSec: number;
  autoSubmitted: boolean;
  correctCount: number;
  questionCount: number;
  mode: CheDo;
  sections: { section: Phan; graded: boolean; total: number; done: number; percent: number }[];
  results: DongKetQua[];
  speaking: DongKetQua[];
  contestTitle?: string | null;
  /** Cuộc thi còn mở: server giấu đáp án và bài mẫu, kẻo người nộp sớm truyền cho người chưa thi. */
  answersHidden?: boolean;
}

export class ThiHsk {
  readonly mockId: number;
  readonly mode: CheDo;

  at = $state<LuotLam | null>(null);
  ketQua = $state<KetQua | null>(null);
  loi = $state('');
  dangTai = $state(true);

  cur = $state(0);
  answers = $state<Record<number, Answer>>({});
  listens = $state<Record<number, number>>({});
  /** Câu đã bấm Kiểm tra (luyện tập): khoá câu và hiện đáp án. */
  kiemTra = $state<Record<number, KiemTra>>({});
  dangKiem = $state(false);
  left = $state(0);
  hoiNop = $state(false);
  dangNop = $state(false);

  #timer: ReturnType<typeof setInterval> | null = null;
  #saveTimer: ReturnType<typeof setTimeout> | null = null;
  #retry: ReturnType<typeof setTimeout> | null = null;
  #skew = 0;
  #beeped = false;

  constructor(mockId: number, mode: CheDo) {
    this.mockId = mockId;
    this.mode = mode;
  }

  readonly q = $derived<CauHoi | null>(this.at?.questions[this.cur] ?? null);
  readonly total = $derived(this.at?.questions.length ?? 0);
  readonly daLam = $derived((this.at?.questions ?? []).filter((x) => answered(x.type, x.payload, this.answers[x.id] ?? null)).length);
  readonly nhom = $derived(nhomTheoPhan(this.at?.questions ?? []));
  /** Getter, không phải $derived: mode gán trong constructor, sau khi các trường $derived đã khởi tạo. */
  get luyenTap(): boolean {
    return this.mode === 'practice';
  }
  readonly coGio = $derived(!!this.at && this.at.mode === 'exam');
  readonly sapHet = $derived(this.coGio && this.left > 0 && this.left < 60);
  /** Hết giờ là khoá màn làm bài cho tới khi nộp xong. */
  readonly hetGio = $derived(this.coGio && !!this.at?.autoSubmit && this.left === 0 && this.ketQua === null);

  async tai(): Promise<void> {
    this.dangTai = true;
    this.loi = '';
    this.ketQua = null;
    this.at = null;
    this.kiemTra = {};
    void donBanGhiCu();
    try {
      const data = await api.post<LuotLam & { resultAttemptId?: number }>('/tests/start', { mockTestId: this.mockId, mode: this.mode });
      // Lượt trước bị tự chấm mà chưa xem: mở thẳng kết quả.
      if (data.resultAttemptId) {
        this.ketQua = await api.get<KetQua>(`/tests/attempts/${data.resultAttemptId}`);
        return;
      }
      this.at = data;
      this.answers = Object.fromEntries(Object.entries(data.answers ?? {}).map(([k, v]) => [Number(k), v]));
      this.listens = Object.fromEntries(Object.entries(data.listenCounts ?? {}).map(([k, v]) => [Number(k), Number(v)]));
      this.cur = 0;
      this.#skew = Date.parse(data.serverNow) - Date.now();
      this.#batDem();
    } catch (e) {
      this.loi = e instanceof ApiError ? e.message || 'Không mở được đề thi.' : 'Không mở được đề thi.';
    } finally {
      this.dangTai = false;
    }
  }

  #batDem(): void {
    this.#dungDem();
    if (!this.coGio) return;
    this.#tick();
    this.#timer = setInterval(() => this.#tick(), 1000);
  }

  #dungDem(): void {
    if (this.#timer) clearInterval(this.#timer);
    this.#timer = null;
  }

  #tick(): void {
    if (!this.at) return;
    this.left = Math.max(0, Math.round((Date.parse(this.at.deadlineAt) - (Date.now() + this.#skew)) / 1000));
    if (this.left === 60 && settings.value.testBeep && !this.#beeped) {
      this.#beeped = true;
      toast.show('Còn 1 phút!');
    }
    if (this.left === 0 && this.at.autoSubmit) {
      this.#dungDem();
      void this.nop(true);
    }
  }

  /** Tự lưu câu trả lời, gộp 1 giây một lần. */
  #luuSau(): void {
    if (this.#saveTimer) clearTimeout(this.#saveTimer);
    this.#saveTimer = setTimeout(() => {
      if (!this.at) return;
      void api.patch(`/tests/attempts/${this.at.attemptId}`, { answers: { ...this.answers }, listenCounts: this.listens }).catch(() => {});
    }, 1000);
  }

  datTraLoi(v: Answer): void {
    const q = this.q;
    if (!q) return;
    // Câu đã kiểm tra thì khoá, trừ câu nói: vẫn được tự đánh giá sau khi xem mẫu.
    if (this.kiemTra[q.id] && q.type !== 'speak') return;
    this.answers = { ...this.answers, [q.id]: v };
    this.#luuSau();
  }

  demNghe(): void {
    const q = this.q;
    if (!q) return;
    this.listens = { ...this.listens, [q.id]: (this.listens[q.id] ?? 0) + 1 };
    this.#luuSau();
  }

  /** Số lần nghe còn lại: chỉ giới hạn khi thi thật, luyện tập nghe thoải mái. */
  conNghe(q: CauHoi): number | null {
    if (!this.at || this.luyenTap || q.type !== 'listen') return null;
    return Math.max(0, this.at.listenLimit - (this.listens[q.id] ?? 0));
  }

  async kiemTraCau(): Promise<void> {
    const q = this.q;
    if (!this.at || !q || this.dangKiem || !this.luyenTap) return;
    this.dangKiem = true;
    try {
      const r = await api.post<KiemTra>(`/tests/attempts/${this.at.attemptId}/check`, { qid: q.id, answer: this.answers[q.id] ?? null });
      this.kiemTra = { ...this.kiemTra, [q.id]: r };
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Chưa kiểm tra được, thử lại nhé.');
    } finally {
      this.dangKiem = false;
    }
  }

  async nop(auto = false): Promise<void> {
    if (!this.at || this.dangNop) return;
    this.dangNop = true;
    this.hoiNop = false;
    this.#dungDem();
    try {
      this.ketQua = await api.post<KetQua>(`/tests/attempts/${this.at.attemptId}/submit`, { answers: { ...this.answers } });
      if (auto) toast.show('Hết giờ — bài đã được nộp.');
    } catch (e) {
      const id = e instanceof ApiError ? (e.extra as { attemptId?: number } | undefined)?.attemptId : undefined;
      if (id) {
        this.ketQua = await api.get<KetQua>(`/tests/attempts/${id}`);
      } else if (auto) {
        // Hết giờ mà mất mạng: màn đã khoá, cứ thử lại; server vẫn giữ các câu đã lưu.
        this.#retry = setTimeout(() => void this.nop(true), 5000);
      } else {
        toast.show('Không nộp được bài, thử lại nhé.');
        this.#batDem();
      }
    } finally {
      this.dangNop = false;
    }
  }

  huy(): void {
    this.#dungDem();
    if (this.#saveTimer) clearTimeout(this.#saveTimer);
    if (this.#retry) clearTimeout(this.#retry);
  }
}
