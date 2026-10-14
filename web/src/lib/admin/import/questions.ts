// Nhập câu hỏi Bài tập / Kiểm tra từ Excel (08 §8, §9).
//
// 10 dạng câu có payload khác nhau, nếu mỗi dạng một sheet thì file mẫu rối. Cách làm ở đây:
// một bảng duy nhất với các cột đặt theo *vai trò* (Nội dung · Phụ · A–D · Đáp án · Ô chữ),
// mỗi dạng chỉ điền những cột nó cần. Bảng hướng dẫn trong hộp thoại nói rõ dạng nào dùng cột nào.

import type { Sheet } from '$lib/admin/xlsx';
import { normZh, type Payload } from '$lib/utils/grade';
import {
  answerIndex,
  cellOf,
  headerMap,
  isBlank,
  Issues,
  norm,
  parseBool,
  parseNumber,
  pickSheet,
  previewOf,
  splitList,
  type ImportSpec,
  type ParseResult,
  type SheetSpec,
} from './core';

export interface QuestionRow {
  type: string;
  payload: Payload;
  explain: string;
  score: number;
}

/** Dạng câu dùng được ở mỗi phần — giống QuestionGrader::BT_TYPES / KT_TYPES ở API. */
const BT_TYPES = ['meaning', 'blank', 'audio', 'pinyin', 'order', 'trans', 'tf', 'match'];
const KT_TYPES = ['blank', 'listen', 'meaning', 'pick', 'order', 'trans', 'tf', 'match', 'tone', 'toZh'];

/** Tên gọi chấp nhận cho mỗi dạng (gõ mã hay gõ tiếng Việt đều được). */
const TYPE_NAMES: Record<string, string[]> = {
  meaning: ['meaning', 'chọn nghĩa', 'nghĩa'],
  pinyin: ['pinyin', 'chọn pinyin'],
  tone: ['tone', 'thanh điệu'],
  audio: ['audio', 'nghe chọn chữ', 'nghe chọn chữ hán'],
  listen: ['listen', 'nghe và chọn', 'nghe'],
  blank: ['blank', 'fill', 'điền chỗ trống', 'chọn từ điền câu', 'điền vào chỗ trống'],
  pick: ['pick', 'chọn câu đúng', 'chọn câu viết đúng'],
  toZh: ['tozh', 'chọn câu tiếng trung'],
  order: ['order', 'sắp xếp câu', 'sắp xếp'],
  trans: ['trans', 'dịch sang tiếng việt', 'dịch'],
  tf: ['tf', 'đúng / sai', 'đúng sai', 'đúng hay sai'],
  match: ['match', 'nối từ', 'nối'],
};

const CHOICE = ['meaning', 'pinyin', 'tone', 'audio', 'listen', 'blank', 'pick', 'toZh'];

function typeCode(s: string): string {
  const n = norm(s);
  if (n === '') return '';
  for (const [code, names] of Object.entries(TYPE_NAMES)) {
    if (names.some((x) => norm(x) === n)) return code;
  }
  return '';
}

const COLUMNS: SheetSpec['columns'] = [
  {
    key: 'type',
    header: 'Dạng câu',
    alias: ['dạng', 'loại câu', 'type'],
    hint: 'Chọn nghĩa · Điền chỗ trống · Sắp xếp câu · Dịch · Đúng / Sai · Nối từ…',
    required: true,
  },
  {
    key: 'main',
    header: 'Nội dung',
    alias: ['nội dung chính', 'đề bài', 'câu hỏi'],
    hint: 'Trắc nghiệm: từ hoặc câu hỏi · Điền chỗ trống: câu có ___ · Sắp xếp: câu đúng · Đúng/Sai: đoạn văn',
    required: true,
  },
  {
    key: 'sub',
    header: 'Phụ',
    alias: ['phụ đề', 'nghĩa', 'nghĩa tiếng việt'],
    hint: 'Chọn nghĩa: câu hỏi · Điền chỗ trống & Sắp xếp: nghĩa tiếng Việt · Dịch: pinyin · Đúng/Sai: câu cần xét',
  },
  { key: 'a', header: 'A', hint: 'Phương án A' },
  { key: 'b', header: 'B', hint: 'Phương án B' },
  { key: 'c', header: 'C', hint: 'Phương án C' },
  { key: 'd', header: 'D', hint: 'Phương án D' },
  {
    key: 'answer',
    header: 'Đáp án',
    alias: ['đáp án đúng', 'dapan'],
    hint: 'Trắc nghiệm: A/B/C/D · Đúng/Sai: Đúng hoặc Sai · Dịch: các ý bắt buộc, ngăn bằng |',
  },
  {
    key: 'tiles',
    header: 'Ô chữ',
    alias: ['ô chữ / cặp nối', 'tiles', 'cặp nối'],
    hint: 'Sắp xếp câu: 我|喜欢|鸡蛋 · Nối từ: 鸡蛋=trứng gà|牛奶=sữa bò',
  },
  { key: 'sample', header: 'Câu mẫu', alias: ['mẫu', 'bản dịch mẫu'], hint: 'Chỉ dạng Dịch: bản dịch tham khảo' },
  { key: 'explain', header: 'Giải thích', alias: ['giải thích đáp án'], hint: 'Hiện sau khi người học trả lời' },
  { key: 'score', header: 'Điểm', alias: ['điểm số'], hint: 'Để trống thì tính 1 điểm' },
];

const SAMPLE_BT: string[][] = [
  ['Chọn nghĩa', '鸡蛋', 'Từ này nghĩa là gì?', 'trứng gà', 'sữa bò', 'bánh mì', 'cơm', 'A', '', '', '', '1'],
  ['Điền chỗ trống', '我每天喝一杯___。', 'Mỗi ngày tôi uống một cốc ___.', '牛奶', '鸡蛋', '米饭', '面包', 'A', '', '', '', '1'],
  ['Sắp xếp câu', '我喜欢吃鸡蛋', 'Tôi thích ăn trứng gà.', '', '', '', '', '', '我|喜欢|吃|鸡蛋', '', '', '1'],
  ['Dịch sang tiếng Việt', '我每天喝牛奶。', 'Wǒ měi tiān hē niúnǎi.', '', '', '', '', 'uống|sữa', '', 'Mỗi ngày tôi uống sữa.', '', '1'],
  ['Đúng / Sai', '小明每天早上吃两个鸡蛋。', 'Tiểu Minh ăn trứng vào buổi sáng.', '', '', '', '', 'Đúng', '', '', '', '1'],
  ['Nối từ', '', '', '', '', '', '', '', '鸡蛋=trứng gà|牛奶=sữa bò|面包=bánh mì', '', '', '1'],
];

const SAMPLE_KT: string[][] = [
  ['Chọn từ điền câu', '我每天喝一杯___。', 'Mỗi ngày tôi uống một cốc ___.', '牛奶', '鸡蛋', '米饭', '面包', 'A', '', '', '', '1'],
  ['Nghe và chọn', '我喜欢吃鸡蛋。', '', 'Tôi thích ăn trứng gà.', 'Tôi thích uống sữa.', 'Tôi thích ăn cơm.', 'Tôi thích bánh mì.', 'A', '', '', '', '1'],
  ['Chọn câu đúng', 'Câu nào viết đúng?', '', '我喜欢吃鸡蛋。', '我鸡蛋吃喜欢。', '吃我鸡蛋喜欢。', '喜欢我吃鸡蛋。', 'A', '', '', '', '1'],
  ['Thanh điệu', '妈', 'Chữ này mang thanh mấy?', 'Thanh 1', 'Thanh 2', 'Thanh 3', 'Thanh 4', 'A', '', '', '', '1'],
  ['Chọn câu tiếng Trung', 'Tôi thích ăn trứng gà.', '', '我喜欢吃鸡蛋。', '我喜欢喝牛奶。', '我喜欢吃米饭。', '我喜欢吃面包。', 'A', '', '', '', '1'],
];

/* ===================================================================== */
/* Dựng payload cho từng dạng                                             */
/* ===================================================================== */

interface Fields {
  main: string;
  sub: string;
  options: string[];
  answer: string;
  tiles: string;
  sample: string;
}

/** Trả về payload, hoặc chuỗi lỗi để ghi vào danh sách “dòng không nhập được”. */
function buildPayload(type: string, f: Fields): Payload | string {
  if (CHOICE.includes(type)) {
    const options = f.options.filter((o) => o !== '');
    if (options.length < 2) return 'Cần ít nhất 2 phương án ở cột A–D.';
    const answer = answerIndex(f.answer);
    if (answer < 0) return 'Cột Đáp án phải là A, B, C hoặc D.';
    if (answer >= options.length) return `Đáp án ${f.answer.toUpperCase()} không có phương án tương ứng.`;
    if (new Set(options).size !== options.length) return 'Có hai phương án trùng nhau.';

    switch (type) {
      case 'meaning':
      case 'pinyin':
      case 'tone':
        if (f.main === '') return 'Thiếu chữ Hán ở cột Nội dung.';
        if (type === 'tone' && [...f.main].length !== 1) return 'Dạng Thanh điệu chỉ dùng cho một chữ.';
        return { word: f.main, ask: f.sub, options, answer };

      case 'audio':
      case 'listen':
        if (f.main === '') return 'Thiếu câu cần đọc ở cột Nội dung.';
        return { audioText: f.main, audioUrl: null, options, answer };

      case 'blank':
        if ((f.main.match(/___/g) ?? []).length !== 1) return 'Cột Nội dung phải có đúng một chỗ trống ___.';
        return { sentence: f.main, vi: f.sub, showMeaning: f.sub !== '', options, answer };

      case 'pick':
        if (f.main === '') return 'Thiếu câu hỏi ở cột Nội dung.';
        return { ask: f.main, options, answer };

      default: // toZh
        if (f.main === '') return 'Thiếu câu tiếng Việt ở cột Nội dung.';
        return { vi: f.main, options, answer };
    }
  }

  if (type === 'order') {
    if (f.main === '') return 'Thiếu câu đúng ở cột Nội dung.';
    const tiles = splitList(f.tiles);
    if (tiles.length < 2 || tiles.length > 8) return 'Cột Ô chữ cần 2 đến 8 ô, ngăn bằng dấu |.';
    // So đúng cách API so (QuestionPayload::order) để khỏi qua được ở đây rồi bị chặn lúc lưu.
    if (normZh(tiles.join('')) !== normZh(f.main)) return 'Các ô chữ ghép lại không khớp câu ở cột Nội dung.';
    return { vi: f.sub, answer: f.main, tiles };
  }

  if (type === 'trans') {
    if (f.main === '') return 'Thiếu câu tiếng Trung ở cột Nội dung.';
    const keywords = splitList(f.answer);
    if (keywords.length === 0) return 'Cột Đáp án cần ít nhất một ý bắt buộc để chấm.';
    return { zh: f.main, pinyin: f.sub, sample: f.sample, keywords };
  }

  if (type === 'tf') {
    if (f.main === '') return 'Thiếu đoạn văn ở cột Nội dung.';
    if (f.sub === '') return 'Thiếu câu cần xét đúng/sai ở cột Phụ.';
    const answer = parseBool(f.answer);
    if (answer === null) return 'Cột Đáp án phải ghi Đúng hoặc Sai.';
    return { passage: f.main, statement: f.sub, answer };
  }

  // match
  const pairs: [string, string][] = [];
  for (const part of splitList(f.tiles)) {
    const at = part.indexOf('=');
    if (at <= 0) return `Cặp nối “${part}” phải viết dạng từ=nghĩa.`;
    pairs.push([part.slice(0, at).trim(), part.slice(at + 1).trim()]);
  }
  if (pairs.length < 2 || pairs.length > 6) return 'Cột Ô chữ cần 2 đến 6 cặp từ=nghĩa, ngăn bằng dấu |.';
  if (new Set(pairs.map((p) => p[0])).size !== pairs.length) return 'Có hai cặp dùng cùng một từ.';
  if (new Set(pairs.map((p) => p[1])).size !== pairs.length) return 'Có hai cặp dùng cùng một nghĩa.';
  return { pairs };
}

/* ===================================================================== */

export function questionsImport(part: 'bt' | 'kt'): ImportSpec<QuestionRow> {
  const allowed = part === 'bt' ? BT_TYPES : KT_TYPES;
  const label = part === 'bt' ? 'bài tập' : 'kiểm tra';

  const sheet: SheetSpec = {
    name: part === 'bt' ? 'Bài tập' : 'Kiểm tra',
    about: `Mỗi dòng một câu. Cột nào không dùng cho dạng đó thì để trống.`,
    columns: COLUMNS,
    sample: part === 'bt' ? SAMPLE_BT : SAMPLE_KT,
  };

  return {
    title: `Nhập câu ${label} từ Excel`,
    fileName: `zika-mau-${part === 'bt' ? 'bai-tap' : 'kiem-tra'}.xlsx`,
    intro: `Mỗi dòng một câu ${label}. Tải file mẫu để xem cách điền từng dạng câu.`,
    sheets: [sheet],

    parse(sheets: Sheet[]): ParseResult<QuestionRow> {
      const found = pickSheet(sheets, sheet, 0);
      const issues = new Issues(sheet.name);
      if (!found) {
        issues.add(-1, 'Không tìm thấy bảng nào trong file.');
        return { items: [], issues: issues.list, preview: { cols: [], rows: [] } };
      }

      const { at, index } = headerMap(found.rows, COLUMNS);
      if (at < 0 || index.type === undefined || index.main === undefined) {
        issues.add(-1, 'Không thấy cột “Dạng câu” và “Nội dung”. Hãy tải file mẫu và giữ nguyên dòng tiêu đề.');
        return { items: [], issues: issues.list, preview: previewOf(found, at) };
      }

      const items: QuestionRow[] = [];

      found.rows.slice(at + 1).forEach((row, i) => {
        const line = at + 1 + i;
        if (isBlank(row)) return;

        const rawType = cellOf(row, index, 'type');
        const type = typeCode(rawType);
        if (type === '') {
          issues.add(line, `Không hiểu dạng câu “${rawType}”.`);
          return;
        }
        if (!allowed.includes(type)) {
          issues.add(line, `Phần ${label} không dùng dạng “${rawType}”.`);
          return;
        }

        const payload = buildPayload(type, {
          main: cellOf(row, index, 'main'),
          sub: cellOf(row, index, 'sub'),
          options: ['a', 'b', 'c', 'd'].map((k) => cellOf(row, index, k)),
          answer: cellOf(row, index, 'answer'),
          tiles: cellOf(row, index, 'tiles'),
          sample: cellOf(row, index, 'sample'),
        });

        if (typeof payload === 'string') {
          issues.add(line, payload);
          return;
        }

        const score = parseNumber(cellOf(row, index, 'score'), 1);
        items.push({
          type,
          payload,
          explain: cellOf(row, index, 'explain'),
          score: score > 0 ? score : 1,
        });
      });

      return { items, issues: issues.list, preview: previewOf(found, at) };
    },
  };
}
