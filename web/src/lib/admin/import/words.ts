// Nhập Từ vựng · Lật thẻ từ Excel (08 §7).
// Một dòng = một từ. Ví dụ câu nằm cùng dòng, nhiều ví dụ ngăn bằng “|” theo đúng thứ tự ở cả 3 cột.

import type { Sheet } from '$lib/admin/xlsx';
import type { WordExample } from '$lib/admin/api';
import {
  cellOf,
  headerMap,
  isBlank,
  Issues,
  norm,
  pickSheet,
  previewOf,
  splitList,
  type ImportSpec,
  type ParseResult,
  type SheetSpec,
} from './core';

export interface WordRow {
  hanzi: string;
  pinyin: string;
  hanViet: string;
  meaning: string;
  pos: string;
  examples: WordExample[];
}

/** Giống ContentController::POS_VI ở API — nhận cả mã lẫn tên tiếng Việt. */
const POS: [string, string][] = [
  ['n', 'danh từ'],
  ['v', 'động từ'],
  ['adj', 'tính từ'],
  ['adv', 'phó từ'],
  ['mw', 'lượng từ'],
  ['phrase', 'cụm từ'],
  ['num', 'số từ'],
  ['pron', 'đại từ'],
  ['prep', 'giới từ'],
  ['conj', 'liên từ'],
  ['part', 'trợ từ'],
  ['other', 'khác'],
];

function posCode(s: string): string {
  const n = norm(s);
  if (n === '') return '';
  const hit = POS.find(([code, vi]) => code === n || norm(vi) === n);
  return hit ? hit[0] : '';
}

const SHEET: SheetSpec = {
  name: 'Từ vựng',
  about: 'Mỗi dòng một từ. Để trống Pinyin / Hán Việt / Nghĩa thì hệ thống tự tra từ điển có sẵn.',
  columns: [
    { key: 'hanzi', header: 'Chữ Hán', alias: ['hanzi', 'từ', 'tu'], hint: 'Bắt buộc. Ví dụ 鸡蛋', required: true },
    { key: 'pinyin', header: 'Pinyin', alias: ['phiên âm'], hint: 'Có dấu thanh: jīdàn' },
    { key: 'hanViet', header: 'Hán Việt', alias: ['hanviet', 'âm Hán Việt'], hint: 'Kê đản' },
    { key: 'meaning', header: 'Nghĩa tiếng Việt', alias: ['nghĩa', 'nghia', 'meaning'], hint: 'trứng gà' },
    { key: 'pos', header: 'Loại từ', alias: ['từ loại', 'pos'], hint: 'danh từ · động từ · tính từ…' },
    { key: 'exZh', header: 'Ví dụ', alias: ['ví dụ tiếng Trung', 'câu ví dụ'], hint: 'Nhiều ví dụ ngăn bằng |' },
    { key: 'exPinyin', header: 'Ví dụ pinyin', alias: ['pinyin ví dụ'], hint: 'Cùng thứ tự với cột Ví dụ' },
    { key: 'exVi', header: 'Ví dụ nghĩa', alias: ['nghĩa ví dụ'], hint: 'Cùng thứ tự với cột Ví dụ' },
  ],
  sample: [
    ['鸡蛋', 'jīdàn', 'Kê đản', 'trứng gà', 'danh từ', '我早上吃了两个鸡蛋。', 'Wǒ zǎoshang chī le liǎng gè jīdàn.', 'Sáng nay tôi ăn hai quả trứng gà.'],
    ['牛奶', 'niúnǎi', 'Ngưu nãi', 'sữa bò', 'danh từ', '我每天喝一杯牛奶。', 'Wǒ měi tiān hē yì bēi niúnǎi.', 'Mỗi ngày tôi uống một cốc sữa.'],
    ['喝', 'hē', 'Hát', 'uống', 'động từ', '', '', ''],
  ],
};

/** Ghép 3 cột ví dụ theo chỉ số; thiếu pinyin hay nghĩa vẫn nhận, chỉ cần có câu tiếng Trung. */
function examplesOf(zh: string, pinyin: string, vi: string): WordExample[] {
  const zhs = splitList(zh);
  const pys = splitList(pinyin);
  const vis = splitList(vi);
  return zhs.slice(0, 3).map((s, i) => ({ zh: s, pinyin: pys[i] ?? '', vi: vis[i] ?? '', highlight: null }));
}

export const wordsImport: ImportSpec<WordRow> = {
  title: 'Nhập từ vựng từ Excel',
  fileName: 'zika-mau-tu-vung.xlsx',
  intro: 'Mỗi dòng một từ. Từ đã có trong cấp sẽ được bỏ qua để không ghi đè bản đang dùng.',
  sheets: [SHEET],

  parse(sheets: Sheet[]): ParseResult<WordRow> {
    const sheet = pickSheet(sheets, SHEET, 0);
    const issues = new Issues(SHEET.name);
    if (!sheet) {
      issues.add(-1, 'Không tìm thấy bảng nào trong file.');
      return { items: [], issues: issues.list, preview: { cols: [], rows: [] } };
    }

    const { at, index } = headerMap(sheet.rows, SHEET.columns);
    if (at < 0 || index.hanzi === undefined) {
      issues.add(-1, 'Không thấy cột “Chữ Hán”. Hãy tải file mẫu và giữ nguyên dòng tiêu đề.');
      return { items: [], issues: issues.list, preview: previewOf(sheet, at) };
    }

    const items: WordRow[] = [];
    const seen = new Set<string>();

    sheet.rows.slice(at + 1).forEach((row, i) => {
      const line = at + 1 + i;
      if (isBlank(row)) return;

      const hanzi = cellOf(row, index, 'hanzi');
      if (hanzi === '') {
        issues.add(line, 'Thiếu chữ Hán.');
        return;
      }
      if (seen.has(hanzi)) {
        issues.add(line, `Trùng với dòng trên: ${hanzi}`);
        return;
      }
      seen.add(hanzi);

      const posRaw = cellOf(row, index, 'pos');
      const pos = posCode(posRaw);
      if (posRaw !== '' && pos === '') {
        issues.add(line, `Không hiểu loại từ “${posRaw}” — sẽ để mặc định là danh từ.`);
      }

      items.push({
        hanzi,
        pinyin: cellOf(row, index, 'pinyin'),
        hanViet: cellOf(row, index, 'hanViet'),
        meaning: cellOf(row, index, 'meaning'),
        pos,
        examples: examplesOf(cellOf(row, index, 'exZh'), cellOf(row, index, 'exPinyin'), cellOf(row, index, 'exVi')),
      });
    });

    return { items, issues: issues.list, preview: previewOf(sheet, at) };
  },
};
