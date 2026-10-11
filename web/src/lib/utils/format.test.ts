import { describe, expect, it } from 'vitest';
import { ago, clock, date, duration, lastSeen, longDate, minSec, num, pct, time, upperHv } from './format';

describe('format kiểu Việt Nam', () => {
  it('số và phần trăm', () => {
    expect(num(1284)).toBe('1.284');
    expect(num(3.5, 1)).toBe('3,5');
    expect(pct(3.5)).toBe('3,5%');
    expect(pct(16.5)).toBe('16,5%');
    expect(pct(64)).toBe('64%');
    expect(num(null)).toBe('—');
  });

  it('ngày giờ theo múi giờ VN, không phụ thuộc máy', () => {
    const iso = '2026-10-04T20:41:00+07:00';
    expect(date(iso)).toBe('04/10/2026');
    expect(time(iso)).toBe('20:41');
    expect(longDate(iso)).toBe('Chủ nhật, 04/10/2026');
    // 23:30 UTC = 06:30 sáng hôm sau ở VN
    expect(date('2026-10-04T23:30:00Z')).toBe('05/10/2026');
  });

  it('thời lượng', () => {
    expect(duration(180)).toBe('3 phút');
    expect(duration(4200)).toBe('1 giờ 10 phút');
    expect(duration(148800)).toBe('41 giờ 20 phút');
    expect(minSec(250)).toBe('4 phút 10 giây');
    expect(minSec(45)).toBe('45 giây');
    expect(clock(272_000)).toBe('04:32');
    expect(clock(-5)).toBe('00:00');
  });

  it('thời điểm tương đối và lần vào gần nhất', () => {
    const now = new Date('2026-10-04T21:00:00+07:00');
    expect(ago('2026-10-04T20:59:40+07:00', now)).toBe('vừa xong');
    expect(ago('2026-10-04T20:45:00+07:00', now)).toBe('15 phút trước');
    expect(ago('2026-10-04T18:00:00+07:00', now)).toBe('3 giờ trước');
    expect(ago('2026-10-03T21:30:00+07:00', now)).toBe('hôm qua');
    expect(ago('2026-10-01T10:00:00+07:00', now)).toBe('3 ngày trước');
    expect(lastSeen('2026-10-04T20:41:00+07:00', now)).toBe('Hôm nay 20:41');
    expect(lastSeen('2026-10-03T22:47:00+07:00', now)).toBe('Hôm qua 22:47');
    expect(lastSeen('2026-10-01T21:20:00+07:00', now)).toBe('01/10 21:20');
  });

  it('Hán Việt in hoa đúng dấu', () => {
    expect(upperHv('Kê đản')).toBe('KÊ ĐẢN');
  });
});
