import { describe, expect, it } from 'vitest';
import type { Sheet } from '$lib/admin/xlsx';
import { answerIndex, headerMap, norm, parseBool, splitList } from './core';
import { wordsImport } from './words';
import { questionsImport } from './questions';
import { writingImport } from './writing';
import { dialogueImport } from './dialogue';
import { grammarImport } from './grammar';

const sheet = (name: string, rows: string[][]): Sheet => ({ name, rows });

describe('hàm chung', () => {
  it('norm bỏ dấu để tiêu đề gõ kiểu nào cũng khớp', () => {
    expect(norm('Dạng câu')).toBe('dangcau');
    expect(norm('  CHỮ HÁN  ')).toBe('chuhan');
    expect(norm('Nghĩa tiếng Việt')).toBe('nghiatiengviet');
  });

  it('answerIndex nhận cả chữ cái lẫn số', () => {
    expect(answerIndex('A')).toBe(0);
    expect(answerIndex('d')).toBe(3);
    expect(answerIndex('2')).toBe(1);
    expect(answerIndex('')).toBe(-1);
    expect(answerIndex('E')).toBe(-1);
  });

  it('parseBool hiểu tiếng Việt', () => {
    expect(parseBool('Đúng')).toBe(true);
    expect(parseBool('sai')).toBe(false);
    expect(parseBool('Có')).toBe(true);
    expect(parseBool('Không')).toBe(false);
    expect(parseBool('', true)).toBe(true);
    expect(parseBool('abc')).toBe(null);
  });

  it('splitList ngăn bằng | hoặc xuống dòng, bỏ ô rỗng', () => {
    expect(splitList('a|b || c')).toEqual(['a', 'b', 'c']);
    expect(splitList('a\nb')).toEqual(['a', 'b']);
    expect(splitList('')).toEqual([]);
  });

  it('headerMap tìm được dòng tiêu đề dù có dòng ghi chú ở trên', () => {
    const rows = [
      ['File mẫu Zìkǎ — xoá dòng này cũng được'],
      [],
      ['Chữ Hán', 'Pinyin', 'Nghĩa tiếng Việt'],
      ['鸡蛋', 'jīdàn', 'trứng gà'],
    ];
    const { at, index } = headerMap(rows, wordsImport.sheets[0].columns);
    expect(at).toBe(2);
    expect(index.hanzi).toBe(0);
    expect(index.meaning).toBe(2);
  });
});

describe('nhập từ vựng', () => {
  it('đọc từ, ví dụ và loại từ tiếng Việt', () => {
    const s = sheet('Từ vựng', [
      ['Chữ Hán', 'Pinyin', 'Hán Việt', 'Nghĩa tiếng Việt', 'Loại từ', 'Ví dụ', 'Ví dụ pinyin', 'Ví dụ nghĩa'],
      ['鸡蛋', 'jīdàn', 'Kê đản', 'trứng gà', 'danh từ', '我吃鸡蛋。|他吃鸡蛋。', 'Wǒ chī jīdàn.|Tā chī jīdàn.', 'Tôi ăn trứng.|Anh ấy ăn trứng.'],
      ['喝', 'hē', '', 'uống', 'v', '', '', ''],
    ]);
    const r = wordsImport.parse([s]);

    expect(r.issues).toEqual([]);
    expect(r.items).toHaveLength(2);
    expect(r.items[0].pos).toBe('n');
    expect(r.items[0].examples).toHaveLength(2);
    expect(r.items[0].examples[1]).toEqual({ zh: '他吃鸡蛋。', pinyin: 'Tā chī jīdàn.', vi: 'Anh ấy ăn trứng.', highlight: null });
    expect(r.items[1].pos).toBe('v');
  });

  it('báo dòng thiếu chữ Hán và dòng trùng, vẫn nhận các dòng còn lại', () => {
    const s = sheet('Từ vựng', [
      ['Chữ Hán', 'Nghĩa tiếng Việt'],
      ['', 'trứng gà'],
      ['牛奶', 'sữa bò'],
      ['牛奶', 'sữa'],
    ]);
    const r = wordsImport.parse([s]);

    expect(r.items).toHaveLength(1);
    expect(r.issues).toHaveLength(2);
    expect(r.issues[0].row).toBe(2);
    expect(r.issues[1].message).toContain('Trùng');
  });

  it('thiếu cột Chữ Hán thì nói rõ thay vì trả bảng rỗng', () => {
    const r = wordsImport.parse([sheet('Từ vựng', [['Pinyin', 'Nghĩa'], ['jīdàn', 'trứng gà']])]);
    expect(r.items).toEqual([]);
    expect(r.issues[0].message).toContain('Chữ Hán');
  });
});

describe('nhập câu hỏi', () => {
  const header = ['Dạng câu', 'Nội dung', 'Phụ', 'A', 'B', 'C', 'D', 'Đáp án', 'Ô chữ', 'Câu mẫu', 'Giải thích', 'Điểm'];

  it('dựng đúng payload cho các dạng trắc nghiệm', () => {
    const s = sheet('Bài tập', [
      header,
      ['Chọn nghĩa', '鸡蛋', 'Từ này nghĩa là gì?', 'trứng gà', 'sữa bò', '', '', 'A', '', '', 'Nhớ nhé', '2'],
      ['Điền chỗ trống', '我喝一杯___。', 'Tôi uống một cốc ___.', '牛奶', '鸡蛋', '', '', 'A', '', '', '', ''],
    ]);
    const r = questionsImport('bt').parse([s]);

    expect(r.issues).toEqual([]);
    expect(r.items[0]).toEqual({
      type: 'meaning',
      payload: { word: '鸡蛋', ask: 'Từ này nghĩa là gì?', options: ['trứng gà', 'sữa bò'], answer: 0 },
      explain: 'Nhớ nhé',
      score: 2,
    });
    expect(r.items[1].payload).toEqual({
      sentence: '我喝一杯___。',
      vi: 'Tôi uống một cốc ___.',
      showMeaning: true,
      options: ['牛奶', '鸡蛋'],
      answer: 0,
    });
    expect(r.items[1].score).toBe(1);
  });

  it('sắp xếp câu: ô chữ phải ghép đúng câu (so như API, bỏ qua dấu câu)', () => {
    const ok = questionsImport('bt').parse([
      sheet('Bài tập', [header, ['Sắp xếp câu', '我喜欢吃鸡蛋。', 'Tôi thích ăn trứng.', '', '', '', '', '', '我|喜欢|吃|鸡蛋', '', '', '']]),
    ]);
    expect(ok.issues).toEqual([]);
    expect(ok.items[0].payload.tiles).toEqual(['我', '喜欢', '吃', '鸡蛋']);

    const bad = questionsImport('bt').parse([
      sheet('Bài tập', [header, ['Sắp xếp câu', '我喜欢吃鸡蛋', '', '', '', '', '', '', '我|喜欢|喝|牛奶', '', '', '']]),
    ]);
    expect(bad.items).toEqual([]);
    expect(bad.issues[0].message).toContain('không khớp');
  });

  it('nối từ đọc cặp từ=nghĩa và bắt lỗi trùng', () => {
    const ok = questionsImport('bt').parse([
      sheet('Bài tập', [header, ['Nối từ', '', '', '', '', '', '', '', '鸡蛋=trứng gà|牛奶=sữa bò', '', '', '']]),
    ]);
    expect(ok.items[0].payload.pairs).toEqual([
      ['鸡蛋', 'trứng gà'],
      ['牛奶', 'sữa bò'],
    ]);

    const dup = questionsImport('bt').parse([
      sheet('Bài tập', [header, ['Nối từ', '', '', '', '', '', '', '', '鸡蛋=trứng|鸡蛋=sữa', '', '', '']]),
    ]);
    expect(dup.issues[0].message).toContain('cùng một từ');
  });

  it('Đúng/Sai và Dịch', () => {
    const r = questionsImport('bt').parse([
      sheet('Bài tập', [
        header,
        ['Đúng / Sai', '小明吃鸡蛋。', 'Tiểu Minh ăn trứng.', '', '', '', '', 'Đúng', '', '', '', ''],
        ['Dịch sang tiếng Việt', '我喝牛奶。', 'Wǒ hē niúnǎi.', '', '', '', '', 'uống|sữa', '', 'Tôi uống sữa.', '', ''],
      ]),
    ]);
    expect(r.issues).toEqual([]);
    expect(r.items[0].payload).toEqual({ passage: '小明吃鸡蛋。', statement: 'Tiểu Minh ăn trứng.', answer: true });
    expect(r.items[1].payload).toEqual({ zh: '我喝牛奶。', pinyin: 'Wǒ hē niúnǎi.', sample: 'Tôi uống sữa.', keywords: ['uống', 'sữa'] });
  });

  it('chặn dạng không thuộc phần đang soạn', () => {
    const r = questionsImport('bt').parse([
      sheet('Bài tập', [header, ['Thanh điệu', '妈', '', 'Thanh 1', 'Thanh 2', '', '', 'A', '', '', '', '']]),
    ]);
    expect(r.items).toEqual([]);
    expect(r.issues[0].message).toContain('bài tập');

    const kt = questionsImport('kt').parse([
      sheet('Kiểm tra', [header, ['Thanh điệu', '妈', '', 'Thanh 1', 'Thanh 2', '', '', 'A', '', '', '', '']]),
    ]);
    expect(kt.items).toHaveLength(1);
  });

  it('“Chọn từ điền câu” của màn Kiểm tra vẫn là dạng blank của API', () => {
    const r = questionsImport('kt').parse([
      sheet('Kiểm tra', [header, ['Chọn từ điền câu', '我喝___。', '', '牛奶', '鸡蛋', '', '', 'B', '', '', '', '']]),
    ]);
    expect(r.items[0].type).toBe('blank');
    expect(r.items[0].payload.answer).toBe(1);
  });

  it('bắt lỗi thiếu phương án, sai đáp án, thiếu chỗ trống', () => {
    const r = questionsImport('bt').parse([
      sheet('Bài tập', [
        header,
        ['Chọn nghĩa', '鸡蛋', '', 'trứng gà', '', '', '', 'A', '', '', '', ''],
        ['Chọn nghĩa', '牛奶', '', 'sữa', 'trứng', '', '', 'C', '', '', '', ''],
        ['Điền chỗ trống', '我喝一杯牛奶。', '', 'a', 'b', '', '', 'A', '', '', '', ''],
      ]),
    ]);
    expect(r.items).toEqual([]);
    expect(r.issues.map((i) => i.message)).toEqual([
      expect.stringContaining('2 phương án'),
      expect.stringContaining('không có phương án'),
      expect.stringContaining('một chỗ trống'),
    ]);
  });
});

describe('nhập luyện viết', () => {
  it('nhận chữ đơn, bỏ chuỗi nhiều chữ và chữ không phải Hán', () => {
    const r = writingImport.parse([
      sheet('Luyện viết', [
        ['Chữ Hán', 'Pinyin', 'Hán Việt', 'Nghĩa', 'Cấu tạo', 'Hiện'],
        ['鸡', 'jī', 'KÊ', 'con gà', 'Trái phải: 又 + 鸟', 'Có'],
        ['鸡蛋', '', '', '', '', ''],
        ['abc', '', '', '', '', ''],
        ['蛋', 'dàn', '', '', '', 'Không'],
      ]),
    ]);
    expect(r.items).toHaveLength(2);
    expect(r.items[0]).toEqual({ hanzi: '鸡', pinyin: 'jī', hanViet: 'KÊ', meaning: 'con gà', structure: 'Trái phải: 又 + 鸟', visible: true });
    expect(r.items[1].visible).toBe(false);
    expect(r.issues).toHaveLength(2);
  });
});

describe('nhập hội thoại', () => {
  it('gom nhân vật theo tên, giữ thứ tự câu, đọc cả sheet câu hỏi', () => {
    const lines = sheet('Lời thoại', [
      ['Bài khoá', 'Nhân vật', 'Vai', 'Tiếng Trung', 'Pinyin', 'Nghĩa tiếng Việt', 'Từ nhấn'],
      ['1', 'Tiểu Minh', 'Khách', '你好！我要一杯牛奶。', 'Nǐ hǎo!', 'Xin chào!', '牛奶'],
      ['1', 'Nhân viên', 'Nhân viên quán', '好的。', 'Hǎo de.', 'Được ạ.', ''],
      ['1', 'Tiểu Minh', '', '谢谢。', 'Xièxie.', 'Cảm ơn.', '面包'],
      ['2', 'Tiểu Lan', 'Bạn', '你去哪儿？', 'Nǐ qù nǎr?', 'Bạn đi đâu?', ''],
    ]);
    const bk = sheet('Bài khoá', [
      ['Bài khoá', 'Tên bài khoá', 'Tình huống'],
      ['1', 'Ở quán nước', 'Tiểu Minh gọi sữa.'],
      ['2', 'Trên đường', ''],
    ]);
    const r = dialogueImport.parse([lines, bk]);

    // Hai bài khoá tách theo cột “Bài khoá”, mỗi bài khoá có bộ nhân vật riêng.
    expect(r.items).toHaveLength(2);
    const d = r.items[0];
    expect(d.title).toBe('Ở quán nước');
    expect(d.scene).toBe('Tiểu Minh gọi sữa.');
    expect(d.speakers.map((s) => s.name)).toEqual(['Tiểu Minh', 'Nhân viên']);
    expect(d.speakers[0].roleVi).toBe('Khách');
    expect(d.lines).toHaveLength(3);
    expect(d.lines[2].speakerKey).toBe(d.speakers[0].key);
    expect(d.lines[0].highlight).toBe('牛奶');
    // Từ nhấn không có trong câu thì bỏ, kèm lời nhắc.
    expect(d.lines[2].highlight).toBe(null);
    expect(r.issues[0].message).toContain('面包');
    expect(r.items[1].title).toBe('Trên đường');
    expect(r.items[1].lines).toHaveLength(1);
    expect(r.summary).toContain('2 bài khoá');
  });
});

describe('nhập ngữ pháp', () => {
  it('nối ví dụ về đúng cấu trúc và tách ô chữ cho bài luyện tập', () => {
    const points = sheet('Ngữ pháp', [
      ['Tên cấu trúc', 'Mô tả ngắn', 'Giải thích', 'Lưu ý', 'Khối công thức', 'Câu luyện tập', 'Nghĩa luyện tập'],
      ['喜欢 + danh từ', 'Sở thích', 'Dùng để nói thích gì.', 'Phủ định thêm 不', 'Chủ ngữ=我|喜欢=喜欢', '我喜欢鸡蛋', 'Tôi thích trứng gà.'],
      ['每天 + động từ', '', 'Việc lặp hằng ngày.', '', 'Chủ ngữ=我', '', ''],
    ]);
    const examples = sheet('Ví dụ', [
      ['Tên cấu trúc', 'Loại', 'Công thức', 'Tiếng Trung', 'Pinyin', 'Nghĩa', 'Từ nhấn'],
      ['喜欢 + danh từ', 'Khẳng định', 'CN + 喜欢 + TN', '我喜欢鸡蛋。', 'Wǒ xǐhuan jīdàn.', 'Tôi thích trứng gà.', '喜欢'],
      ['每天 + động từ', 'Khẳng định', '', '我每天喝牛奶。', '', 'Mỗi ngày tôi uống sữa.', '每天'],
      ['Không có cấu trúc này', '', '', '我们走。', '', '', ''],
    ]);
    const r = grammarImport.parse([points, examples]);

    expect(r.items).toHaveLength(2);
    expect(r.items[0].blocks).toEqual([
      { label: 'Chủ ngữ', hz: '我', color: '#DCEBFF' },
      { label: '喜欢', hz: '喜欢', color: '#CFF5E1' },
    ]);
    expect(r.items[0].practice).toEqual({ answer: '我喜欢鸡蛋', vi: 'Tôi thích trứng gà.', tiles: ['我', '喜', '欢', '鸡', '蛋'] });
    expect(r.items[1].practice).toBe(null);
    expect(r.items[0].examples).toHaveLength(1);
    expect(r.items[1].examples[0].highlight).toBe('每天');
    expect(r.issues[0].message).toContain('Không có cấu trúc nào');
    expect(r.summary).toBe('2 cấu trúc · 2 ví dụ');
  });

  it('phân biệt hai cấu trúc chỉ khác nhau ở phần chữ Hán', () => {
    // Giáo trình đặt tên kiểu “想 + động từ”, “怎么 + động từ” — chỉ khác nhau ở chữ Hán.
    const points = sheet('Ngữ pháp', [
      ['Tên cấu trúc', 'Mô tả ngắn', 'Giải thích', 'Lưu ý', 'Khối công thức', 'Câu luyện tập', 'Nghĩa luyện tập'],
      ['想 + động từ', 'Muốn làm gì', 'Đặt trước động từ, nói mong muốn.', '', '', '', ''],
      ['怎么 + động từ', 'Hỏi cách thức', 'Đặt trước động từ, hỏi làm thế nào.', '', '', '', ''],
    ]);
    const examples = sheet('Ví dụ', [
      ['Tên cấu trúc', 'Loại', 'Công thức', 'Tiếng Trung', 'Pinyin', 'Nghĩa', 'Từ nhấn'],
      ['想 + động từ', 'Khẳng định', '', '我想去超市。', '', 'Tôi muốn đi siêu thị.', '想'],
      ['怎么 + động từ', 'Câu hỏi', '', '你怎么去超市？', '', 'Bạn đi siêu thị bằng cách nào?', '怎么'],
    ]);
    const r = grammarImport.parse([points, examples]);

    expect(r.issues).toEqual([]);
    expect(r.items).toHaveLength(2);
    expect(r.items[0].examples).toHaveLength(1);
    expect(r.items[1].examples[0].zh).toBe('你怎么去超市？');
  });
});
