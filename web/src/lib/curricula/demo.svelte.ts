export interface Curriculum {
  id: string;
  name: string;
  description: string;
  color: string;
  published: boolean;
  access: 'all' | 'selected';
  members: string[];
  levels: number[];
}

export const DEMO_LEARNERS = [
  { id: 'an', name: 'Nguyễn Minh An', email: 'minhan@example.com', initials: 'MA' },
  { id: 'linh', name: 'Trần Thảo Linh', email: 'thaolinh@example.com', initials: 'TL' },
  { id: 'nam', name: 'Lê Hoàng Nam', email: 'hoangnam@example.com', initials: 'HN' },
  { id: 'mai', name: 'Phạm Ngọc Mai', email: 'ngocmai@example.com', initials: 'NM' },
];

const initial: Curriculum[] = [
  {
    id: 'hanu',
    name: 'Giáo trình Hán ngữ HANU',
    description: 'Từng bước xây nền tảng tiếng Trung qua từ vựng, hội thoại và ngữ pháp.',
    color: '#E4EEDF',
    published: true,
    access: 'selected',
    members: ['nam'],
    levels: [15, 15, 10, 0, 0, 0],
  },
  {
    id: 'standard',
    name: 'HSK Standard Course',
    description: 'Lộ trình học theo bộ giáo trình chuẩn, từ sơ cấp đến nâng cao.',
    color: '#E3EAF6',
    published: true,
    access: 'selected',
    members: ['an', 'linh'],
    levels: [15, 15, 20, 20, 0, 0],
  },
  {
    id: 'new',
    name: 'Hán ngữ · Lộ trình mới',
    description: 'Bộ bài học mới đang được biên soạn cho sáu cấp HSK.',
    color: '#F0E6D6',
    published: false,
    access: 'selected',
    members: ['mai'],
    levels: [0, 0, 0, 0, 0, 0],
  },
];

// Bản xem trước chỉ lưu trong trình duyệt, độc lập với nội dung và quyền thật trên API.
class CurriculumDemo {
  items = $state<Curriculum[]>(structuredClone(initial));
  learner = $state('an');
  load(): void {
    try {
      const raw = localStorage.getItem('zk.curricula.preview.single.v2');
      if (raw) {
        const saved = JSON.parse(raw) as Curriculum[];
        if (
          Array.isArray(saved) &&
          saved.every(
            (c) =>
              typeof c.id === 'string' &&
              typeof c.name === 'string' &&
              Array.isArray(c.levels) &&
              Array.isArray(c.members) &&
              ['all', 'selected'].includes(c.access),
          )
        )
          this.items = saved;
      }
    } catch {
      /* Bộ nhớ bị chặn hoặc dữ liệu cũ hỏng: dùng bộ mẫu. */
    }
  }
  save(item: Curriculum): void {
    item = { ...item, access: 'selected', members: [...new Set(item.members)] };
    // Gán sang giáo trình mới đồng thời gỡ khỏi bộ cũ: mỗi học viên chỉ có một bộ.
    this.items = this.items.map((c) => (c.id === item.id ? c : { ...c, members: c.members.filter((id) => !item.members.includes(id)) }));
    const index = this.items.findIndex((c) => c.id === item.id);
    if (index < 0) this.items = [...this.items, item];
    else this.items = this.items.map((c) => (c.id === item.id ? item : c));
    try {
      localStorage.setItem('zk.curricula.preview.single.v2', JSON.stringify(this.items));
    } catch {
      /* Vẫn dùng được trong phiên hiện tại. */
    }
  }
  assignedTo(id: string): Curriculum | undefined {
    return this.items.find((c) => c.members.includes(id));
  }
  assignAll(assignments: Record<string, string>): void {
    this.items = this.items.map((c) => ({
      ...c,
      access: 'selected',
      members: DEMO_LEARNERS.filter((u) => assignments[u.id] === c.id).map((u) => u.id),
    }));
    try {
      localStorage.setItem('zk.curricula.preview.single.v2', JSON.stringify(this.items));
    } catch {
      /* Giữ bản xem trước trong phiên. */
    }
  }
  allowed(c: Curriculum): boolean {
    return c.published && (c.access === 'all' || c.members.includes(this.learner));
  }
}

export const curricula = new CurriculumDemo();
