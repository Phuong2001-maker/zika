-- Dữ liệu minh họa riêng cho admin; không liên kết tài khoản, tiến độ hay quỹ thưởng thật.
CREATE TABLE IF NOT EXISTS demo_learners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  activity_level VARCHAR(12) NOT NULL DEFAULT 'vua',
  minutes INT UNSIGNED NOT NULL DEFAULT 0,
  study_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
  completed_parts INT UNSIGNED NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  is_registered TINYINT(1) NOT NULL DEFAULT 0,
  show_contest TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demo_rewards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  learner_id INT UNSIGNED NOT NULL,
  contest_name VARCHAR(120) NOT NULL,
  reward_month CHAR(7) NOT NULL,
  contest_date DATE NOT NULL,
  ranking INT UNSIGNED NOT NULL,
  prize_name VARCHAR(80) NOT NULL,
  amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reward_status VARCHAR(20) NOT NULL DEFAULT 'cho',
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_demo_rewards_learner FOREIGN KEY (learner_id) REFERENCES demo_learners(id) ON DELETE CASCADE,
  KEY ix_demo_rewards_month (reward_month, ranking)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
