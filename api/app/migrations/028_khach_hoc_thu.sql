-- =====================================================================
-- Zìkǎ · 028 — Khách học thử làm Kiểm tra và Thi thử HSK 3.0 cấp 1 (11/11/2026)
--
-- Khách (chưa đăng nhập) học thử trọn cấp 1 ở mọi phần trong Chuyển nhanh, kể cả hai phần chấm bài ở server.
-- Lượt của khách không có user_id mà gắn với guest_key = sha256 của cookie HttpOnly zk_khach (Core/Khach.php),
-- nên tải lại trang vẫn làm tiếp và xem lại được kết quả. Lượt khách không ghi tiến độ, không vào cuộc thi hay bảng
-- xếp hạng; MaintenanceService xoá sau 30 ngày (bằng tuổi cookie).
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE test_attempts
  MODIFY user_id BIGINT UNSIGNED NULL,
  ADD COLUMN guest_key CHAR(64) NULL AFTER user_id,
  ADD KEY ix_ta_guest (guest_key, submitted_at);
