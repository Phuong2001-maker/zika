// Nhập Luyện viết từ Excel (08 §10). Mỗi dòng một chữ Hán.
//
// Chữ chưa có trong kho sẽ được API tạo mới và tự tải dữ liệu nét; các cột Pinyin / Hán Việt /
// Nghĩa / Cấu tạo chỉ dùng để bổ sung khi kho còn trống chỗ đó.

import type { Sheet } from '$lib/admin/xlsx';
import {
  cellOf,
  headerMap,
  isBlank,
  Issues,
  parseBool,
  pickSheet,
  previewOf,
  type ImportSpec,
  type ParseResult,
  type SheetSpec,
} from './core';

export interface CharRow {
  hanzi: string;
  pinyin: string;
  hanViet: string;
  meaning: string;
  structure: string;
  visible: boolean;
}

/** Chữ Hán nằm trong các dải CJK — giống Pinyin::isHanzi ở API. */
const HANZI = /^[㐀-䶿一-鿿豈-﫿]$/u;

const SHEET: SheetSpec = {
  name: 'Luyện viết',
  about: 'Mỗi dòng một chữ. Chỉ cột “Chữ Hán” là bắt buộc — phần còn lại tự lấy từ kho chữ nếu bỏ trống.',
  columns: [
    { key: 'hanzi', header: 'Chữ Hán', alias: ['chữ', 'hanzi'], hint: 'Đúng một chữ, ví dụ 鸡', required: true },
    { key: 'pinyin', header: 'Pinyin', alias: ['phiên âm'], hint: 'jī' },
    { key: 'hanViet', header: 'Hán Việt', alias: ['hanviet'], hint: 'KÊ' },
    { key: 'meaning', header: 'Nghĩa', alias: ['nghĩa tiếng việt'], hint: 'con gà' },
    { key: 'structure', header: 'Cấu tạo', alias: ['cấu trúc'], hint: 'Trái phải: 又 + 鸟' },
    { key: 'visible', header: 'Hiện', alias: ['hiển thị'], hint: 'Để trống hoặc “Có” là hiện cho người học' },
  ],
  sample: [
    ['鸡', 'jī', 'KÊ', 'con gà', 'Trái phải: 又 + 鸟', 'Có'],
    ['蛋', 'dàn', 'ĐẢN', 'quả trứng', 'Trên dưới: 疋 + 虫', 'Có'],
    ['牛', 'niú', 'NGƯU', 'con bò', '', 'Có'],
    ['奶', 'nǎi', 'NÃI', 'sữa', 'Trái phải: 女 + 乃', 'Có'],
  ],
};

export const writingImport: ImportSpec<CharRow> = {
  title: 'Nhập chữ luyện viết từ Excel',
  fileName: 'zika-mau-luyen-viet.xlsx',
  intro: 'Mỗi dòng một chữ Hán. Chữ mới sẽ được tự tải dữ liệu nét viết, có thể mất vài giây.',
  sheets: [SHEET],

  parse(sheets: Sheet[]): ParseResult<CharRow> {
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

    const items: CharRow[] = [];
    const seen = new Set<string>();

    sheet.rows.slice(at + 1).forEach((row, i) => {
      const line = at + 1 + i;
      if (isBlank(row)) return;

      const hanzi = cellOf(row, index, 'hanzi');
      if (hanzi === '') {
        issues.add(line, 'Thiếu chữ Hán.');
        return;
      }
      if (!HANZI.test(hanzi)) {
        issues.add(line, `“${hanzi}” không phải một chữ Hán đơn.`);
        return;
      }
      if (seen.has(hanzi)) {
        issues.add(line, `Trùng với dòng trên: ${hanzi}`);
        return;
      }
      seen.add(hanzi);

      items.push({
        hanzi,
        pinyin: cellOf(row, index, 'pinyin'),
        hanViet: cellOf(row, index, 'hanViet'),
        meaning: cellOf(row, index, 'meaning'),
        structure: cellOf(row, index, 'structure'),
        visible: parseBool(cellOf(row, index, 'visible'), true) ?? true,
      });
    });

    return { items, issues: issues.list, preview: previewOf(sheet, at) };
  },
};
