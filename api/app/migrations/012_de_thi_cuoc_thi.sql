-- =====================================================================
-- Zìkǎ · 012 — Đề thi cho cuộc thi
--
-- Tab "Đề thi" chỉ cần nhập tên, câu hỏi nạp sau. Vẫn dùng bảng mock_tests để bài thi, trang soạn đề
-- và chấm điểm chạy chung một đường; for_contest tách đề này khỏi danh sách Thi thử cuối cấp.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE mock_tests ADD COLUMN for_contest TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
