-- =====================================================================
-- Zìkǎ · schema.sql
-- Chạy được trên MySQL 8.0+ và MariaDB 10.6+.
-- Mọi bảng: InnoDB, utf8mb4. Cột chữ Hán dùng làm khoá duy nhất → utf8mb4_bin.
-- Thời gian lưu theo giờ Việt Nam (+07:00): kết nối PDO phải chạy SET time_zone = '+07:00'.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Hệ thống
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
  filename     VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  k            VARCHAR(64)  NOT NULL PRIMARY KEY,
  v            JSON         NOT NULL,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  k            VARCHAR(190) NOT NULL PRIMARY KEY,         -- ví dụ 'login:1.2.3.4:a@b.com'
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME     NOT NULL,
  KEY ix_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_queue (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email     VARCHAR(190) NOT NULL,
  subject      VARCHAR(200) NOT NULL,
  html         MEDIUMTEXT   NOT NULL,
  text_body    MEDIUMTEXT   NOT NULL,
  kind         VARCHAR(30)  NOT NULL DEFAULT 'misc',     -- verify|reset|invite|reply|announce|welcome|misc
  status       ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error   VARCHAR(500) NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at      DATETIME     NULL,
  KEY ix_mq_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NULL,
  action       VARCHAR(60)  NOT NULL,                     -- 'lesson.update', 'user.lock', 'word.delete'…
  entity       VARCHAR(40)  NOT NULL,
  entity_id    BIGINT UNSIGNED NULL,
  detail       JSON         NULL,
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_time (created_at),
  KEY ix_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Người dùng và xác thực
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email               VARCHAR(190) NOT NULL,
  email_verified_at   DATETIME     NULL,
  password_hash       VARCHAR(255) NULL,                  -- NULL = chỉ đăng nhập Google
  google_sub          VARCHAR(64)  NULL,
  full_name           VARCHAR(120) NOT NULL DEFAULT '',   -- “Nguyễn Minh Anh”
  display_name        VARCHAR(60)  NOT NULL,              -- “Minh Anh”
  avatar_path         VARCHAR(255) NULL,                  -- /uploads/img/… hoặc URL ảnh Google
  role                ENUM('learner','editor','admin') NOT NULL DEFAULT 'learner',
  status              ENUM('active','locked')          NOT NULL DEFAULT 'active',
  signup_method       ENUM('email','google')           NOT NULL,
  self_level_initial  ENUM('beginner','hsk12','hsk34','hsk5') NOT NULL DEFAULT 'beginner',
  self_level          ENUM('beginner','hsk12','hsk34','hsk5') NOT NULL DEFAULT 'beginner',
  daily_goal_min      TINYINT UNSIGNED NOT NULL DEFAULT 15,
  current_level_id    TINYINT UNSIGNED NULL,              -- cấp đang học (cập nhật khi lưu tiến độ)
  total_study_sec     INT UNSIGNED NOT NULL DEFAULT 0,    -- cộng dồn từ heartbeat
  lessons_done        SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- số bài đã học xong (Lật thẻ)
  visits_count        INT UNSIGNED NOT NULL DEFAULT 0,
  last_seen_at        DATETIME     NULL,
  last_device         ENUM('phone','tablet','desktop') NULL,
  locked_at           DATETIME     NULL,
  locked_reason       VARCHAR(255) NULL,
  terms_accepted_at   DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email  (email),
  UNIQUE KEY uq_users_google (google_sub),
  KEY ix_users_last_seen (last_seen_at),
  KEY ix_users_created   (created_at),
  KEY ix_users_status    (status),
  KEY ix_users_level     (current_level_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_settings (
  user_id      BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  data         JSON         NOT NULL,                     -- xem 03-database.md §4.1
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_sessions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64)     NOT NULL,                     -- sha256(cookie zk_sid)
  remember     TINYINT(1)   NOT NULL DEFAULT 0,
  device_type  ENUM('phone','tablet','desktop') NOT NULL DEFAULT 'desktop',
  os           VARCHAR(40)  NOT NULL DEFAULT '',          -- “Android”, “iPhone”, “Windows”, “macOS”…
  browser      VARCHAR(40)  NOT NULL DEFAULT '',          -- “Chrome”, “Edge”, “Safari”…
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME     NOT NULL,
  expires_at   DATETIME     NOT NULL,
  revoked_at   DATETIME     NULL,
  UNIQUE KEY uq_as_token (token_hash),
  KEY ix_as_user (user_id, revoked_at),
  CONSTRAINT fk_as_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_tokens (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  kind         ENUM('verify','reset') NOT NULL,
  token_hash   CHAR(64)     NOT NULL,
  expires_at   DATETIME     NOT NULL,
  used_at      DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_et_token (token_hash),
  KEY ix_et_user (user_id, kind),
  CONSTRAINT fk_et_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_invites (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(190) NOT NULL,
  role         ENUM('editor','admin') NOT NULL,
  token_hash   CHAR(64)     NOT NULL,
  invited_by   BIGINT UNSIGNED NULL,
  expires_at   DATETIME     NOT NULL,
  accepted_at  DATETIME     NULL,
  accepted_user_id BIGINT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_token (token_hash),
  KEY ix_ai_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (                -- [P3] nhắc học
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  endpoint     VARCHAR(600) NOT NULL,
  p256dh       VARCHAR(200) NOT NULL,
  auth_key     VARCHAR(100) NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ps_user (user_id),
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Nội dung học
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS levels (
  id                    TINYINT UNSIGNED NOT NULL PRIMARY KEY,   -- 1..6
  name                  VARCHAR(20)  NOT NULL,                    -- 'HSK 1'
  target_words          SMALLINT UNSIGNED NOT NULL,               -- số từ mục tiêu của cấp
  sample_text           VARCHAR(60)  NOT NULL DEFAULT '',         -- '你好 · 喜欢'
  is_visible            TINYINT(1)   NOT NULL DEFAULT 0,          -- Hiện cho người học
  review_published_only TINYINT(1)   NOT NULL DEFAULT 1,          -- Tổng hợp chỉ gộp bài đang hiện
  review_bt_count       SMALLINT UNSIGNED NOT NULL DEFAULT 50,    -- số câu Bài tập tổng hợp
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lessons (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level_id     TINYINT UNSIGNED NOT NULL,
  lesson_no    SMALLINT UNSIGNED NOT NULL,                 -- số thứ tự hiển thị “Bài 6”
  title        VARCHAR(120) NOT NULL,                      -- “Ăn uống”
  sample_text  VARCHAR(60)  NOT NULL DEFAULT '',           -- tự sinh: 2 từ đầu “鸡蛋 · 牛奶”
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  cnt_words    SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- đếm sẵn, cập nhật khi lưu
  cnt_bt       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cnt_kt       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cnt_lv       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cnt_ht       SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- số câu thoại
  cnt_np       SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- số điểm ngữ pháp
  updated_by   BIGINT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lessons_no (level_id, lesson_no),
  KEY ix_lessons_status (level_id, status),
  CONSTRAINT fk_lessons_level FOREIGN KEY (level_id) REFERENCES levels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS words (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level_id     TINYINT UNSIGNED NOT NULL,
  lesson_id    INT UNSIGNED NULL,                          -- NULL = chưa gắn bài
  hanzi        VARCHAR(32)  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  pinyin       VARCHAR(120) NOT NULL,                      -- 'jīdàn' (có dấu thanh)
  han_viet     VARCHAR(120) NOT NULL DEFAULT '',           -- 'Kê đản' (hiển thị viết HOA)
  meaning_vi   VARCHAR(255) NOT NULL,
  pos          ENUM('n','v','adj','adv','mw','phrase','num','pron','prep','conj','part','other') NOT NULL DEFAULT 'n',
  note         TEXT         NULL,
  audio_path   VARCHAR(255) NULL,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_words_level_hanzi (level_id, hanzi),
  KEY ix_words_lesson (lesson_id, sort_order),
  CONSTRAINT fk_words_level  FOREIGN KEY (level_id)  REFERENCES levels(id),
  CONSTRAINT fk_words_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS word_examples (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  word_id      INT UNSIGNED NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  zh           VARCHAR(255) NOT NULL,                      -- '我早上吃了两个鸡蛋。'
  pinyin       VARCHAR(500) NOT NULL DEFAULT '',
  vi           VARCHAR(500) NOT NULL DEFAULT '',
  highlight    VARCHAR(32)  NULL,                          -- NULL = dùng words.hanzi
  audio_path   VARCHAR(255) NULL,
  KEY ix_we_word (word_id, sort_order),
  CONSTRAINT fk_we_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS characters (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hanzi              VARCHAR(4)   CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  pinyin             VARCHAR(20)  NOT NULL DEFAULT '',
  han_viet           VARCHAR(60)  NOT NULL DEFAULT '',     -- 'KÊ', 'DIỆN / MIẾN'
  meaning_vi         VARCHAR(255) NOT NULL DEFAULT '',
  strokes            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  structure          VARCHAR(160) NOT NULL DEFAULT '',     -- 'Trái phải: 又 + 鸟'
  radical            VARCHAR(4)   NULL,
  stroke_data_status ENUM('ok','missing','custom') NOT NULL DEFAULT 'missing',
  stroke_data_path   VARCHAR(255) NULL,                    -- /uploads/hanzi/xx/<codepoint>.json
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_char_hanzi (hanzi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_chars (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id      INT UNSIGNED NOT NULL,
  char_id        INT UNSIGNED NOT NULL,
  source_word_id INT UNSIGNED NULL,                        -- từ chứa chữ (“Có trong từ: 鸡蛋”)
  sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_visible     TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_lc (lesson_id, char_id),
  CONSTRAINT fk_lc_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_lc_char   FOREIGN KEY (char_id)   REFERENCES characters(id),
  CONSTRAINT fk_lc_word   FOREIGN KEY (source_word_id) REFERENCES words(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS writing_configs (
  lesson_id          INT UNSIGNED NOT NULL PRIMARY KEY,
  start_mode         ENUM('demo','trace','free') NOT NULL DEFAULT 'trace',  -- Xem mẫu | Tô theo | Tự viết
  grid               ENUM('tian','mi')           NOT NULL DEFAULT 'mi',     -- Ô 田 | Ô 米
  pass_count         TINYINT UNSIGNED NOT NULL DEFAULT 2,                   -- viết đúng mấy lần thì xong chữ
  hint_after_misses  TINYINT UNSIGNED NOT NULL DEFAULT 2,                   -- sai mấy lần thì nét nháy sáng
  show_animation     TINYINT(1) NOT NULL DEFAULT 1,
  highlight_radical  TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_wc_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mock_tests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level_id     TINYINT UNSIGNED NOT NULL,
  title        VARCHAR(80)  NOT NULL,                      -- 'Đề 1'
  duration_sec SMALLINT UNSIGNED NOT NULL DEFAULT 3300,    -- 55 phút
  max_points   SMALLINT UNSIGNED NOT NULL DEFAULT 200,
  pass_points  SMALLINT UNSIGNED NOT NULL DEFAULT 120,
  listen_limit TINYINT UNSIGNED  NOT NULL DEFAULT 2,
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_mt_level (level_id, status),
  CONSTRAINT fk_mt_level FOREIGN KEY (level_id) REFERENCES levels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  part         ENUM('bt','kt','mock') NOT NULL,
  lesson_id    INT UNSIGNED NULL,                          -- bt, kt
  mock_test_id INT UNSIGNED NULL,                          -- mock
  type         VARCHAR(12)  NOT NULL,                      -- xem 06-dang-cau-hoi.md
  payload      JSON         NOT NULL,
  explain_vi   TEXT         NULL,
  score        DECIMAL(5,1) NOT NULL DEFAULT 1.0,
  section      ENUM('listening','reading') NULL,           -- chỉ đề thi thử
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_q_lesson (lesson_id, part, sort_order),
  KEY ix_q_mock   (mock_test_id, sort_order),
  CONSTRAINT fk_q_lesson FOREIGN KEY (lesson_id)    REFERENCES lessons(id)    ON DELETE CASCADE,
  CONSTRAINT fk_q_mock   FOREIGN KEY (mock_test_id) REFERENCES mock_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_configs (
  lesson_id          INT UNSIGNED NOT NULL PRIMARY KEY,
  duration_sec       SMALLINT UNSIGNED NOT NULL DEFAULT 300,
  pass_score         DECIMAL(5,1) NOT NULL DEFAULT 6.0,
  listen_limit       TINYINT UNSIGNED NOT NULL DEFAULT 2,
  attempts_limit     TINYINT UNSIGNED NULL,                -- NULL = không giới hạn
  shuffle_questions  TINYINT(1) NOT NULL DEFAULT 1,
  shuffle_options    TINYINT(1) NOT NULL DEFAULT 1,
  auto_submit        TINYINT(1) NOT NULL DEFAULT 1,
  show_answers_after TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_tc_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dialogues (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id    INT UNSIGNED NOT NULL,
  title        VARCHAR(120) NOT NULL,                      -- 'Ở nhà hàng'
  scene        TEXT         NOT NULL,                      -- Tình huống
  image_path   VARCHAR(255) NULL,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dlg_lesson (lesson_id),
  CONSTRAINT fk_dlg_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dialogue_speakers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dialogue_id  INT UNSIGNED NOT NULL,
  name         VARCHAR(30)  NOT NULL,                      -- '小王'
  avatar_char  VARCHAR(4)   NOT NULL,                      -- '王'
  color        CHAR(7)      NOT NULL DEFAULT '#DCEBFF',
  role_vi      VARCHAR(40)  NOT NULL DEFAULT '',           -- 'khách', 'phục vụ'
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  KEY ix_ds_dlg (dialogue_id),
  CONSTRAINT fk_ds_dlg FOREIGN KEY (dialogue_id) REFERENCES dialogues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dialogue_lines (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dialogue_id  INT UNSIGNED NOT NULL,
  speaker_id   INT UNSIGNED NOT NULL,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  zh           VARCHAR(500) NOT NULL,
  pinyin       VARCHAR(800) NOT NULL DEFAULT '',
  vi           VARCHAR(800) NOT NULL DEFAULT '',
  highlight    VARCHAR(32)  NULL,                          -- từ mới tô sáng trong câu
  audio_path   VARCHAR(255) NULL,
  KEY ix_dl_dlg (dialogue_id, sort_order),
  CONSTRAINT fk_dl_dlg FOREIGN KEY (dialogue_id) REFERENCES dialogues(id) ON DELETE CASCADE,
  CONSTRAINT fk_dl_spk FOREIGN KEY (speaker_id)  REFERENCES dialogue_speakers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dialogue_questions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dialogue_id  INT UNSIGNED NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  prompt_zh    VARCHAR(255) NOT NULL,                      -- '小王想吃什么？'
  options      JSON         NOT NULL,                      -- ["面条","羊肉","米饭"]
  answer       TINYINT UNSIGNED NOT NULL,                  -- chỉ số đáp án đúng
  KEY ix_dq_dlg (dialogue_id, sort_order),
  CONSTRAINT fk_dq_dlg FOREIGN KEY (dialogue_id) REFERENCES dialogues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grammar_points (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id    INT UNSIGNED NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  title        VARCHAR(120) NOT NULL,                      -- 'V + 过'
  subtitle     VARCHAR(160) NOT NULL DEFAULT '',           -- 'Đã từng làm gì'
  explain_vi   TEXT         NOT NULL,
  note_vi      TEXT         NULL,                          -- Lưu ý / lỗi hay gặp
  blocks       JSON         NOT NULL,                      -- [{label,hz,color}]
  examples     JSON         NOT NULL,                      -- [{kind,formula,zh,highlight,pinyin,vi}]
  practice     JSON         NOT NULL,                      -- {answer, vi, tiles[]}
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_gp_lesson (lesson_id, sort_order),
  CONSTRAINT fk_gp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audio_assets (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  text_hash    CHAR(40)     NOT NULL,                      -- sha1(text_zh)
  text_zh      VARCHAR(500) NOT NULL,
  voice        VARCHAR(40)  NOT NULL,                      -- 'xiaoxiao' | 'yunxi' | 'upload'
  path         VARCHAR(255) NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_aa (text_hash, voice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dict_words (                         -- từ điển nội bộ cho “Điền tự động”
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hanzi        VARCHAR(32)  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  pinyin       VARCHAR(120) NOT NULL,
  han_viet     VARCHAR(120) NOT NULL DEFAULT '',
  meaning_vi   VARCHAR(500) NOT NULL DEFAULT '',
  pos          VARCHAR(10)  NOT NULL DEFAULT '',
  source       VARCHAR(20)  NOT NULL DEFAULT 'import',
  UNIQUE KEY uq_dict (hanzi, pinyin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tiến độ học
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lesson_progress (
  user_id       BIGINT UNSIGNED NOT NULL,
  lesson_id     INT UNSIGNED    NOT NULL,
  part          ENUM('lt','bt','kt','lv','ht','np') NOT NULL,
  status        ENUM('in_progress','done') NOT NULL DEFAULT 'in_progress',  -- 'done' giữ nguyên khi học lại
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,      -- vị trí của lượt đang dở (0 = chưa bắt đầu lượt mới)
  total         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  best_score    DECIMAL(6,1) NULL,
  last_score    DECIMAL(6,1) NULL,
  max_score     DECIMAL(6,1) NULL,
  times_done    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  state         JSON NULL,                                 -- xem 03-database.md §4.2
  started_at    DATETIME NOT NULL,
  first_done_at DATETIME NULL,
  last_done_at  DATETIME NULL,
  updated_at    DATETIME NOT NULL,
  PRIMARY KEY (user_id, lesson_id, part),
  KEY ix_lp_lesson (lesson_id, part, status),
  KEY ix_lp_user_recent (user_id, updated_at),
  CONSTRAINT fk_lp_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_progress (
  user_id      BIGINT UNSIGNED NOT NULL,
  level_id     TINYINT UNSIGNED NOT NULL,
  part         ENUM('lt','bt','kt','lv','ht','np') NOT NULL,
  position     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  times_done   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  state        JSON NULL,
  updated_at   DATETIME NOT NULL,
  PRIMARY KEY (user_id, level_id, part),
  CONSTRAINT fk_rp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  level_id     TINYINT UNSIGNED NOT NULL,
  lesson_id    INT UNSIGNED NULL,                          -- NULL = Bài tập tổng hợp
  mode         ENUM('full','retry_wrong','review') NOT NULL DEFAULT 'full',
  question_ids JSON NOT NULL,                              -- thứ tự câu của lượt
  results      JSON NOT NULL,                              -- [{qid, answer, correct}]
  score        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  started_at   DATETIME NOT NULL,
  finished_at  DATETIME NULL,
  KEY ix_ea_user (user_id, lesson_id, started_at),
  CONSTRAINT fk_ea_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ea_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_attempts (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        BIGINT UNSIGNED NOT NULL,
  lesson_id      INT UNSIGNED NULL,                        -- bài kiểm tra của bài
  mock_test_id   INT UNSIGNED NULL,                        -- đề thi thử
  question_ids   JSON NOT NULL,                            -- thứ tự câu hiển thị
  option_maps    JSON NOT NULL,                            -- {qid: [chỉ số gốc theo thứ tự hiển thị]}
  answers        JSON NOT NULL,                            -- {qid: đáp án theo chỉ số gốc}
  flags          JSON NOT NULL,                            -- [qid, …] đánh dấu xem lại
  listen_counts  JSON NOT NULL,                            -- {qid: số lần đã nghe}
  started_at     DATETIME(3) NOT NULL,
  deadline_at    DATETIME(3) NOT NULL,
  submitted_at   DATETIME(3) NULL,
  auto_submitted TINYINT(1) NOT NULL DEFAULT 0,
  score          DECIMAL(6,1) NULL,
  max_score      DECIMAL(6,1) NULL,
  passed         TINYINT(1) NULL,
  used_sec       SMALLINT UNSIGNED NULL,
  results        JSON NULL,                                -- [{qid, correct, answer, right}] sau khi chấm
  result_seen_at DATETIME NULL,                            -- lúc người học mở xem kết quả (NULL = chưa xem)
  KEY ix_ta_user_lesson (user_id, lesson_id),
  KEY ix_ta_user_mock   (user_id, mock_test_id),
  KEY ix_ta_open        (submitted_at, deadline_at),
  CONSTRAINT fk_ta_user   FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
  CONSTRAINT fk_ta_lesson FOREIGN KEY (lesson_id)    REFERENCES lessons(id)    ON DELETE CASCADE,
  CONSTRAINT fk_ta_mock   FOREIGN KEY (mock_test_id) REFERENCES mock_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS char_stats (
  user_id      BIGINT UNSIGNED NOT NULL,
  char_id      INT UNSIGNED    NOT NULL,
  attempts     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  mistakes     SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- tổng số nét sai
  passes       SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- số lần viết đúng ở chế độ Tự viết
  last_at      DATETIME NOT NULL,
  PRIMARY KEY (user_id, char_id),
  CONSTRAINT fk_cs_user FOREIGN KEY (user_id) REFERENCES users(id)      ON DELETE CASCADE,
  CONSTRAINT fk_cs_char FOREIGN KEY (char_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Phiên truy cập và thống kê
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visits (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  auth_session_id BIGINT UNSIGNED NULL,
  started_at      DATETIME NOT NULL,
  last_seen_at    DATETIME NOT NULL,
  ended_at        DATETIME NULL,                           -- NULL = đang mở
  duration_sec    INT UNSIGNED NOT NULL DEFAULT 0,
  device_type     ENUM('phone','tablet','desktop') NOT NULL,
  os              VARCHAR(40) NOT NULL DEFAULT '',
  browser         VARCHAR(40) NOT NULL DEFAULT '',
  parts           SET('lt','bt','kt','lv','ht','np') NOT NULL DEFAULT '',
  activity        VARCHAR(160) NOT NULL DEFAULT '',        -- 'Đang lật HSK 2 · Bài 6'
  stats           JSON NULL,                               -- xem 03-database.md §4.3
  KEY ix_v_user_started (user_id, started_at),
  KEY ix_v_started (started_at),
  KEY ix_v_open (ended_at, last_seen_at),
  CONSTRAINT fk_v_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_samples (
  ts           DATETIME NOT NULL PRIMARY KEY,              -- làm tròn 5 phút
  online       SMALLINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stats_daily (
  d              DATE NOT NULL PRIMARY KEY,
  new_users      INT UNSIGNED NOT NULL DEFAULT 0,
  new_google     INT UNSIGNED NOT NULL DEFAULT 0,
  new_email      INT UNSIGNED NOT NULL DEFAULT 0,
  active_users   INT UNSIGNED NOT NULL DEFAULT 0,
  visits         INT UNSIGNED NOT NULL DEFAULT 0,
  study_sec      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  phone_sec      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tablet_sec     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  desktop_sec    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  lessons_done   INT UNSIGNED NOT NULL DEFAULT 0,
  aff_views      INT UNSIGNED NOT NULL DEFAULT 0,
  aff_clicks     INT UNSIGNED NOT NULL DEFAULT 0,
  peak_online    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  peak_online_at TIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kinh phí: link Shopee
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS affiliate_links (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(160) NOT NULL,
  url          VARCHAR(500) NOT NULL,
  image_path   VARCHAR(255) NULL,
  place_desk   TINYINT(1)   NOT NULL DEFAULT 0,            -- Bàn học
  place_done   TINYINT(1)   NOT NULL DEFAULT 0,            -- Màn hoàn thành bài
  starts_on    DATE         NULL,
  ends_on      DATE         NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_events (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  link_id      INT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED NULL,
  kind         ENUM('view','click') NOT NULL,
  place        ENUM('desk','done') NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_ae_link (link_id, created_at),
  KEY ix_ae_time (created_at),
  CONSTRAINT fk_ae_link FOREIGN KEY (link_id) REFERENCES affiliate_links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_daily (
  link_id      INT UNSIGNED NOT NULL,
  d            DATE NOT NULL,
  views        INT UNSIGNED NOT NULL DEFAULT 0,
  clicks       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (link_id, d),
  CONSTRAINT fk_ad_link FOREIGN KEY (link_id) REFERENCES affiliate_links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Thông báo và góp ý
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(160) NOT NULL,
  body            TEXT         NOT NULL,
  kind            ENUM('banner','modal','email') NOT NULL,          -- Dải trên cùng | Hộp nổi | Email
  audience        ENUM('all','level','new7d','user') NOT NULL DEFAULT 'all',
  audience_level  TINYINT UNSIGNED NULL,
  target_user_id  BIGINT UNSIGNED NULL,                             -- gửi riêng 1 người (từ Chi tiết người dùng)
  starts_at       DATETIME NULL,
  ends_at         DATETIME NULL,
  cta_text        VARCHAR(40)  NULL,                                -- “Thử ngay”
  cta_route       VARCHAR(160) NULL,                                -- “/learn/tests/hsk/2”
  status          ENUM('draft','scheduled','published','ended') NOT NULL DEFAULT 'draft',
  is_auto_welcome TINYINT(1) NOT NULL DEFAULT 0,                    -- email chào mừng tự động
  views           INT UNSIGNED NOT NULL DEFAULT 0,
  clicks          INT UNSIGNED NOT NULL DEFAULT 0,
  emails_sent     INT UNSIGNED NOT NULL DEFAULT 0,
  created_by      BIGINT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_an_status (status, starts_at),
  CONSTRAINT fk_an_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_reads (
  announcement_id INT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  seen_at         DATETIME NOT NULL,
  dismissed_at    DATETIME NULL,
  clicked_at      DATETIME NULL,
  PRIMARY KEY (announcement_id, user_id),
  CONSTRAINT fk_ar_an   FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_user FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NULL,
  category        ENUM('content_error','pronunciation','display','feature','content_suggestion','other') NOT NULL,
  message         TEXT NOT NULL,
  screenshot_path VARCHAR(255) NULL,
  ctx             JSON NULL,                       -- {part, levelId, lessonId, lessonNo, questionId, questionNo, route, device, os, browser}
  status          ENUM('new','doing','done') NOT NULL DEFAULT 'new',
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  assignee_id     BIGINT UNSIGNED NULL,
  internal_note   TEXT NULL,
  reply           TEXT NULL,
  replied_at      DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_fb_status (status, created_at),
  KEY ix_fb_read (is_read, created_at),
  CONSTRAINT fk_fb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
