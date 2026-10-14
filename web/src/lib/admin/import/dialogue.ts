// Nhập Hội thoại từ Excel (08 §11).
//
// Hai sheet: “Lời thoại” (bắt buộc) và “Câu hỏi” (tuỳ chọn). Nhân vật không khai báo riêng mà
// suy ra từ cột Nhân vật — người soạn chỉ việc gõ tên ở từng câu, trùng tên là cùng một người.
//
// Khác 5 phần kia, bộ nhập này không gọi API ngay mà đổ thẳng vào biểu mẫu đang mở để người soạn
// xem lại rồi bấm “Lưu thay đổi” — vì hội thoại lưu cả khối một lần (PUT /admin/lessons/{id}/dialogue).

import type { Sheet } from '$lib/admin/xlsx';
import type { DialogueLine, DialogueQuestion, DialogueSpeaker } from '$lib/admin/api';
import {
  answerIndex,
  cellOf,
  headerMap,
  isBlank,
  Issues,
  pickSheet,
  previewOf,
  type ImportSpec,
  type ParseResult,
  type SheetSpec,
} from './core';

export interface DialogueBundle {
  speakers: DialogueSpeaker[];
  lines: DialogueLine[];
  questions: DialogueQuestion[];
}

/** Màu nhân vật gán lần lượt, lấy từ bảng màu của bộ thiết kế. */
const COLORS = ['#DCEBFF', '#CFF5E1', '#FFF0B3', '#E8E0FF', '#FFE0DC', '#D6E8FF'];

const LINES: SheetSpec = {
  name: 'Lời thoại',
  about: 'Mỗi dòng một câu thoại, theo đúng thứ tự trong bài. Gõ trùng tên ở cột Nhân vật là cùng một người.',
  columns: [
    { key: 'speaker', header: 'Nhân vật', alias: ['người nói', 'ai nói'], hint: 'Tên hiện trên bong bóng thoại', required: true },
    { key: 'role', header: 'Vai', alias: ['vai trò', 'mô tả'], hint: 'Khách · Nhân viên… (chỉ cần ghi ở dòng đầu của mỗi người)' },
    { key: 'zh', header: 'Tiếng Trung', alias: ['câu', 'nội dung', 'zh'], hint: 'Câu thoại', required: true },
    { key: 'pinyin', header: 'Pinyin', alias: ['phiên âm'], hint: 'Nǐ hǎo!' },
    { key: 'vi', header: 'Nghĩa tiếng Việt', alias: ['nghĩa', 'dịch'], hint: 'Xin chào!' },
    { key: 'highlight', header: 'Từ nhấn', alias: ['từ khoá', 'highlight'], hint: 'Từ được tô trong câu, phải có trong câu' },
  ],
  sample: [
    ['Tiểu Minh', 'Khách', '你好！我要一杯牛奶。', 'Nǐ hǎo! Wǒ yào yì bēi niúnǎi.', 'Xin chào! Tôi muốn một cốc sữa.', '牛奶'],
    ['Nhân viên', 'Nhân viên quán', '好的，还要别的吗？', 'Hǎo de, hái yào bié de ma?', 'Được ạ, anh còn muốn gì nữa không?', ''],
    ['Tiểu Minh', '', '再要两个鸡蛋，谢谢。', 'Zài yào liǎng gè jīdàn, xièxie.', 'Cho thêm hai quả trứng gà, cảm ơn.', '鸡蛋'],
  ],
};

const QUESTIONS: SheetSpec = {
  name: 'Câu hỏi',
  about: 'Câu hỏi hiểu bài, nhiều nhất 5 câu. Bỏ trống sheet này cũng được.',
  optional: true,
  columns: [
    { key: 'prompt', header: 'Câu hỏi', alias: ['đề bài'], hint: 'Viết bằng tiếng Trung hoặc tiếng Việt', required: true },
    { key: 'a', header: 'A', hint: 'Phương án A' },
    { key: 'b', header: 'B', hint: 'Phương án B' },
    { key: 'c', header: 'C', hint: 'Phương án C' },
    { key: 'd', header: 'D', hint: 'Phương án D' },
    { key: 'answer', header: 'Đáp án', alias: ['đáp án đúng'], hint: 'A, B, C hoặc D' },
  ],
  sample: [
    ['小明要买什么？', '牛奶和鸡蛋', '面包和牛奶', '米饭和鸡蛋', '只要牛奶', 'A'],
    ['小明要几个鸡蛋？', '两个', '一个', '三个', '不要鸡蛋', 'A'],
  ],
};

export const dialogueImport: ImportSpec<DialogueBundle> = {
  title: 'Nhập hội thoại từ Excel',
  fileName: 'zika-mau-hoi-thoai.xlsx',
  intro: 'Nội dung đọc được sẽ hiện ngay trên biểu mẫu để bạn xem lại — bấm “Lưu thay đổi” thì mới ghi.',
  sheets: [LINES, QUESTIONS],

  parse(sheets: Sheet[]): ParseResult<DialogueBundle> {
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

    const speakers: DialogueSpeaker[] = [];
    const lines: DialogueLine[] = [];

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

      let who = speakers.find((s) => s.name === name);
      if (!who) {
        if (speakers.length >= 6) {
          issues.add(line, `Quá 6 nhân vật — bỏ qua “${name}”.`);
          return;
        }
        who = {
          key: `k${speakers.length + 1}`,
          name,
          avatarChar: [...name].at(-1) ?? name,
          color: COLORS[speakers.length % COLORS.length],
          roleVi: cellOf(row, index, 'role'),
        };
        speakers.push(who);
      } else if (who.roleVi === '') {
        // Vai chỉ cần ghi một lần; dòng sau bổ sung thì vẫn nhận.
        who.roleVi = cellOf(row, index, 'role');
      }

      const highlight = cellOf(row, index, 'highlight');
      if (highlight !== '' && !zh.includes(highlight)) {
        issues.add(line, `Câu không chứa từ nhấn “${highlight}” — bỏ qua từ nhấn.`);
      }

      if (lines.length >= 60) {
        issues.add(line, 'Quá 60 câu thoại — các dòng sau bị bỏ qua.');
        return;
      }

      lines.push({
        speakerKey: who.key,
        zh,
        pinyin: cellOf(row, index, 'pinyin'),
        vi: cellOf(row, index, 'vi'),
        highlight: highlight !== '' && zh.includes(highlight) ? highlight : null,
      });
    });

    // ----- Câu hỏi hiểu bài (sheet tuỳ chọn) -----
    const questions: DialogueQuestion[] = [];
    // Tìm theo tên trước, để file gộp nhiều sheet của cả bài vẫn lấy đúng sheet câu hỏi.
    const found2 = pickSheet(sheets, QUESTIONS, 1);
    const qSheet = found2 !== null && found2 !== sheet ? found2 : null;
    if (qSheet) {
      const qIssues = new Issues(QUESTIONS.name);
      const q = headerMap(qSheet.rows, QUESTIONS.columns);
      if (q.at >= 0 && q.index.prompt !== undefined) {
        qSheet.rows.slice(q.at + 1).forEach((row, i) => {
          const line = q.at + 1 + i;
          if (isBlank(row)) return;
          if (questions.length >= 5) return;

          const prompt = cellOf(row, q.index, 'prompt');
          if (prompt === '') return;

          const options = ['a', 'b', 'c', 'd'].map((k) => cellOf(row, q.index, k)).filter((o) => o !== '');
          if (options.length < 2) {
            qIssues.add(line, 'Cần ít nhất 2 phương án.');
            return;
          }
          const answer = answerIndex(cellOf(row, q.index, 'answer'));
          if (answer < 0 || answer >= options.length) {
            qIssues.add(line, 'Cột Đáp án phải là A, B, C hoặc D.');
            return;
          }
          questions.push({ prompt, options, answer });
        });
      }
      issues.list.push(...qIssues.list);
    }

    if (lines.length === 0) {
      return { items: [], issues: issues.list, preview: previewOf(sheet, at) };
    }

    const summary =
      `${lines.length} câu thoại · ${speakers.length} nhân vật` + (questions.length ? ` · ${questions.length} câu hỏi` : '');

    return {
      items: [{ speakers, lines, questions }],
      issues: issues.list,
      preview: previewOf(sheet, at),
      summary,
    };
  },
};
