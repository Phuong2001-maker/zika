import { describe, expect, it } from 'vitest';
import { answered, check, isChoice } from '$lib/utils/grade';
import { canView } from '$lib/auth/routes';
import { isActiveTest, routeFeature } from '$lib/auth/features';
import { chiaDe, loiDan, moTaPhan, nhomTheoPhan } from './cau-truc';
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

  it('tờ đề chia phần như đề giấy: 一、听力 → 第一部分…, nhóm là các câu liền nhau cùng dạng (15/11/2026)', () => {
    const q = (section: string, type: string, options: string[] = []) => ({ section, type, payload: { options } });
    const de = chiaDe([
      q('listening', 'listen', ['Đúng', 'Sai']),
      q('listening', 'listen', ['Sai', 'Đúng']),
      q('listening', 'listen', ['他', '她', '它']),
      q('listening', 'pic'),
      q('reading', 'blank'),
      q('reading', 'blank'),
      q('writing', 'go'),
    ]);
    expect(de.map((p) => p.han)).toEqual(['一、听力', '二、阅读', '三、书写']);
    expect(de[0].bo.map((b) => [b.han, b.cau.length])).toEqual([['第一部分', 2], ['第二部分', 1], ['第三部分', 1]]);
    expect(de[0].bo[2].cau[0].k).toBe(3);
    expect(de[2].bo[0].cachLam).toBe('Nhìn pinyin, viết chữ Hán vào ô.');
    expect(loiDan('pic', 'reading', {})).toBe('Đọc câu rồi chọn tranh đúng.');
    // Cùng dạng chọn tranh nhưng đổi bộ tranh A–F dùng chung → sang phần mới như đề giấy.
    const nhomA = ['a', 'b', 'c', 'd', 'e', 'f'];
    const nhomB = ['g', 'h', 'i', 'j', 'k'];
    const tranh = chiaDe([q('listening', 'pic', ['x', 'y', 'z']), q('listening', 'pic', ['u', 'v', 'w']), q('listening', 'pic', nhomA), q('listening', 'pic', nhomA), q('listening', 'pic', nhomB)]);
    expect(tranh[0].bo.map((b) => b.cau.length)).toEqual([2, 2, 1]);
  });

  it('viết chữ Hán theo pinyin: đã làm khi gõ chữ, chấm đúng chữ; tranh tải lên giữ nguyên đường dẫn', () => {
    expect(answered('go', {}, '茶')).toBe(true);
    expect(answered('go', {}, ' ')).toBe(false);
    expect(check('go', { answer: '茶' }, ' 茶')).toBe(true);
    expect(check('go', { answer: '茶' }, '查')).toBe(false);
    expect(urlHinh('/uploads/hinh-de/hsk2-mp1/c01-a.jpg')).toBe('/uploads/hinh-de/hsk2-mp1/c01-a.jpg');
    expect(urlHinh('cha')).toBe('/hinh-hsk/cha.svg');
  });
});
