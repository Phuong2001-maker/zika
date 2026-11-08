-- =====================================================================
-- Zìkǎ · 019 — Điểm mở bài giáo trình
--
-- Học viên không đọc được trang sách, đáp án, file nghe của bài chưa mở. Mở bài bằng điểm: lần đầu bấm bé túi
-- (hoặc link Shopee) được 3 điểm, từ hôm sau mỗi ngày bấm được 1 điểm; điểm chưa dùng giữ lại, ngày không bấm thì
-- không bù. Một điểm mở một bài, lần lượt theo thứ tự. Không có phần bán sách (người dùng bỏ 08/11/2026).
--
-- book_points là sổ cộng/trừ, số điểm = tổng delta. `khoa` duy nhất theo người nên bấm nhiều lần trong ngày hay
-- gửi trùng yêu cầu mở bài cũng chỉ ghi một dòng — không cần khoá ứng dụng để chống cộng trùng.
-- Mã sách (hn1…hn6, hsk1…hsk3) lấy theo sach.json trong uploads, không có bảng sách riêng.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS book_points (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  delta       SMALLINT        NOT NULL,
  kind        ENUM('first','daily','unlock','admin') NOT NULL,
  khoa        VARCHAR(40)     NOT NULL,              -- first · daily:2026-11-08 · mo:hn1:5 · admin:<ngẫu nhiên> · thu:<ngẫu nhiên>
  note        VARCHAR(200)    NULL,
  by_user_id  BIGINT UNSIGNED NULL,                  -- quản trị cộng/trừ tay
  created_at  DATETIME        NOT NULL,
  UNIQUE KEY uq_book_points_khoa (user_id, khoa),
  KEY ix_book_points_time (user_id, created_at),
  CONSTRAINT fk_book_points_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bài đã mở bằng điểm: mở rồi là mở mãi.
CREATE TABLE IF NOT EXISTS book_unlocks (
  user_id     BIGINT UNSIGNED   NOT NULL,
  ma          VARCHAR(8)        NOT NULL,
  bai         SMALLINT UNSIGNED NOT NULL,
  created_at  DATETIME          NOT NULL,
  PRIMARY KEY (user_id, ma, bai),
  CONSTRAINT fk_book_unlocks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
