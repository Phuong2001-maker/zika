-- =====================================================================
-- Zìkǎ · 006 — Hội thoại mặc định ẩn pinyin và nghĩa
--
-- Đồng bộ với câu ví dụ ở lật thẻ và ngữ pháp: mở bài chỉ thấy chữ Hán, người học tự bật nút
-- Pinyin | Nghĩa khi cần. Mặc định trong UserService đã đổi sang false, nhưng ai từng bấm lưu
-- Cài đặt thì giá trị true cũ (mặc định trước đây) đã nằm trong user_settings — đặt lại một lần.
-- Ai muốn hiện sẵn thì bật lại hai dòng "Hội thoại: hiện …" trong Cài đặt.
-- =====================================================================
SET NAMES utf8mb4;

UPDATE user_settings
SET data = JSON_SET(data, '$.dialogue', JSON_OBJECT('showPinyin', FALSE, 'showVi', FALSE));
