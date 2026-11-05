-- =====================================================================
-- Zìkǎ · 010 — Gắn lượt làm bài với cuộc thi để xếp hạng
--
-- contest_id: một đề có thể dùng cho nhiều cuộc thi và cả thi thử thường, nên phải ghi rõ lượt nào thuộc cuộc thi nào.
-- correct_count: xếp hạng theo số câu đúng; để cột riêng thì không phải đọc JSON kết quả của mọi lượt.
-- UNIQUE (contest_id, user_id): mỗi người một lượt, kể cả khi mở hai tab cùng lúc. contest_id NULL thì không bị giới hạn.
-- Xoá cuộc thi không xoá lượt làm, chỉ gỡ liên kết.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE test_attempts
  ADD COLUMN contest_id INT UNSIGNED NULL AFTER mock_test_id,
  ADD COLUMN correct_count SMALLINT UNSIGNED NULL AFTER max_score,
  ADD UNIQUE KEY uq_ta_contest_user (contest_id, user_id),
  ADD CONSTRAINT fk_ta_contest FOREIGN KEY (contest_id) REFERENCES contests(id) ON DELETE SET NULL;
