-- =====================================================================
-- Zìkǎ · 018 — Bỏ dòng ghi chú "Bản dịch tiếng Việt là bản tham khảo của Zìkǎ — sách không in." ở tình huống bài khoá
--
-- Bộ nạp sách (data/nap-sach/sinh.py) từng chèn dòng này vào dialogues.scene của mọi bài khoá có bản dịch tự dịch,
-- cả HSK lẫn Hán ngữ. Người dùng không muốn hiện (08/11/2026); bộ nạp đã thôi chèn, đây dọn dữ liệu đã nạp.
-- =====================================================================
SET NAMES utf8mb4;

UPDATE dialogues
   SET scene = TRIM(REPLACE(REPLACE(scene, CONCAT(CHAR(10), 'Bản dịch tiếng Việt là bản tham khảo của Zìkǎ — sách không in.'), ''),
                            'Bản dịch tiếng Việt là bản tham khảo của Zìkǎ — sách không in.', ''))
 WHERE scene LIKE '%Bản dịch tiếng Việt là bản tham khảo của Zìkǎ%';
