-- =====================================================================
-- Zìkǎ · 007 — Vị trí link Shopee: bé túi (icon) và hộp Quỹ tháng
--
-- Hai vị trí cũ (Bàn học, Màn tổng kết) chưa từng có màn người học nào dùng, nên thay hẳn.
--   place_icon: bé túi di chuyển, chỉ một link; luật "một link" giữ ở OpsController::saveLink.
--   place_fund: hộp Quỹ tháng, nhiều link.
-- Link đang gắn vị trí cũ chuyển sang Quỹ tháng để không mất. Icon không tự gán: admin tự chọn link.
-- Bỏ starts_on vì form quản trị không còn ô "Hiện từ ngày"; giữ lại sẽ có link hẹn ngày mà không sửa được.
-- Sự kiện cũ vẫn mang place 'desk'/'done' nên giữ hai giá trị đó trong ENUM.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE affiliate_links
  ADD COLUMN place_icon TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path,
  ADD COLUMN place_fund TINYINT(1) NOT NULL DEFAULT 0 AFTER place_icon;

UPDATE affiliate_links SET place_fund = IF(place_desk = 1 OR place_done = 1, 1, 0);

ALTER TABLE affiliate_links
  DROP COLUMN place_desk,
  DROP COLUMN place_done,
  DROP COLUMN starts_on;

ALTER TABLE affiliate_events
  MODIFY place ENUM('desk','done','icon','fund') NOT NULL;
