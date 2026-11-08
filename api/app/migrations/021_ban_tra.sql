-- =====================================================================
-- Zìkǎ · 021 — Quán trà chia "bàn" theo chủ đề
--
-- Một phòng chung cho mọi chủ đề thì đông người là rối, câu hỏi bài bị trôi giữa chuyện làm quen (người dùng góp ý
-- 08/11/2026). Mỗi bàn một chủ đề; quản trị thêm/sửa/đóng bàn. Tin cũ (trước khi có bàn) về Bàn chung.
-- Đóng bàn (is_open = 0) là chỉ đọc, không xoá tin.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS chat_rooms (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(40)  NOT NULL,
  name         VARCHAR(80)  NOT NULL,
  description  VARCHAR(200) NOT NULL DEFAULT '',
  icon         VARCHAR(8)   NOT NULL DEFAULT '🍵',
  sort_order   INT          NOT NULL DEFAULT 0,
  is_open      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_chat_rooms_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO chat_rooms (id, slug, name, description, icon, sort_order, created_at) VALUES
  (1, 'chung',       'Bàn chung',        'Chào hỏi, làm quen, chuyện gì cũng được.',                      '🍵', 1, '2026-11-08 00:00:00'),
  (2, 'hoi-bai',     'Hỏi bài',          'Hỏi từ, ngữ pháp, bài tập chưa hiểu — ghi rõ quyển và bài.',   '❓', 2, '2026-11-08 00:00:00'),
  (3, 'meo-hoc',     'Mẹo học',          'Cách nhớ chữ, lịch học, kinh nghiệm thi HSK.',                  '💡', 3, '2026-11-08 00:00:00'),
  (4, 'tim-ban',     'Tìm bạn học',      'Rủ nhau học cùng, hẹn giờ luyện nói.',                          '🤝', 4, '2026-11-08 00:00:00'),
  (5, 'tieng-trung', 'Góc tiếng Trung',  '中文角 — chỉ nói tiếng Trung, sai cũng không sao.',             '中', 5, '2026-11-08 00:00:00');

ALTER TABLE chat_messages
  ADD COLUMN room_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
  ADD KEY ix_chat_room (room_id, id),
  ADD CONSTRAINT fk_chat_room FOREIGN KEY (room_id) REFERENCES chat_rooms(id);
