-- =====================================================================
-- Zìkǎ · 011 — Lịch thi gọn và đăng ký thi
--
-- duration_min: admin chỉ nhập giờ bắt đầu và số phút thi; ends_at vẫn giữ, do server tính ra để truy vấn nhanh.
-- contest_registrations: chỉ người đăng ký trước giờ bắt đầu mới được làm bài.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE contests ADD COLUMN duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER starts_at;
UPDATE contests SET duration_min = LEAST(TIMESTAMPDIFF(MINUTE, starts_at, ends_at), 65535);

CREATE TABLE IF NOT EXISTS contest_registrations (
  contest_id  INT UNSIGNED    NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME        NOT NULL,
  PRIMARY KEY (contest_id, user_id),
  KEY ix_cr_user (user_id),
  CONSTRAINT fk_cr_contest FOREIGN KEY (contest_id) REFERENCES contests(id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
