-- =====================================================================
-- Zìkǎ · 003 — Hội thoại: mỗi bài có nhiều bài khoá
--
-- Giáo trình HSK chia mỗi bài thành 2–3 bài khoá (课文一, 课文二…), mỗi bài khoá là một cảnh
-- riêng với nhân vật riêng. Khoá UNIQUE cũ ép cả bài chỉ được một hội thoại nên phải dồn hết
-- vào một khối, người học đọc không biết chỗ nào sang cảnh khác.
--
-- Câu hỏi hiểu bài bỏ khỏi phần Hội thoại: phần Kiểm tra đã làm đúng việc hỏi bài, để ở đây
-- chỉ khiến màn đọc hội thoại lẫn lộn hai việc.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE dialogues
  ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER lesson_id;

-- Thêm khoá mới TRƯỚC khi bỏ khoá cũ: khoá ngoại fk_dlg_lesson đang dựa vào uq_dlg_lesson,
-- bỏ trước thì MariaDB từ chối với lỗi 1553.
ALTER TABLE dialogues
  ADD KEY ix_dlg_lesson (lesson_id, sort_order);

ALTER TABLE dialogues
  DROP INDEX uq_dlg_lesson;

DROP TABLE IF EXISTS dialogue_questions;
