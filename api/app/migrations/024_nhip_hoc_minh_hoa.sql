-- Nhịp học riêng của người minh họa, không ghi bảng tiến độ/tài khoản thật.
ALTER TABLE demo_learners
  ADD COLUMN auto_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN auto_cursor DATETIME NULL,
  ADD COLUMN stats_month CHAR(7) NULL;

CREATE TABLE IF NOT EXISTS demo_activity_months (
  learner_id INT UNSIGNED NOT NULL,
  month CHAR(7) NOT NULL,
  minutes INT UNSIGNED NOT NULL DEFAULT 0,
  study_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
  completed_parts INT UNSIGNED NOT NULL DEFAULT 0,
  auto_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (learner_id, month),
  CONSTRAINT fk_demo_month_learner FOREIGN KEY (learner_id) REFERENCES demo_learners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demo_activity_days (
  learner_id INT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  plan_json TEXT NOT NULL,
  credited_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (learner_id, day),
  CONSTRAINT fk_demo_day_learner FOREIGN KEY (learner_id) REFERENCES demo_learners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
