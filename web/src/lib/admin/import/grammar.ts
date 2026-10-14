// Nhập Cấu trúc ngữ pháp từ Excel (08 §12).
//
// Hai sheet: “Ngữ pháp” (mỗi dòng một cấu trúc) và “Ví dụ” (mỗi dòng một ví dụ, nối về cấu trúc
// qua cột Tên cấu trúc). Tách làm hai vì một cấu trúc thường có 3–4 ví dụ, nhét hết vào một dòng
// thì không đọc nổi.

import type { Sheet } from '$lib/admin/xlsx';
import type { GrammarBlock, GrammarExample, GrammarPractice } from '$lib/admin/api';
import { normZh } from '$lib/utils/grade';
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

export interface GrammarRow {
  title: string;
  subtitle: string;
  explain: string;
  note: string;
  blocks: GrammarBlock[];
  examples: GrammarExample[];
  practice: GrammarPractice | null;
}

const COLORS = ['#DCEBFF', '#CFF5E1', '#FFB3AA', '#FFF0B3', '#E8E0FF'];

const POINTS: SheetSpec = {
  name: 'Ngữ pháp',
  about: 'Mỗi dòng một cấu trúc ngữ pháp.',
  columns: [
    { key: 'title', header: 'Tên cấu trúc', alias: ['tên', 'cấu trúc'], hint: 'Ví dụ: 喜欢 + danh từ', required: true },
    { key: 'subtitle', header: 'Mô tả ngắn', alias: ['mô tả'], hint: 'Nói về sở thích' },
    { key: 'explain', header: 'Giải thích', alias: ['nội dung'], hint: 'Bắt buộc. Giải thích cách dùng', required: true },
    { key: 'note', header: 'Lưu ý', alias: ['ghi chú'], hint: 'Mẹo nhớ, lỗi hay gặp' },
    {
      key: 'blocks',
      header: 'Khối công thức',
      alias: ['công thức', 'khối'],
      hint: 'Chủ ngữ=我|喜欢=喜欢|Tân ngữ=鸡蛋 — phần trước “=” là nhãn, sau là chữ Hán',
    },
    { key: 'practice', header: 'Câu luyện tập', alias: ['luyện tập'], hint: 'Câu đúng để người học xếp lại' },
    { key: 'practiceVi', header: 'Nghĩa luyện tập', alias: ['nghĩa câu luyện tập'], hint: 'Nghĩa tiếng Việt của câu trên' },
  ],
  sample: [
    [
      '喜欢 + danh từ / động từ',
      'Nói về sở thích',
      'Dùng 喜欢 (xǐhuan) để nói mình thích cái gì hoặc thích làm gì. Sau 喜欢 có thể là danh từ hoặc một cụm động từ.',
      'Phủ định thì thêm 不 trước 喜欢: 我不喜欢…',
      'Chủ ngữ=我|喜欢=喜欢|Tân ngữ=鸡蛋',
      '我喜欢吃鸡蛋',
      'Tôi thích ăn trứng gà.',
    ],
    [
      '每天 + động từ',
      'Nói việc làm hằng ngày',
      '每天 (měi tiān) đặt trước động từ để nói việc lặp lại mỗi ngày.',
      '',
      'Chủ ngữ=我|每天=每天|Động từ=喝牛奶',
      '我每天喝牛奶',
      'Mỗi ngày tôi uống sữa.',
    ],
  ],
};

const EXAMPLES: SheetSpec = {
  name: 'Ví dụ',
  about: 'Mỗi dòng một ví dụ. Cột “Tên cấu trúc” phải trùng với sheet Ngữ pháp.',
  optional: true,
  columns: [
    { key: 'title', header: 'Tên cấu trúc', alias: ['cấu trúc', 'thuộc cấu trúc'], hint: 'Trùng tên ở sheet Ngữ pháp', required: true },
    { key: 'kind', header: 'Loại', alias: ['kiểu'], hint: 'Khẳng định · Phủ định · Câu hỏi' },
    { key: 'formula', header: 'Công thức', hint: 'Chủ ngữ + 喜欢 + tân ngữ' },
    { key: 'zh', header: 'Tiếng Trung', alias: ['câu', 'zh'], hint: 'Câu ví dụ', required: true },
    { key: 'pinyin', header: 'Pinyin', alias: ['phiên âm'] },
    { key: 'vi', header: 'Nghĩa', alias: ['nghĩa tiếng việt', 'dịch'] },
    { key: 'highlight', header: 'Từ nhấn', alias: ['highlight'], hint: 'Phải có trong câu' },
  ],
  sample: [
    ['喜欢 + danh từ / động từ', 'Khẳng định', 'Chủ ngữ + 喜欢 + tân ngữ', '我喜欢鸡蛋。', 'Wǒ xǐhuan jīdàn.', 'Tôi thích trứng gà.', '喜欢'],
    ['喜欢 + danh từ / động từ', 'Phủ định', 'Chủ ngữ + 不喜欢 + tân ngữ', '我不喜欢牛奶。', 'Wǒ bù xǐhuan niúnǎi.', 'Tôi không thích sữa.', '不喜欢'],
    ['喜欢 + danh từ / động từ', 'Câu hỏi', 'Chủ ngữ + 喜欢 + tân ngữ + 吗', '你喜欢鸡蛋吗？', 'Nǐ xǐhuan jīdàn ma?', 'Bạn thích trứng gà không?', '吗'],
    ['每天 + động từ', 'Khẳng định', 'Chủ ngữ + 每天 + động từ', '我每天喝牛奶。', 'Wǒ měi tiān hē niúnǎi.', 'Mỗi ngày tôi uống sữa.', '每天'],
  ],
};

/** "Chủ ngữ=我|Động từ=喜欢" → các khối công thức. Không có “=” thì coi cả ô là nhãn. */
function parseBlocks(s: string): GrammarBlock[] {
  return splitList(s)
    .slice(0, 6)
    .map((part, i) => {
      const at = part.indexOf('=');
      const label = at > 0 ? part.slice(0, at).trim() : part.trim();
      const hz = at > 0 ? part.slice(at + 1).trim() : '';
      return { label, hz, color: COLORS[i % COLORS.length] };
    })
    .filter((b) => b.label !== '');
}

export const grammarImport: ImportSpec<GrammarRow> = {
  title: 'Nhập ngữ pháp từ Excel',
  fileName: 'zika-mau-ngu-phap.xlsx',
  intro: 'Sheet “Ngữ pháp” là các cấu trúc, sheet “Ví dụ” là câu minh hoạ nối theo tên cấu trúc.',
  sheets: [POINTS, EXAMPLES],

  parse(sheets: Sheet[]): ParseResult<GrammarRow> {
    const sheet = pickSheet(sheets, POINTS, 0);
    const issues = new Issues(POINTS.name);
    if (!sheet) {
      issues.add(-1, 'Không tìm thấy bảng nào trong file.');
      return { items: [], issues: issues.list, preview: { cols: [], rows: [] } };
    }

    const { at, index } = headerMap(sheet.rows, POINTS.columns);
    if (at < 0 || index.title === undefined || index.explain === undefined) {
      issues.add(-1, 'Không thấy cột “Tên cấu trúc” và “Giải thích”. Hãy tải file mẫu và giữ nguyên dòng tiêu đề.');
      return { items: [], issues: issues.list, preview: previewOf(sheet, at) };
    }

    const items: GrammarRow[] = [];
    const byTitle = new Map<string, GrammarRow>();

    sheet.rows.slice(at + 1).forEach((row, i) => {
      const line = at + 1 + i;
      if (isBlank(row)) return;

      const title = cellOf(row, index, 'title');
      const explain = cellOf(row, index, 'explain');
      if (title === '') {
        issues.add(line, 'Thiếu tên cấu trúc.');
        return;
      }
      if (explain === '') {
        issues.add(line, `“${title}” chưa có phần Giải thích.`);
        return;
      }
      if (byTitle.has(norm(title))) {
        issues.add(line, `Trùng tên với dòng trên: ${title}`);
        return;
      }

      // Ô chữ của bài luyện tập tách sẵn từng chữ; người soạn gộp lại được trên biểu mẫu.
      const answer = cellOf(row, index, 'practice');
      const tiles = answer === '' ? [] : Array.from(normZh(answer));
      if (answer !== '' && tiles.length < 2) {
        issues.add(line, `Câu luyện tập của “${title}” quá ngắn — cần ít nhất 2 ô chữ.`);
      }

      const item: GrammarRow = {
        title,
        subtitle: cellOf(row, index, 'subtitle'),
        explain,
        note: cellOf(row, index, 'note'),
        blocks: parseBlocks(cellOf(row, index, 'blocks')),
        examples: [],
        practice: tiles.length >= 2 ? { answer, vi: cellOf(row, index, 'practiceVi'), tiles } : null,
      };
      items.push(item);
      byTitle.set(norm(title), item);
    });

    // ----- Ví dụ (sheet tuỳ chọn) -----
    const exSheet = sheets.find((s) => s !== sheet && s.rows.length > 0) ?? null;
    if (exSheet && items.length > 0) {
      const exIssues = new Issues(EXAMPLES.name);
      const ex = headerMap(exSheet.rows, EXAMPLES.columns);
      if (ex.at >= 0 && ex.index.zh !== undefined) {
        exSheet.rows.slice(ex.at + 1).forEach((row, i) => {
          const line = ex.at + 1 + i;
          if (isBlank(row)) return;

          const zh = cellOf(row, ex.index, 'zh');
          if (zh === '') return;

          const title = cellOf(row, ex.index, 'title');
          // Chỉ có một cấu trúc thì khỏi bắt ghi tên ở từng dòng ví dụ.
          const owner = title === '' && items.length === 1 ? items[0] : byTitle.get(norm(title));
          if (!owner) {
            exIssues.add(line, `Không có cấu trúc nào tên “${title}”.`);
            return;
          }
          if (owner.examples.length >= 6) {
            exIssues.add(line, `“${owner.title}” đã đủ 6 ví dụ — bỏ qua dòng này.`);
            return;
          }

          const highlight = cellOf(row, ex.index, 'highlight');
          if (highlight !== '' && !zh.includes(highlight)) {
            exIssues.add(line, `Câu không chứa từ nhấn “${highlight}” — bỏ qua từ nhấn.`);
          }

          owner.examples.push({
            kind: cellOf(row, ex.index, 'kind'),
            formula: cellOf(row, ex.index, 'formula'),
            zh,
            highlight: highlight !== '' && zh.includes(highlight) ? highlight : null,
            pinyin: cellOf(row, ex.index, 'pinyin'),
            vi: cellOf(row, ex.index, 'vi'),
          });
        });
      }
      issues.list.push(...exIssues.list);
    }

    const exCount = items.reduce((n, g) => n + g.examples.length, 0);
    return {
      items,
      issues: issues.list,
      preview: previewOf(sheet, at),
      summary: items.length ? `${items.length} cấu trúc · ${exCount} ví dụ` : undefined,
    };
  },
};
