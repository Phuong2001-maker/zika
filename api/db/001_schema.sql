-- Zika — schema MariaDB 10.6+ / MySQL 8
-- Chay: mariadb -u root -p < 001_schema.sql
-- Ghi chu collation: dung utf8mb4_unicode_ci (ton tai tren ca MySQL 8 va MariaDB).
--   KHONG dung utf8mb4_0900_ai_ci vi MariaDB khong co collation do.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS zika_app
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zika_app;

-- ===================== Người dùng & phiên =====================

DROP TABLE IF EXISTS user_word_progress;
DROP TABLE IF EXISTS user_part_progress;
DROP TABLE IF EXISTS test_attempts;
DROP TABLE IF EXISTS word_examples;
DROP TABLE IF EXISTS words;
DROP TABLE IF EXISTS hanzi_strokes;
DROP TABLE IF EXISTS hanzi;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS dialogue_lines;
DROP TABLE IF EXISTS dialogue_speakers;
DROP TABLE IF EXISTS dialogues;
DROP TABLE IF EXISTS grammar_forms;
DROP TABLE IF EXISTS grammar_blocks;
DROP TABLE IF EXISTS grammar_points;
DROP TABLE IF EXISTS lesson_parts;
DROP TABLE IF EXISTS mock_exams;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS levels;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS study_sessions;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS auth_tokens;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS news;
DROP TABLE IF EXISTS support_links;
DROP TABLE IF EXISTS settings;

CREATE TABLE users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(120)  NOT NULL,
  email           VARCHAR(190)  NOT NULL,
  pass_hash       VARCHAR(255)  NULL,
  provider        ENUM('email','google') NOT NULL DEFAULT 'email',
  google_sub      VARCHAR(64)   NULL,
  role            ENUM('user','editor','admin') NOT NULL DEFAULT 'user',
  status          ENUM('active','locked')       NOT NULL DEFAULT 'active',
  level_hint      ENUM('new','hsk12','hsk34','hsk5') NOT NULL DEFAULT 'new',
  daily_goal_min  SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  email_verified_at DATETIME NULL,
  total_seconds   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_gsub  (google_sub),
  KEY ix_users_seen (last_seen_at),
  KEY ix_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_settings (
  user_id       INT UNSIGNED NOT NULL,
  voice         VARCHAR(20)  NOT NULL DEFAULT 'xiaoxiao',
  speed         DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  autoplay      TINYINT(1)   NOT NULL DEFAULT 1,
  front_side    ENUM('vi','hz','py') NOT NULL DEFAULT 'vi',
  shuffle       TINYINT(1)   NOT NULL DEFAULT 0,
  show_examples TINYINT(1)   NOT NULL DEFAULT 1,
  hz_font       ENUM('sans','serif','brush') NOT NULL DEFAULT 'sans',
  hz_size       ENUM('sm','md','lg')         NOT NULL DEFAULT 'md',
  pinyin_color  TINYINT(1)   NOT NULL DEFAULT 1,
  show_hv       TINYINT(1)   NOT NULL DEFAULT 1,
  theme         ENUM('light','dark','system') NOT NULL DEFAULT 'light',
  remind_at     VARCHAR(5)   NULL,
  test_sound    TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_set_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  token_hash   CHAR(64)     NOT NULL,
  device       VARCHAR(40)  NOT NULL DEFAULT '',
  platform     VARCHAR(30)  NOT NULL DEFAULT '',
  is_phone     TINYINT(1)   NOT NULL DEFAULT 0,
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tok (token_hash),
  KEY ix_tok_user (user_id),
  CONSTRAINT fk_tok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pr (token_hash),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_sessions (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  started_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_ping_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at     DATETIME NULL,
  seconds      INT UNSIGNED NOT NULL DEFAULT 0,
  is_phone     TINYINT(1)   NOT NULL DEFAULT 0,
  platform     VARCHAR(30)  NOT NULL DEFAULT '',
  parts        VARCHAR(80)  NOT NULL DEFAULT '',
  last_part    VARCHAR(10)  NOT NULL DEFAULT '',
  last_lesson_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_ss_user (user_id, started_at),
  KEY ix_ss_live (ended_at, last_ping_at),
  CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================== Nội dung học =====================

CREATE TABLE levels (
  hsk         TINYINT UNSIGNED NOT NULL,
  sample      VARCHAR(60) NOT NULL DEFAULT '',
  total_words INT UNSIGNED NOT NULL DEFAULT 0,
  visible     TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (hsk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lessons (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hsk     TINYINT UNSIGNED NOT NULL,
  no      SMALLINT UNSIGNED NOT NULL,
  title   VARCHAR(120) NOT NULL,
  sample  VARCHAR(60)  NOT NULL DEFAULT '',
  status  ENUM('draft','published') NOT NULL DEFAULT 'published',
  sort    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lesson (hsk, no),
  KEY ix_lesson_hsk (hsk, sort),
  CONSTRAINT fk_lesson_level FOREIGN KEY (hsk) REFERENCES levels(hsk) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lesson_parts (
  lesson_id INT UNSIGNED NOT NULL,
  part      ENUM('study','bt','kt','lv','ht','np') NOT NULL,
  state     ENUM('missing','draft','ready') NOT NULL DEFAULT 'missing',
  PRIMARY KEY (lesson_id, part),
  CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE words (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_id  INT UNSIGNED NOT NULL,
  hz         VARCHAR(20)  NOT NULL,
  pinyin     VARCHAR(60)  NOT NULL,
  hv         VARCHAR(60)  NOT NULL DEFAULT '',
  meaning    VARCHAR(190) NOT NULL,
  word_class VARCHAR(30)  NOT NULL DEFAULT '',
  sort       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_word_lesson (lesson_id, sort),
  CONSTRAINT fk_word_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE word_examples (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  word_id INT UNSIGNED NOT NULL,
  pre     VARCHAR(190) NOT NULL DEFAULT '',
  target  VARCHAR(40)  NOT NULL DEFAULT '',
  post    VARCHAR(190) NOT NULL DEFAULT '',
  pinyin  VARCHAR(255) NOT NULL DEFAULT '',
  meaning VARCHAR(255) NOT NULL DEFAULT '',
  sort    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_ex_word (word_id, sort),
  CONSTRAINT fk_ex_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hanzi (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_id    INT UNSIGNED NOT NULL,
  hz           VARCHAR(4)   NOT NULL,
  pinyin       VARCHAR(20)  NOT NULL,
  hv           VARCHAR(40)  NOT NULL DEFAULT '',
  meaning      VARCHAR(120) NOT NULL DEFAULT '',
  strokes      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  struct       VARCHAR(120) NOT NULL DEFAULT '',
  word_hz      VARCHAR(20)  NOT NULL DEFAULT '',
  word_meaning VARCHAR(120) NOT NULL DEFAULT '',
  sort         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_hz_lesson (lesson_id, sort),
  CONSTRAINT fk_hz_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hanzi_strokes (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hanzi_id INT UNSIGNED NOT NULL,
  ord      TINYINT UNSIGNED NOT NULL,
  path_d   TEXT NOT NULL,
  name     VARCHAR(40) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY ix_stroke_hz (hanzi_id, ord),
  CONSTRAINT fk_stroke_hz FOREIGN KEY (hanzi_id) REFERENCES hanzi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cau hoi dung cho ca Bai tap (bt) va Kiem tra (kt).
-- data   : de bai, tuy theo kind (JSON)
-- answer : dap an (JSON) — API khong gui cot nay khi nguoi hoc dang lam bai kiem tra
CREATE TABLE questions (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_id INT UNSIGNED NOT NULL,
  bank      ENUM('bt','kt') NOT NULL,
  kind      VARCHAR(16) NOT NULL,
  prompt    VARCHAR(255) NOT NULL DEFAULT '',
  data      JSON NOT NULL,
  answer    JSON NOT NULL,
  `explain` TEXT NULL,
  score     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sort      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_q_lesson (lesson_id, bank, sort),
  CONSTRAINT fk_q_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dialogues (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_id INT UNSIGNED NOT NULL,
  title     VARCHAR(120) NOT NULL DEFAULT '',
  quiz_q    VARCHAR(255) NOT NULL DEFAULT '',
  quiz_opts JSON NULL,
  quiz_ans  TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_dlg_lesson (lesson_id),
  CONSTRAINT fk_dlg_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dialogue_speakers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dialogue_id INT UNSIGNED NOT NULL,
  name        VARCHAR(40) NOT NULL,
  ava         VARCHAR(4)  NOT NULL DEFAULT '',
  color       CHAR(7)     NOT NULL DEFAULT '#DCEBFF',
  role        VARCHAR(40) NOT NULL DEFAULT '',
  sort        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_sp_dlg (dialogue_id, sort),
  CONSTRAINT fk_sp_dlg FOREIGN KEY (dialogue_id) REFERENCES dialogues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dialogue_lines (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dialogue_id INT UNSIGNED NOT NULL,
  speaker_id  INT UNSIGNED NOT NULL,
  pre         VARCHAR(190) NOT NULL DEFAULT '',
  target      VARCHAR(40)  NOT NULL DEFAULT '',
  post        VARCHAR(190) NOT NULL DEFAULT '',
  pinyin      VARCHAR(255) NOT NULL DEFAULT '',
  meaning     VARCHAR(255) NOT NULL DEFAULT '',
  sort        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_ln_dlg (dialogue_id, sort),
  CONSTRAINT fk_ln_dlg FOREIGN KEY (dialogue_id) REFERENCES dialogues(id) ON DELETE CASCADE,
  CONSTRAINT fk_ln_sp  FOREIGN KEY (speaker_id)  REFERENCES dialogue_speakers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grammar_points (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_id    INT UNSIGNED NOT NULL,
  title        VARCHAR(80)  NOT NULL,
  sub          VARCHAR(120) NOT NULL DEFAULT '',
  explain      TEXT NULL,
  note         TEXT NULL,
  answer       VARCHAR(120) NOT NULL DEFAULT '',
  tiles        JSON NULL,
  practice_vi  VARCHAR(190) NOT NULL DEFAULT '',
  sort         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_gp_lesson (lesson_id, sort),
  CONSTRAINT fk_gp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grammar_blocks (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  point_id INT UNSIGNED NOT NULL,
  label    VARCHAR(40) NOT NULL,
  hz       VARCHAR(40) NOT NULL DEFAULT '',
  color    CHAR(7)     NOT NULL DEFAULT '#DCEBFF',
  sort     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_gb_point (point_id, sort),
  CONSTRAINT fk_gb_point FOREIGN KEY (point_id) REFERENCES grammar_points(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grammar_forms (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  point_id INT UNSIGNED NOT NULL,
  kind     VARCHAR(40)  NOT NULL DEFAULT '',
  formula  VARCHAR(120) NOT NULL DEFAULT '',
  pre      VARCHAR(120) NOT NULL DEFAULT '',
  target   VARCHAR(40)  NOT NULL DEFAULT '',
  post     VARCHAR(120) NOT NULL DEFAULT '',
  pinyin   VARCHAR(190) NOT NULL DEFAULT '',
  meaning  VARCHAR(190) NOT NULL DEFAULT '',
  sort     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_gf_point (point_id, sort),
  CONSTRAINT fk_gf_point FOREIGN KEY (point_id) REFERENCES grammar_points(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mock_exams (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hsk       TINYINT UNSIGNED NOT NULL,
  title     VARCHAR(120) NOT NULL,
  q_count   SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  minutes   SMALLINT UNSIGNED NOT NULL DEFAULT 55,
  pass_score SMALLINT UNSIGNED NOT NULL DEFAULT 120,
  total_score SMALLINT UNSIGNED NOT NULL DEFAULT 200,
  variants  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status    ENUM('draft','published') NOT NULL DEFAULT 'published',
  PRIMARY KEY (id),
  KEY ix_me_hsk (hsk),
  CONSTRAINT fk_me_level FOREIGN KEY (hsk) REFERENCES levels(hsk) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================== Tiến độ học =====================

CREATE TABLE user_word_progress (
  user_id    INT UNSIGNED NOT NULL,
  word_id    INT UNSIGNED NOT NULL,
  state      ENUM('new','known','not_yet') NOT NULL DEFAULT 'new',
  reps       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, word_id),
  KEY ix_uwp_word (word_id),
  CONSTRAINT fk_uwp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uwp_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_part_progress (
  user_id    INT UNSIGNED NOT NULL,
  lesson_id  INT UNSIGNED NOT NULL,
  part       ENUM('study','bt','kt','lv','ht','np') NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  known      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_score SMALLINT NULL,
  best_score SMALLINT NULL,
  done_at    DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id, part),
  KEY ix_upp_user (user_id, updated_at),
  CONSTRAINT fk_upp_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_upp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_attempts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  lesson_id   INT UNSIGNED NULL,
  exam_id     INT UNSIGNED NULL,
  bank        ENUM('bt','kt','mock') NOT NULL DEFAULT 'kt',
  score       SMALLINT NOT NULL DEFAULT 0,
  total       SMALLINT NOT NULL DEFAULT 0,
  duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
  detail      JSON NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ta_user (user_id, created_at),
  KEY ix_ta_lesson (lesson_id),
  CONSTRAINT fk_ta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================== Vận hành =====================

CREATE TABLE news (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title     VARCHAR(190) NOT NULL,
  body      TEXT NULL,
  display   ENUM('banner','modal','email') NOT NULL DEFAULT 'banner',
  audience  ENUM('all','hsk1','hsk2','new7') NOT NULL DEFAULT 'all',
  start_at  DATETIME NULL,
  end_at    DATETIME NULL,
  cta_label VARCHAR(60) NOT NULL DEFAULT '',
  cta_url   VARCHAR(255) NOT NULL DEFAULT '',
  status    ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_news_live (status, start_at, end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedback (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  kind        VARCHAR(40) NOT NULL DEFAULT 'noi_dung',
  body        TEXT NOT NULL,
  ctx_part    VARCHAR(10)  NOT NULL DEFAULT '',
  ctx_lesson_id INT UNSIGNED NULL,
  ctx_question SMALLINT UNSIGNED NULL,
  device      VARCHAR(60) NOT NULL DEFAULT '',
  screenshot  VARCHAR(255) NOT NULL DEFAULT '',
  status      ENUM('new','doing','done') NOT NULL DEFAULT 'new',
  assignee    VARCHAR(80) NOT NULL DEFAULT '',
  note        TEXT NULL,
  reply       TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_fb_status (status, created_at),
  CONSTRAINT fk_fb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_links (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(190) NOT NULL,
  url        VARCHAR(500) NOT NULL,
  image      VARCHAR(255) NOT NULL DEFAULT '',
  placements VARCHAR(120) NOT NULL DEFAULT '',
  views      INT UNSIGNED NOT NULL DEFAULT 0,
  clicks     INT UNSIGNED NOT NULL DEFAULT 0,
  active     TINYINT(1) NOT NULL DEFAULT 0,
  start_at   DATE NULL,
  end_at     DATE NULL,
  sort       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_sl_active (active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  k VARCHAR(60) NOT NULL,
  v TEXT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
