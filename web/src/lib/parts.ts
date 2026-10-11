// 6 phần học: mã, tên, slug route, màu, icon (README §2) và chữ riêng từng phần (07 §5.3).
import type { Part } from '$lib/api/types';

export interface PartInfo {
  code: Part;
  name: string; // tên hiển thị đầy đủ
  short: string; // tên ngắn (admin)
  slug: string; // /learn/<slug>
  accent: string; // màu ô icon
  light: string; // màu nền nhạt
  icon: string;
  chip: string; // màu chip ở bảng Phiên truy cập
}

export const PARTS: PartInfo[] = [
  { code: 'lt', name: 'Lật thẻ', short: 'Từ vựng', slug: 'flashcards', accent: '#DCEBFF', light: '#DCEBFF', icon: 'flip', chip: '#DCEBFF' },
  { code: 'bt', name: 'Bài tập', short: 'Bài tập', slug: 'exercises', accent: '#7FDDA9', light: '#CFF5E1', icon: 'exercise', chip: '#CFF5E1' },
  { code: 'kt', name: 'Kiểm tra', short: 'Kiểm tra', slug: 'tests', accent: '#FF9A90', light: '#FFE0DC', icon: 'quiz', chip: '#FFE0DC' },
  { code: 'lv', name: 'Luyện viết từ mới', short: 'Luyện viết', slug: 'writing', accent: '#FFD84D', light: '#FFF0B3', icon: 'brush', chip: '#FFF0B3' },
  { code: 'ht', name: 'Đọc hội thoại', short: 'Hội thoại', slug: 'dialogues', accent: '#8FC1FF', light: '#DCEBFF', icon: 'chat', chip: '#D6E8FF' },
  { code: 'np', name: 'Cấu trúc ngữ pháp', short: 'Ngữ pháp', slug: 'grammar', accent: '#C9B8FF', light: '#E8E0FF', icon: 'blocks', chip: '#E8E0FF' },
];

export const PART: Record<Part, PartInfo> = Object.fromEntries(PARTS.map((p) => [p.code, p])) as Record<Part, PartInfo>;

export function partBySlug(slug: string): PartInfo | undefined {
  return PARTS.find((p) => p.slug === slug);
}

/** Màu nền cấp HSK (CSS var để chế độ tối đổi được). */
export function levelBg(n: number): string {
  return `var(--lv${n})`;
}

/** Chữ riêng của 5 phần dùng chung khuôn Chọn cấp / Chọn bài (07 §5.3). */
export interface PartTexts {
  homeDesc: string;
  past: string; // “đã {past}”
  contTitle: string; // cont0 khi có lượt dở
  contStart: string; // cont2 khi có lượt dở
  nextTitle: string; // cont0 khi không có lượt dở
  nextStart: string; // cont2 khi không có lượt dở
  doingTag: string | null; // nhãn đang dở trên thẻ bài
  allTitle: (n: number) => string;
  allDesc: (n: number, count: number, lessons: number) => string;
  allLines: (count: number, lessons: number, extra?: { minutes?: number }) => string[];
  allStart: string;
}

export const PART_TEXTS: Record<Exclude<Part, 'lt'>, PartTexts> = {
  bt: {
    homeDesc:
      'Mỗi bài có 10 câu, 8 dạng: chọn nghĩa, điền chỗ trống, nghe chọn chữ, pinyin, sắp xếp câu, dịch, đúng/sai, nối từ. Làm xong câu nào chấm ngay câu đó.',
    past: 'làm',
    contTitle: 'Làm tiếp',
    contStart: 'Làm tiếp',
    nextTitle: 'Bài tiếp theo',
    nextStart: 'Bắt đầu làm bài',
    doingTag: 'Đang làm',
    allTitle: (n) => `Bài tập tổng hợp HSK ${n}`,
    allDesc: (_n, count, lessons) => `${count} câu trộn từ cả ${lessons} bài.`,
    allLines: (count, lessons) => [`${count} câu trộn từ ${lessons} bài`, 'Nên làm sau khi xong các bài'],
    allStart: 'Làm bài tổng hợp',
  },
  kt: {
    homeDesc:
      'Mỗi bài có một bài kiểm tra 10 câu với 10 dạng khác nhau và đồng hồ đếm ngược. Cuối cấp có đề thi thử giống đề HSK thật.',
    past: 'kiểm tra',
    contTitle: 'Bài kiểm tra tiếp theo',
    contStart: 'Bắt đầu kiểm tra',
    nextTitle: 'Bài kiểm tra tiếp theo',
    nextStart: 'Bắt đầu kiểm tra',
    doingTag: null,
    allTitle: (n) => `Thi thử HSK ${n}`,
    allDesc: () => '60 câu nghe và đọc, khoảng 55 phút, giống đề thi thật.',
    allLines: (count, _l, extra) => [`${count} câu · khoảng ${extra?.minutes ?? 55} phút`, 'Phần nghe và phần đọc như đề thật', 'Chấm điểm ngay khi nộp'],
    allStart: 'Bắt đầu thi thử',
  },
  lv: {
    homeDesc: 'Viết từng chữ Hán của bài theo đúng thứ tự nét: xem mẫu, tô theo nét mờ rồi tự viết.',
    past: 'viết',
    contTitle: 'Viết tiếp',
    contStart: 'Viết tiếp',
    nextTitle: 'Bài tiếp theo',
    nextStart: 'Bắt đầu luyện viết',
    doingTag: 'Đang viết',
    allTitle: (n) => `Luyện viết cả cấp HSK ${n}`,
    allDesc: (_n, _c, lessons) => `Ôn viết tất cả chữ Hán của ${lessons} bài.`,
    allLines: (_c, lessons) => [`Tất cả chữ của ${lessons} bài`, 'Có thể chọn chỉ viết lại chữ hay sai'],
    allStart: 'Bắt đầu luyện viết',
  },
  ht: {
    homeDesc: 'Mỗi bài có một đoạn hội thoại ngắn: nghe, đọc theo từng câu, bật tắt pinyin và nghĩa.',
    past: 'đọc',
    contTitle: 'Đọc tiếp',
    contStart: 'Đọc tiếp',
    nextTitle: 'Bài tiếp theo',
    nextStart: 'Bắt đầu đọc',
    doingTag: 'Đang đọc',
    allTitle: (n) => `Ôn hội thoại HSK ${n}`,
    allDesc: (_n, _c, lessons) => `Nghe liền ${lessons} đoạn hội thoại của cả cấp.`,
    allLines: (_c, lessons) => [`${lessons} đoạn hội thoại`, 'Nghe liền như podcast'],
    allStart: 'Nghe cả cấp',
  },
  np: {
    homeDesc: 'Mỗi bài có 2–3 cấu trúc ngữ pháp: công thức, ví dụ khẳng định, phủ định, câu hỏi và bài sắp xếp câu.',
    past: 'xem',
    contTitle: 'Xem tiếp',
    contStart: 'Xem tiếp',
    nextTitle: 'Bài tiếp theo',
    nextStart: 'Xem ngữ pháp',
    doingTag: 'Đang xem',
    allTitle: (n) => `Tổng hợp ngữ pháp HSK ${n}`,
    allDesc: (_n, _c, lessons) => `Bảng tóm tắt mọi cấu trúc của ${lessons} bài.`,
    allLines: (_c, lessons) => [`Mọi cấu trúc của ${lessons} bài`, 'Tra nhanh theo công thức'],
    allStart: 'Xem bảng tổng hợp',
  },
};
