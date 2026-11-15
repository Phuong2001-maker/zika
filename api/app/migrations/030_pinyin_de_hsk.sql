-- =====================================================================
-- Zìkǎ · 030 — Pinyin in trên đầu chữ ở đề Thi thử HSK 1–2 (15/11/2026)
--
-- Đề giấy HSK 3.0 cấp 1–2 in pinyin trên từng từ; màn Thi thử kiểu tờ đề (người dùng chốt 15/11) làm giống.
-- Pinyin sinh sẵn bằng data/hsk30/sinh_pinyin.py (jieba + pypinyin, có biến điệu 一/不) rồi nạp qua
-- PUT /admin/pinyin. Khoá theo sha1 của đúng chuỗi chữ: admin sửa câu thì chuỗi đổi, pinyin cũ tự không khớp nữa
-- (màn thi hiện chữ trơn) cho tới khi chạy lại script — không bao giờ hiện pinyin sai chữ.
--   tokens JSON: [["我们","wǒmen"],["，",""],…] — ghép cột 1 phải đúng bằng text.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS text_pinyin (
  hash CHAR(40) NOT NULL PRIMARY KEY,
  text TEXT NOT NULL,
  tokens TEXT NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
