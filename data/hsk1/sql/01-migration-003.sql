-- Zìkǎ · migration 003 cho server chưa chạy — CHẠY LẠI BAO NHIÊU LẦN CŨNG ĐƯỢC.
-- Mỗi bước tự soi information_schema trước, đã làm rồi thì bỏ qua (DO 0) thay vì báo lỗi.
-- Cần vậy vì phpMyAdmin dừng ở câu lỗi đầu tiên, một lần vấp là các câu sau không chạy.
SET NAMES utf8mb4;

-- 1. Thêm cột sort_order
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dialogues'
                 AND COLUMN_NAME = 'sort_order') = 0,
  'ALTER TABLE dialogues ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER lesson_id',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Thêm index mới TRƯỚC khi bỏ khoá cũ: khoá ngoại fk_dlg_lesson đang dựa vào
--    uq_dlg_lesson, bỏ trước thì báo lỗi 1553.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dialogues'
                 AND INDEX_NAME = 'ix_dlg_lesson') = 0,
  'ALTER TABLE dialogues ADD KEY ix_dlg_lesson (lesson_id, sort_order)',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Bỏ khoá UNIQUE cũ (cái ép mỗi bài chỉ được 1 hội thoại)
SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dialogues'
                 AND INDEX_NAME = 'uq_dlg_lesson') > 0,
  'ALTER TABLE dialogues DROP INDEX uq_dlg_lesson',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Bỏ bảng câu hỏi hiểu bài (phần Kiểm tra đã làm đúng việc đó)
DROP TABLE IF EXISTS dialogue_questions;

-- 5. Ghi nhận để install.php --migrate không chạy lại
INSERT IGNORE INTO schema_migrations (filename) VALUES ('003_dialogue_nhieu_bai_khoa.sql');

-- Kiểm lại: phải ra 1 · 2 · 0 · 0 · 1
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogues' AND COLUMN_NAME = 'sort_order')   AS co_sort_order,
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogues' AND INDEX_NAME = 'ix_dlg_lesson') AS co_index_moi,
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogues' AND INDEX_NAME = 'uq_dlg_lesson') AS con_unique_cu,
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogue_questions')                        AS con_bang_cu,
  (SELECT COUNT(*) FROM schema_migrations
     WHERE filename = '003_dialogue_nhieu_bai_khoa.sql')           AS da_ghi_nhan;
