-- =====================================================================
-- Zìkǎ · 004 — File nghe cho cả bài khoá
--
-- File nghe giáo trình thu nguyên một bài khoá (10–45 giây), không cắt theo từng câu, nên
-- cột audio_path ở dialogue_lines không dùng được: gắn vào câu nào thì bấm câu đó cũng phát
-- cả đoạn. Nút "Nghe" của bài khoá phát file này; bài khoá chưa có file thì ẩn nút, không thay
-- bằng giọng máy (đọc máy sai thanh điệu, học viên tưởng là giọng chuẩn).
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE dialogues
  ADD COLUMN audio_path VARCHAR(255) NULL AFTER image_path;
