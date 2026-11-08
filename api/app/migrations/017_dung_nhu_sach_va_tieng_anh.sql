-- =====================================================================
-- Zìkǎ · 017 — Hiện đúng như sách in và thêm phần tiếng Anh sách in
--
-- 1. Sách in lại một từ ở bài sau (thường với nghĩa hoặc cách đọc khác: 倒, 了, 还, 好 hào/hǎo), nhưng khoá duy nhất
--    (giáo trình, cấp, chữ Hán) bắt bỏ từ đó ở bài sau. Trùng giờ chỉ xét trong một bài, theo chữ Hán + pinyin, và kiểm ở PHP:
--    cột pinyin dùng collation bỏ dấu nên hǎo và hào bị coi là một nếu để DB tự chặn.
-- 2. Cột tiếng Anh cho từ (cột English của bảng 生词) và ngữ pháp (tiêu đề, công thức, giải thích, lưu ý tiếng Anh sách in).
-- 3. Tên mục và công thức ngữ pháp dài hơn 120/160 ký tự bị cắt; nới cho đủ chữ sách in (bảng phiên âm quyển 1
--    gộp lại tới hơn 600 ký tự công thức).
-- 4. Cách đọc một chữ trong mục 写汉字 tuỳ bài (行 háng trong 银行), còn bảng characters dùng chung mọi giáo trình:
--    lưu pinyin theo bài ở lesson_chars, trống thì dùng của characters như cũ.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE words ADD KEY ix_words_cur_level_hanzi (curriculum_id, level_id, hanzi);

ALTER TABLE words DROP INDEX uq_words_curriculum_hanzi;

ALTER TABLE words ADD COLUMN meaning_en VARCHAR(255) NULL AFTER meaning_vi;

ALTER TABLE grammar_points
  MODIFY title VARCHAR(255) NOT NULL,
  MODIFY subtitle VARCHAR(2000) NOT NULL DEFAULT '',
  ADD COLUMN title_en VARCHAR(255) NULL AFTER subtitle,
  ADD COLUMN subtitle_en VARCHAR(2000) NULL AFTER title_en,
  ADD COLUMN explain_en TEXT NULL AFTER explain_vi,
  ADD COLUMN note_en TEXT NULL AFTER note_vi;

ALTER TABLE lesson_chars ADD COLUMN pinyin VARCHAR(30) NULL AFTER source_word_id;
