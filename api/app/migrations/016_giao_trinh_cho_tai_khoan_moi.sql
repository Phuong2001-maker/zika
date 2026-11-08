-- =====================================================================
-- Zìkǎ · 016 — Giáo trình mặc định chọn được, mặc định là Hán ngữ 6 tập
--
-- Học viên chưa được phân quyền (users.curriculum_id NULL), cả tài khoản cũ lẫn mới đăng ký, học giáo trình
-- bật cờ is_default. Giáo trình số 1 giữ dữ liệu HSK cũ, đổi tên thành "Giáo trình HSK 3.0" và chỉ ai được
-- admin gán mới học. Không ghi id vào users nên đổi mặc định ở form giáo trình là mọi học viên chưa phân quyền
-- đổi theo, không phải sửa từng người.
-- Tìm Hán ngữ theo tên như migration 015, vì id trên server khác local. Server chạy migration này lúc chưa có
-- Hán ngữ nên cờ nằm ở giáo trình số 1; 02-han-ngu.sql tự chuyển cờ sang Hán ngữ.
-- Lượt ôn dở lưu theo (người, cấp), còn trỏ vào câu HSK, nên bỏ đi như khi admin gán giáo trình khác.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE curricula ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER level_label;

UPDATE curricula SET name = 'Giáo trình HSK 3.0' WHERE id = 1 AND name = 'Giáo trình HSK chuẩn';

UPDATE curricula SET description = 'Giáo trình HSK: chỉ học viên được phân quyền mới học bộ này.'
 WHERE id = 1 AND description = 'Giáo trình mặc định: học viên chưa được gán sẽ học theo bộ này.';

SET @zika_hn = (SELECT id FROM curricula WHERE name = 'Hán ngữ 6 tập');

UPDATE curricula SET is_default = (id = COALESCE(@zika_hn, 1));

DELETE FROM review_progress WHERE @zika_hn IS NOT NULL AND user_id IN (SELECT id FROM users WHERE curriculum_id IS NULL AND role = 'learner');
