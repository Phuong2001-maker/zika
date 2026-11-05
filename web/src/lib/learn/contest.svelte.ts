import { api } from '$lib/api/client';

export interface Contest {
  id: number;
  title: string;
  status: 'upcoming' | 'open';
  startsInSec: number;
  endsInSec: number;
  startsLabel: string;
  endsLabel: string;
  durationMin: number;
  questionCount: number;
  levelId: number;
  /** Chỉ có khi đã mở thi. */
  mockTestId: number | null;
  myAttempt: 'none' | 'doing' | 'submitted';
  registered: boolean;
  registrantCount: number;
}

export interface Prize {
  label: string;
  percent: number;
  /** Tính từ Quỹ tháng; chưa nhập quỹ thì null. */
  amount: number | null;
}

export interface Registrants {
  contest: { id: number; title: string } | null;
  rows: { name: string; isMe: boolean; at: string }[];
  count: number;
}

export interface RankRow {
  rank: number;
  name: string;
  correct: number;
  total: number;
  usedSec: number;
  isMe: boolean;
}

export interface Ranking {
  contest: { id: number; title: string; status: 'upcoming' | 'open' | 'ended' } | null;
  rows: RankRow[];
  me: RankRow | null;
  participants: number;
}

class ContestStore {
  current = $state<Contest | null>(null);
  ranking = $state<Ranking | null>(null);
  prizes = $state<Prize[]>([]);
  registrants = $state<Registrants | null>(null);

  async refreshRegistrants(): Promise<void> {
    try {
      this.registrants = await api.get<Registrants>('/contest/registrants', { quiet: true });
    } catch {
      // Giữ danh sách đang hiện.
    }
  }

  /** Lỗi để nơi gọi báo cho người học: bấm đăng ký mà không được thì phải biết. */
  async setRegistered(on: boolean): Promise<void> {
    if (!this.current) return;
    const contestId = this.current.id;
    if (on) await api.post('/contest/register', { contestId });
    else await api.del(`/contest/register?contestId=${contestId}`);
    await Promise.all([this.refresh(), this.refreshRegistrants()]);
  }

  async refreshRanking(): Promise<void> {
    try {
      this.ranking = await api.get<Ranking>('/contest/ranking', { quiet: true });
    } catch {
      // Giữ bảng đang hiện nếu có; chưa có thì phần giao diện báo chưa có kết quả.
    }
  }

  async refresh(): Promise<void> {
    try {
      const data = await api.get<{ contest: Contest | null; prizes: Prize[] }>('/contest/current', { quiet: true });
      this.current = data.contest;
      this.prizes = data.prizes;
    } catch {
      // Lỗi mạng thì giữ nguyên thứ đang hiện, đồng hồ vẫn chạy tiếp.
    }
  }
}

export const contestStore = new ContestStore();
