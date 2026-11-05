-- =====================================================================
-- Zìkǎ · 008 — Bỏ ngày hết hạn của link Shopee
--
-- Form quản trị không còn ô "Đến ngày": link nào không dùng nữa thì tắt hoặc xoá.
-- Tắt trước các link đã quá hạn, không thì bỏ cột xong chúng sẽ hiện lại cho người học.
-- =====================================================================
SET NAMES utf8mb4;

UPDATE affiliate_links SET is_active = 0 WHERE ends_on IS NOT NULL AND ends_on < CURDATE();

ALTER TABLE affiliate_links DROP COLUMN ends_on;
