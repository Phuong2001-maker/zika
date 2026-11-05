import { beforeEach, describe, expect, it } from 'vitest';
import { curricula, type Curriculum } from './demo.svelte';

const book = (id: string, members: string[]): Curriculum => ({
  id,
  name: id,
  members,
  access: 'selected',
  published: true,
  description: '',
  color: '#fff',
  levels: [0, 0, 0, 0, 0, 0],
});

beforeEach(() => {
  curricula.items = [book('a', ['an', 'nam']), book('b', ['linh'])];
});

describe('Mỗi học viên một giáo trình', () => {
  it('chuyển học viên sang bộ mới và giữ nguyên các học viên khác', () => {
    curricula.save(book('b', ['linh', 'an']));
    expect(curricula.items.find((c) => c.id === 'a')?.members).toEqual(['nam']);
    expect(curricula.assignedTo('an')?.id).toBe('b');
    expect(curricula.items.filter((c) => c.members.includes('an'))).toHaveLength(1);
  });
  it('lưu bảng phân giáo trình mà không để lại quyền của bộ cũ', () => {
    curricula.assignAll({ an: 'b', linh: 'a', nam: '', mai: 'b' });
    expect(curricula.items.find((c) => c.id === 'a')?.members).toEqual(['linh']);
    expect(curricula.items.find((c) => c.id === 'b')?.members).toEqual(['an', 'mai']);
    expect(curricula.assignedTo('nam')).toBeUndefined();
  });
});
