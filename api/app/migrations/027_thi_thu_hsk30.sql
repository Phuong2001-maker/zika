-- =====================================================================
-- Zìkǎ · 027 — Thi thử HSK 3.0 (09/11/2026)
--
-- Đề HSK 3.0 dùng lại bảng mock_tests (soạn câu, chấm, màn làm bài có sẵn) nhưng KHÔNG gắn giáo trình:
-- học viên học bộ nào cũng thấy cùng một kho đề. kind phân biệt với "Thi thử cuối cấp" cũ ('mock').
-- Đề có 4 phần: nghe, đọc, viết và nói. Câu nói chỉ ghi âm ở máy người học, server không nhận file âm thanh.
-- mode: 'exam' thi thật tính giờ, chấm khi nộp; 'practice' luyện tập, xem đáp án từng câu.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE mock_tests
  ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'mock' AFTER for_contest,
  ADD KEY ix_mt_kind (kind, level_id, status);

ALTER TABLE questions
  MODIFY section ENUM('listening','reading','writing','speaking') NULL;

ALTER TABLE test_attempts
  ADD COLUMN mode VARCHAR(8) NOT NULL DEFAULT 'exam' AFTER contest_id;
