import { describe, expect, it } from 'vitest';
import { answered, isChoice } from '$lib/utils/grade';
import { canView } from '$lib/auth/routes';
import { isActiveTest, routeFeature } from '$lib/auth/features';
import { moTaPhan, nhomTheoPhan } from './cau-truc';
import { chonDinhDang, khoaBanGhi, loiGhiAm } from './ghi-am';
import { HINH, maTuUrl, urlHinh } from './hinh';

describe('Thi thử HSK 3.0', () => {
  it('có quyền riêng, không theo quyền Kiểm tra; lượt đang làm vẫn vào lại được', () => {
    expect(routeFeature('/learn/hsk30')).toBe('learn.hsk30');
    expect(routeFeature('/learn/hsk30/12?mode=practice')).toBe('learn.hsk30');
    expect(canView('learner', '/learn/hsk30', { 'learn.hsk30': true, 'learn.tests': false })).toBe(true);
    expect(canView('learner', '/learn/hsk30/3', { 'learn.hsk30': false })).toBe(false);
    expect(isActiveTest('/learn/hsk30/3', [{ mockId: 3, levelId: 1, lessonNo: null, kind: 'hsk30' }])).toBe(true);
    expect(isActiveTest('/learn/hsk30/4', [{ mockId: 3, levelId: 1, lessonNo: null }])).toBe(false);
  });

  it('câu nói tính là đã làm khi đã ghi âm hoặc tự đánh giá; chọn tranh là trắc nghiệm', () => {
    expect(answered('speak', {}, 'ghi')).toBe(true);
    expect(answered('speak', {}, 'tot')).toBe(true);
    expect(answered('speak', {}, '')).toBe(false);
    expect(answered('speak', {}, 'bat-ky')).toBe(false);
    expect(answered('speak', {}, null)).toBe(false);
    expect(isChoice('pic')).toBe(true);
    expect(answered('pic', { options: ['tao', 'cha'] }, 1)).toBe(true);
  });

  it('chọn định dạng ghi âm trình duyệt hỗ trợ, Safari rơi xuống mp4', () => {
    expect(chonDinhDang((t) => t.startsWith('audio/webm'))).toBe('audio/webm;codecs=opus');
    expect(chonDinhDang((t) => t === 'audio/mp4')).toBe('audio/mp4');
    expect(chonDinhDang(() => false)).toBe('');
    expect(chonDinhDang(() => {
      throw new Error('cũ');
    })).toBe('');
  });

  it('báo rõ vì sao không ghi âm được', () => {
    const ok = { isSecureContext: true, navigator: { mediaDevices: { getUserMedia: () => null } }, MediaRecorder: class {} };
    expect(loiGhiAm(ok)).toBeNull();
    expect(loiGhiAm({ ...ok, isSecureContext: false })).toMatch(/https/);
    expect(loiGhiAm({ ...ok, MediaRecorder: undefined })).toMatch(/Trình duyệt/);
    expect(loiGhiAm({ ...ok, navigator: {} })).toMatch(/Trình duyệt/);
    expect(khoaBanGhi(12, 345)).toBe('12:345');
  });

  it('gom câu theo phần và mô tả số câu từng phần', () => {
    const ds = [{ section: 'listening' }, { section: 'listening' }, { section: 'reading' }, { section: null }, { section: 'speaking' }];
    expect(nhomTheoPhan(ds).map((g) => [g.phan, g.cau.map((c) => c.k)])).toEqual([
      ['listening', [0, 1]],
      ['reading', [2, 3]],
      ['speaking', [4]],
    ]);
    expect(moTaPhan({ listening: 20, reading: 20, writing: 0 })).toBe('Nghe 20 · Đọc 20');
  });

  it('kho hình: mã hợp lệ, không trùng, đọc lại được từ đường dẫn', () => {
    const ma = HINH.map((h) => h.ma);
    expect(new Set(ma).size).toBe(ma.length);
    expect(ma.every((m) => /^[a-z0-9][a-z0-9-]{0,39}$/.test(m))).toBe(true);
    expect(maTuUrl(urlHinh('an-com'))).toBe('an-com');
    expect(maTuUrl('https://x.test/a.svg')).toBeNull();
  });
});
