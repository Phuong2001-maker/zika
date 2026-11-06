-- =====================================================================
-- Zìkǎ · 013 — Giáo trình là cha của bài học
--
-- Giáo trình → cấp HSK → bài. Toàn bộ bài và từ đã nạp về giáo trình số 1 nên người học cũ vẫn thấy
-- đúng nội dung và tiến độ cũ. curriculum_id mặc định 1 để các công cụ nạp SQL cũ (data/) vẫn chạy.
-- users.curriculum_id NULL nghĩa là chưa gán, học theo giáo trình số 1.
-- Mỗi giáo trình có số bài và bộ từ riêng, nên hai khoá duy nhất phải thêm curriculum_id:
-- nếu không, giáo trình thứ hai không tạo được "Bài 1" và không có được từ đã có ở giáo trình khác.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS curricula (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  description  VARCHAR(300) NOT NULL DEFAULT '',
  color        CHAR(7)      NOT NULL DEFAULT '#E4EEDF',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_curricula_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO curricula (id, name, description)
VALUES (1, 'Giáo trình HSK chuẩn', 'Giáo trình mặc định: học viên chưa được gán sẽ học theo bộ này.');

ALTER TABLE lessons
  ADD COLUMN curriculum_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
  ADD CONSTRAINT fk_lessons_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id);
ALTER TABLE lessons
  DROP INDEX uq_lessons_no,
  ADD UNIQUE KEY uq_lessons_no (curriculum_id, level_id, lesson_no);

-- Khoá ngoại level_id đang dựa vào uq_words_level_hanzi, phải có chỉ mục thay thế trước khi bỏ.
ALTER TABLE words
  ADD COLUMN curriculum_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
  ADD KEY ix_words_level (level_id),
  ADD CONSTRAINT fk_words_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id);
ALTER TABLE words
  DROP INDEX uq_words_level_hanzi,
  ADD UNIQUE KEY uq_words_curriculum_hanzi (curriculum_id, level_id, hanzi);

ALTER TABLE users
  ADD COLUMN curriculum_id INT UNSIGNED NULL AFTER current_level_id,
  ADD CONSTRAINT fk_users_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE SET NULL;
