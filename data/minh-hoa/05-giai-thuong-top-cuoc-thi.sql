-- Tạo lịch sử 5 giải minh họa khớp 5 tên đầu của bảng Cuộc thi.
-- Chọn đúng database Zìkǎ trong phpMyAdmin rồi import với bảng mã UTF-8.
-- Cần migration 022–024 và cần có ít nhất 5 người bật is_visible + show_contest.
-- Chạy lại an toàn: thay đúng nhóm giải do file này quản lý, không nhân đôi.

-- Một số hosting đặt connection là utf8mb4_general_ci trong khi bảng dùng
-- utf8mb4_unicode_ci; ép cùng collation để so sánh biến chuỗi không lỗi #1267.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @zika_reward_now = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR);
SET @zika_reward_month = '2026-11';
SET @zika_reward_date = '2026-11-15';
SET @zika_reward_title = COALESCE(
  (SELECT title FROM contests
    WHERE starts_at <= @zika_reward_now AND ends_at > @zika_reward_now
    ORDER BY starts_at, id LIMIT 1),
  (SELECT title FROM contests
    WHERE ends_at <= @zika_reward_now
    ORDER BY ends_at DESC, id DESC LIMIT 1),
  'Cuộc thi giải đề tháng 11/2026'
);

DROP TEMPORARY TABLE IF EXISTS tmp_zika_reward_top5;
CREATE TEMPORARY TABLE tmp_zika_reward_top5 (
  ranking TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  learner_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_tmp_zika_reward_learner (learner_id)
) ENGINE=InnoDB;

-- Phải giống khóa trộn tên trong ContestService::withDemoRanking().
INSERT INTO tmp_zika_reward_top5 (learner_id)
SELECT id
FROM demo_learners
WHERE is_visible = 1 AND show_contest = 1
ORDER BY CRC32(CONCAT('cuoc-thi:', name)), id
LIMIT 5;

-- Thay đúng nhóm 5 giải của cuộc thi/tháng/ngày này khi chạy lại.
DELETE FROM demo_rewards
WHERE contest_name = @zika_reward_title
  AND reward_month = @zika_reward_month
  AND contest_date = @zika_reward_date
  AND ranking BETWEEN 1 AND 5;

INSERT INTO demo_rewards
  (learner_id, contest_name, reward_month, contest_date, ranking,
   prize_name, amount, reward_status, is_visible, created_at, updated_at)
SELECT
  t.learner_id,
  @zika_reward_title,
  @zika_reward_month,
  @zika_reward_date,
  t.ranking,
  CASE t.ranking
    WHEN 1 THEN 'Giải Nhất'
    WHEN 2 THEN 'Giải Nhì'
    WHEN 3 THEN 'Giải Ba'
    WHEN 4 THEN 'Giải Tư'
    ELSE 'Giải Năm'
  END,
  CASE t.ranking
    WHEN 1 THEN 1340000  -- 50%
    WHEN 2 THEN  670000  -- 25%
    WHEN 3 THEN  402000  -- 15%
    WHEN 4 THEN  134000  --  5%
    ELSE          134000 --  5%
  END,
  'da-trao',
  1,
  @zika_reward_now,
  @zika_reward_now
FROM tmp_zika_reward_top5 t
ORDER BY t.ranking;

SET @zika_reward_inserted = ROW_COUNT();

-- Đồng bộ Quỹ tháng và cơ cấu 5 giải: tổng phần trăm = 100%.
INSERT INTO site_settings (k, v) VALUES
  ('fund', JSON_OBJECT(
    'amount', 2680000,
    'month', '2026-11',
    'updated_on', '2026-11-15'
  )),
  ('prizes', JSON_OBJECT('rows', JSON_ARRAY(
    JSON_OBJECT('label', 'Giải Nhất', 'percent', 50),
    JSON_OBJECT('label', 'Giải Nhì',  'percent', 25),
    JSON_OBJECT('label', 'Giải Ba',   'percent', 15),
    JSON_OBJECT('label', 'Giải Tư',   'percent', 5),
    JSON_OBJECT('label', 'Giải Năm',  'percent', 5)
  )))
ON DUPLICATE KEY UPDATE v = VALUES(v);

SELECT
  @zika_reward_inserted AS so_giai_da_tao,
  @zika_reward_title AS ten_cuoc_thi,
  SUM(r.amount) AS tong_tien_thuong,
  GROUP_CONCAT(CONCAT(r.ranking, ': ', l.name, ' — ', r.amount, 'đ')
    ORDER BY r.ranking SEPARATOR '\n') AS nam_nguoi_dat_giai
FROM demo_rewards r
JOIN demo_learners l ON l.id = r.learner_id
WHERE r.contest_name = @zika_reward_title
  AND r.reward_month = @zika_reward_month
  AND r.contest_date = @zika_reward_date
  AND r.ranking BETWEEN 1 AND 5;

DROP TEMPORARY TABLE tmp_zika_reward_top5;
