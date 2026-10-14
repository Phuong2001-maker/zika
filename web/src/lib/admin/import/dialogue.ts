// Nhập Hội thoại từ Excel (08 §11).
//
// Một bài có nhiều bài khoá (giáo trình HSK chia 课文一, 课文二…), nên cột “Bài khoá” ở sheet
// Lời thoại quyết định câu nào thuộc cảnh nào. Sheet “Bài khoá” chỉ để đặt tên và tả tình huống
// cho từng số đó; bỏ trống thì bài khoá vẫn nhập được, chỉ là lấy tên mặc định.
//
// Nhân vật không khai báo riêng mà suy ra từ cột Nhân vật — gõ trùng tên là cùng một người, và
// mỗi bài khoá có bộ nhân vật của riêng nó.
//
// Khác 5 phần kia, bộ nhập này không gọi API ngay mà đổ thẳng vào biểu mẫu đang mở để người soạn
// xem lại rồi bấm “Lưu thay đổi” — vì hội thoại lưu cả khối một lần (PUT /admin/lessons/{id}/dialogue).

import type { Sheet } from '$lib/admin/xlsx';
import type { DialogueBlock, DialogueLine, DialogueSpeaker } from '$lib/admin/api';
import {
  cellOf,
  headerMap,
  isBlank,
  Issues,
  parseNumber,
  pickSheet,
  previewOf,
  type ImportSpec,
  type ParseResult,
  type SheetSpec,
} from './core';

/**
 * Chữ trong vòng tròn cạnh bong bóng thoại. Ưu tiên cột “Chữ đại diện”; bỏ trống thì lấy chữ Hán
 * cuối trong tên (kiểu 小王 → 王), còn tên viết bằng chữ Latin thì lấy chữ cái đầu — chứ lấy chữ
 * cuối như trước ra “Lý Văn” → “n”, chẳng nói lên điều gì.
 */
function chuDaiDien(cot: string, name: string): string {
  const chon = [...cot.trim()][0];
  if (chon !== undefined) return chon;
  const chu = [...name.trim()];
  const han = chu.filter((c) => /[一-鿿]/.test(c));
  return han.length > 0 ? (han.at(-1) as string) : (chu[0] ?? '?').toUpperCase();
}

/** Màu nhân vật gán lần lượt, lấy từ bảng màu của bộ thiết kế. */
const COLORS = ['#DCEBFF', '#CFF5E1', '#FFE2DE', '#FFF0B3', '#E8E0FF', '#D8F3F6'];

const LINES: SheetSpec = {
  name: 'Lời thoại',
  about: 'Mỗi dòng một câu thoại, theo đúng thứ tự trong bài. Cột “Bài khoá” tách các cảnh với nhau.',
  columns: [
    { key: 'block', header: 'Bài khoá', alias: ['bai khoa', 'cảnh', 'số bài khoá'], hint: '1, 2, 3… Bỏ trống thì coi là bài khoá 1' },
    { key: 'speaker', header: 'Nhân vật', alias: ['người nói', 'ai nói'], hint: 'Tên hiện trên bong bóng thoại', required: true },
    { key: 'role', header: 'Vai', alias: ['vai trò', 'mô tả'], hint: 'Khách · Nhân viên… (chỉ cần ghi ở dòng đầu của mỗi người)' },
    { key: 'avatar', header: 'Chữ đại diện', alias: ['avatar', 'chữ avatar'], hint: 'Một chữ Hán hiện trong vòng tròn: 李, 白…' },
    { key: 'zh', header: 'Tiếng Trung', alias: ['câu', 'nội dung', 'zh'], hint: 'Câu thoại', required: true },
    { key: 'pinyin', header: 'Pinyin', alias: ['phiên âm'], hint: 'Nǐ hǎo!' },
    { key: 'vi', header: 'Nghĩa tiếng Việt', alias: ['nghĩa', 'dịch'], hint: 'Xin chào!' },
    { key: 'highlight', header: 'Từ nhấn', alias: ['từ khoá', 'highlight'], hint: 'Từ được tô trong câu, phải có trong câu' },
  ],
  sample: [
    ['1', 'Tiểu Minh', 'Khách', '明', '你好！我要一杯牛奶。', 'Nǐ hǎo! Wǒ yào yì bēi niúnǎi.', 'Xin chào! Tôi muốn một cốc sữa.', '牛奶'],
    ['1', 'Nhân viên', 'Nhân viên quán', '务', '好的，还要别的吗？', 'Hǎo de, hái yào bié de ma?', 'Được ạ, anh còn muốn gì nữa không?', ''],
    ['2', 'Tiểu Lan', 'Bạn cùng lớp', '兰', '你去哪儿？', 'Nǐ qù nǎr?', 'Bạn đi đâu đấy?', '哪儿'],
    ['2', 'Tiểu Minh', 'Bạn cùng lớp', '明', '我去超市。', 'Wǒ qù chāoshì.', 'Mình đi siêu thị.', '超市'],
  ],
};

const BLOCKS: SheetSpec = {
  name: 'Bài khoá',
  about: 'Tên và tình huống của từng bài khoá. Bỏ trống sheet này cũng được.',
  optional: true,
  columns: [
    { key: 'no', header: 'Bài khoá', alias: ['số', 'stt'], hint: 'Trùng với cột “Bài khoá” ở sheet Lời thoại', required: true },
    { key: 'title', header: 'Tên bài khoá', alias: ['tên', 'tiêu đề'], hint: 'Ở quán nước', required: true },
    { key: 'scene', header: 'Tình huống', alias: ['mô tả', 'bối cảnh'], hint: 'Tiểu Minh vào quán gọi đồ uống.' },
  ],
  sample: [
    ['1', 'Ở quán nước', 'Tiểu Minh vào quán gọi một cốc sữa.'],
    ['2', 'Trên đường', 'Tiểu Lan gặp Tiểu Minh và hỏi đi đâu.'],
  ],
};

export const dialogueImport: ImportSpec<DialogueBlock> = {
  title: 'Nhập hội thoại từ Excel',
  fileName: 'zika-mau-hoi-thoai.xlsx',
  intro: 'Nội dung đọc được sẽ hiện ngay trên biểu mẫu để bạn xem lại — bấm “Lưu thay đổi” thì mới ghi.',
  sheets: [LINES, BLOCKS],

  parse(sheets: Sheet[]): ParseResult<DialogueBlock> {
    const sheet = pickSheet(sheets, LINES, 0);
    const issues = new Issues(LINES.name);
    if (!sheet) {
      issues.add(-1, 'Không tìm thấy bảng nào trong file.');
      return { items: [], issues: issues.list, preview: { cols: [], rows: [] } };
    }

    const { at, index } = headerMap(sheet.rows, LINES.columns);
    if (at < 0 || index.speaker === undefined || index.zh === undefined) {
      issues.add(-1, 'Không thấy cột “Nhân vật” và “Tiếng Trung”. Hãy tải file mẫu và giữ nguyên dòng tiêu đề.');
      return { items: [], issues: issues.list, preview: previewOf(sheet, at) };
    }

    // Gom câu theo số bài khoá; giữ thứ tự xuất hiện trong file.
    const khoi = new Map<number, { speakers: DialogueSpeaker[]; lines: DialogueLine[] }>();

    sheet.rows.slice(at + 1).forEach((row, i) => {
      const line = at + 1 + i;
      if (isBlank(row)) return;

      const name = cellOf(row, index, 'speaker');
      const zh = cellOf(row, index, 'zh');
      if (name === '') {
        issues.add(line, 'Thiếu tên nhân vật.');
        return;
      }
      if (zh === '') {
        issues.add(line, 'Thiếu câu tiếng Trung.');
        return;
      }

      const no = Math.max(1, Math.round(parseNumber(cellOf(row, index, 'block'), 1)));
      let k = khoi.get(no);
      if (!k) {
        if (khoi.size >= 10) {
          issues.add(line, `Quá 10 bài khoá — bỏ qua bài khoá ${no}.`);
          return;
        }
        k = { speakers: [], lines: [] };
        khoi.set(no, k);
      }

      let who = k.speakers.find((s) => s.name === name);
      if (!who) {
        if (k.speakers.length >= 6) {
          issues.add(line, `Bài khoá ${no} quá 6 nhân vật — bỏ qua “${name}”.`);
          return;
        }
        who = {
          key: `k${no}-${k.speakers.length + 1}`,
          name,
          avatarChar: chuDaiDien(cellOf(row, index, 'avatar'), name),
          color: COLORS[k.speakers.length % COLORS.length],
          roleVi: cellOf(row, index, 'role'),
        };
        k.speakers.push(who);
      } else if (who.roleVi === '') {
        who.roleVi = cellOf(row, index, 'role');
      }

      if (k.lines.length >= 60) {
        issues.add(line, `Bài khoá ${no} quá 60 câu thoại — các dòng sau bị bỏ qua.`);
        return;
      }

      const highlight = cellOf(row, index, 'highlight');
      if (highlight !== '' && !zh.includes(highlight)) {
        issues.add(line, `Câu không chứa từ nhấn “${highlight}” — bỏ qua từ nhấn.`);
      }

      k.lines.push({
        speakerKey: who.key,
        zh,
        pinyin: cellOf(row, index, 'pinyin'),
        vi: cellOf(row, index, 'vi'),
        highlight: highlight !== '' && zh.includes(highlight) ? highlight : null,
      });
    });

    // ----- Tên và tình huống từng bài khoá (sheet tuỳ chọn) -----
    const ten = new Map<number, { title: string; scene: string }>();
    const found2 = pickSheet(sheets, BLOCKS, 1);
    const bSheet = found2 !== null && found2 !== sheet ? found2 : null;
    if (bSheet) {
      const bIssues = new Issues(BLOCKS.name);
      const b = headerMap(bSheet.rows, BLOCKS.columns);
      if (b.at >= 0 && b.index.no !== undefined) {
        bSheet.rows.slice(b.at + 1).forEach((row, i) => {
          const line = b.at + 1 + i;
          if (isBlank(row)) return;
          const no = Math.round(parseNumber(cellOf(row, b.index, 'no'), 0));
          const title = cellOf(row, b.index, 'title');
          if (no < 1) {
            bIssues.add(line, 'Cột “Bài khoá” phải là số từ 1 trở lên.');
            return;
          }
          if (title === '') {
            bIssues.add(line, `Bài khoá ${no} chưa có tên.`);
            return;
          }
          ten.set(no, { title, scene: cellOf(row, b.index, 'scene') });
        });
      }
      issues.list.push(...bIssues.list);
    }

    const items: DialogueBlock[] = [...khoi.entries()]
      .sort((a, b) => a[0] - b[0])
      .map(([no, k], i) => ({
        no: i + 1,
        title: ten.get(no)?.title ?? `Bài khoá ${i + 1}`,
        scene: ten.get(no)?.scene ?? '',
        speakers: k.speakers,
        lines: k.lines,
      }));

    if (items.length === 0) {
      return { items: [], issues: issues.list, preview: previewOf(sheet, at) };
    }

    const soCau = items.reduce((s, x) => s + x.lines.length, 0);
    return {
      items,
      issues: issues.list,
      preview: previewOf(sheet, at),
      summary: `${items.length} bài khoá · ${soCau} câu thoại`,
    };
  },
};
