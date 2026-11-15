-- =====================================================================
-- Zìkǎ · 029 — Màn Luyện viết mới: "Cách nhớ" + "Từ ghép" của từng chữ (15/11/2026)
--
-- Người dùng chốt giao diện: bỏ thẻ bộ thủ, đoạn phân tích, "Chữ khác cùng bộ"; thay bằng
--   cach_nho JSON: {"phan":[{"mau":"a|b","vi_tri","chu","ten","nghia"}],"lienTuong":"…"}
--     phần a = bộ thủ (đúng các nét radStrokes trong file nét, tô son), phần b = phần còn lại (tô xanh)
--   tu_ghep JSON: [{"hanzi","pinyin","nghia"}] 3–5 từ có chữ đó
-- Soạn cho 1.381 chữ Hán ngữ (data/han-ngu/cach-nho/, nạp bằng nap_cach_nho.py). Chữ chưa soạn thì màn học
-- lấy từ ghép từ bảng words và dùng lại đoạn analysis cũ làm câu nhớ.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE characters
  ADD COLUMN cach_nho TEXT NULL AFTER radical_examples,
  ADD COLUMN tu_ghep TEXT NULL AFTER cach_nho;

-- Soát lúc soạn Cách nhớ: hai chữ ghi nghĩa tiếng Việt vào ô âm Hán Việt (扛 "GÁNH", 油 "DẦU"). Chỉ sửa khi còn đúng giá trị sai.
UPDATE characters SET han_viet = 'GIANG' WHERE hanzi = '扛' AND han_viet = 'GÁNH';
UPDATE characters SET han_viet = 'DU' WHERE hanzi = '油' AND han_viet = 'DẦU';
