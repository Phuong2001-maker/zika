-- =====================================================================
-- Zìkǎ · 014 — Số giây làm bài đủ rộng và bảng ngày học
--
-- 1. used_sec là SMALLINT UNSIGNED (tối đa 65535 giây ≈ 18,2 giờ). Cuộc thi dài hơn thế làm lệnh
--    chấm bài lỗi, kéo theo nộp bài và bảng xếp hạng đều 500. Nới lên INT UNSIGNED.
-- 2. study_days: mỗi ngày người học thật sự học là một dòng. Trước đây chuỗi ngày suy ra từ
--    lesson_progress.updated_at (bị ghi đè mỗi lần học lại) nên học một bài hai ngày chỉ đếm một ngày.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE test_attempts MODIFY used_sec INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS study_days (
  user_id BIGINT UNSIGNED NOT NULL,
  day     DATE NOT NULL,
  PRIMARY KEY (user_id, day),
  CONSTRAINT fk_sd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lấp lịch sử từ các bảng cũ. Chỉ tính hoạt động thật: có vị trí, đã xong, hoặc đã trả lời câu nào.
INSERT IGNORE INTO study_days (user_id, day)
  SELECT user_id, DATE(updated_at) FROM lesson_progress WHERE position > 0 OR status = 'done'
  UNION SELECT user_id, DATE(updated_at) FROM review_progress WHERE position > 0 OR times_done > 0
  UNION SELECT user_id, DATE(started_at) FROM exercise_attempts WHERE JSON_LENGTH(results) > 0
  UNION SELECT user_id, DATE(started_at) FROM test_attempts WHERE submitted_at IS NOT NULL OR JSON_LENGTH(answers) > 0;
