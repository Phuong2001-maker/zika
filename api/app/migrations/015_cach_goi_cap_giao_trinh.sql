-- =====================================================================
-- Zìkǎ · 015 — Mỗi giáo trình tự đặt cách gọi cấp
--
-- Cấp 1–6 là khung chung của mọi giáo trình, nhưng "HSK 1" sai với giáo trình theo quyển sách:
-- học viên Hán ngữ đang học quyển 1 chứ không phải cấp HSK 1.
-- Lưu theo giáo trình chứ không so id hay tên ở web, vì id trên server khác local và tên đổi được.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE curricula ADD COLUMN level_label VARCHAR(20) NOT NULL DEFAULT 'HSK' AFTER color;

UPDATE curricula SET level_label = 'Quyển' WHERE name = 'Hán ngữ 6 tập';
