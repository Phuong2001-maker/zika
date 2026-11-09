import { api } from '$lib/api/client';
import { session } from '$lib/stores/session.svelte';

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
  /** Đề khung HSK 3.0 (đề tab Đề thi): làm ở màn thi HSK 3.0 /learn/cuoc-thi/{id}, có phần và câu nói ghi âm. */
  hsk30: boolean;
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
  /** false khi đã đăng ký nhưng không vào làm bài trước lúc đóng thi. */
  participated: boolean;
  isMe: boolean;
}

export interface Ranking {
  contest: { id: number; title: string; status: 'upcoming' | 'open' | 'ended' } | null;
  rows: RankRow[];
  me: RankRow | null;
  participants: number;
}

/** Một dòng Top chăm học: phút học trong tháng, ngày có học, số phần bài học xong lần đầu trong tháng. */
export interface StudyRow {
  rank: number;
  name: string;
  minutes: number;
  days: number;
  done: number;
  isMe: boolean;
}

export interface StudyTop {
  month: string;
  rows: StudyRow[];
  /** null khi tháng này mình chưa học đủ một phút. */
  me: StudyRow | null;
  count: number;
}

/** Cuộc thi đã kết thúc. Không có số tiền: quỹ không chụp lại theo từng cuộc thi. */
export interface PastContest {
  id: number;
  title: string;
  endedOn: string;
  participants: number;
  top: RankRow[];
  me: RankRow | null;
}

class ContestStore {
  current = $state<Contest | null>(null);
  ranking = $state<Ranking | null>(null);
  studyTop = $state<StudyTop | null>(null);
  history = $state<PastContest[] | null>(null);
  prizes = $state<Prize[]>([]);
  registrants = $state<Registrants | null>(null);

  async refreshRegistrants(): Promise<void> {
    if (!session.can('community.contests.registrants')) { this.registrants = null; return; }
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
    if (!session.can('community.contests.ranking')) { this.ranking = null; return; }
    try {
      this.ranking = await api.get<Ranking>('/contest/ranking', { quiet: true });
    } catch {
      // Giữ bảng đang hiện nếu có; chưa có thì phần giao diện báo chưa có kết quả.
    }
  }

  async refreshStudyTop(): Promise<void> {
    if (!session.can('community.study')) { this.studyTop = null; return; }
    try {
      this.studyTop = await api.get<StudyTop>('/contest/study-top', { quiet: true });
    } catch {
      // Giữ bảng đang hiện.
    }
  }

  async refreshHistory(): Promise<void> {
    if (!session.can('community.contests.history')) { this.history = []; return; }
    try {
      this.history = await api.get<PastContest[]>('/contest/history', { quiet: true });
    } catch {
      if (this.history === null) this.history = [];
    }
  }

  async refresh(): Promise<void> {
    if (!session.can('community.contests')) { this.current = null; this.prizes = []; return; }
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
