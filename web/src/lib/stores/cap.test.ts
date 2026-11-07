import { afterEach, describe, expect, it } from 'vitest';
import type { User } from '$lib/api/types';
import { cap } from './cap.svelte';
import { session } from './session.svelte';

const dung = (levelLabel: string): void => session.setUser({ levelLabel } as User);

describe('cách gọi cấp theo giáo trình', () => {
  afterEach(() => session.clear());

  it('chưa đăng nhập thì gọi HSK', () => {
    expect(cap.ten(1)).toBe('HSK 1');
    expect(cap.chon).toBe('Chọn cấp HSK');
  });

  it('giáo trình HSK giữ nguyên chữ cũ', () => {
    dung('HSK');
    expect(cap.ten(3)).toBe('HSK 3');
    expect(cap.tu).toBe('cấp');
    expect(cap.tuHoa).toBe('Cấp');
    expect(cap.cacCap).toBe('Các cấp HSK');
    expect(cap.caTen(3)).toBe('cả cấp HSK 3');
  });

  it('giáo trình theo quyển ghép câu không lặp chữ', () => {
    dung('Quyển');
    expect(cap.ten(3)).toBe('Quyển 3');
    expect(cap.tu).toBe('quyển');
    expect(cap.tuHoa).toBe('Quyển');
    expect(cap.chon).toBe('Chọn quyển');
    expect(cap.cacCap).toBe('Các quyển');
    expect(cap.caTen(3)).toBe('cả quyển 3');
  });
});
