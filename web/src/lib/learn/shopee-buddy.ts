// Bé chỉ hiện ở Bàn học và các màn chọn cấp. Màn chọn bài và màn đang làm bài có nút chính nằm thấp:
// lời nhắn của bé từng che đúng nút "Kiểm tra", "Bắt đầu" nên người học bấm nhầm thành ẩn bé.
const KHONG_HIEN = /^\/learn\/(?:books\/[^/]+|.*\/hsk\/\d+(?:\/.*)?)$/;

export function buddyShownOn(path: string): boolean {
  return path.startsWith('/learn/') && !KHONG_HIEN.test(path);
}
