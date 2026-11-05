// Màn đang làm bài có nút chính nằm thấp, lời nhắn của bé từng che đúng nút "Kiểm tra" nên người học
// bấm nhầm thành ẩn bé. Bé chỉ hiện ở các màn chọn cấp và chọn bài.
const MAN_LAM_BAI = /^\/learn\/(?:books\/[^/]+|.*\/(?:lesson|review|mock)(?:\/.*)?)$/;

export function buddyShownOn(path: string): boolean {
  return path.startsWith('/learn/') && !MAN_LAM_BAI.test(path);
}
