-- =====================================================================
-- Zìkǎ · 005 — Phân tích chữ ở màn Luyện viết
--
-- Người học nhớ chữ nhanh hơn khi biết chữ ghép từ đâu: bộ thủ gợi nghĩa gì, phần nào gợi âm,
-- và vài chữ khác cùng bộ. Dữ liệu sinh từ từ điển chữ (Unihan + Make Me a Hanzi, xem
-- data/nap-sach/phan_tich_chu.py), nạp qua PUT /admin/characters/analysis.
--   radical_no       số bộ Khang Hy 1–214 (characters.radical giữ dạng viết trong chữ: 氵, 亻…)
--   analysis         một câu giải thích cấu tạo: "Hình thanh: 氵 (nước) gợi nghĩa, 可 (kě) gợi âm."
--   radical_examples JSON: chữ khác cùng bộ, ưu tiên chữ trong từ vựng HSK 1–3
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE characters
  ADD COLUMN radical_no TINYINT UNSIGNED NULL AFTER radical,
  ADD COLUMN analysis TEXT NULL AFTER radical_no,
  ADD COLUMN radical_examples TEXT NULL AFTER analysis;
