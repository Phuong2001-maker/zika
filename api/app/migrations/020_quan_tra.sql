-- =====================================================================
-- Zìkǎ · 020 — Quán trà: phòng trò chuyện chung của người học
--
-- Một phòng chung cho mọi tài khoản đã đăng nhập (người dùng yêu cầu 08/11/2026: "nói chuyện như một group").
-- Hosting dùng chung không chạy được WebSocket nên web hỏi tin mới mỗi vài giây (chỉ khi tab đang mở).
-- Tin bị xoá giữ dòng (deleted_at) để tin trả lời nó vẫn hiện "Tin đã bị xoá" thay vì mất ngữ cảnh.
-- Người học báo cáo tin xấu → quản trị xem ở /admin/chat, xoá tin, cấm chat có thời hạn hoặc vĩnh viễn.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS chat_messages (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  body        VARCHAR(1000)   NOT NULL,
  reply_to    BIGINT UNSIGNED NULL,
  created_at  DATETIME        NOT NULL,
  deleted_at  DATETIME        NULL,
  deleted_by  BIGINT UNSIGNED NULL,
  KEY ix_chat_user (user_id, created_at),
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mỗi người báo cáo một tin một lần.
CREATE TABLE IF NOT EXISTS chat_reports (
  message_id  BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(200)    NOT NULL DEFAULT '',
  created_at  DATETIME        NOT NULL,
  PRIMARY KEY (message_id, user_id),
  CONSTRAINT fk_chat_report_msg FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_report_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cấm chat: until NULL là vĩnh viễn.
CREATE TABLE IF NOT EXISTS chat_mutes (
  user_id     BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  until       DATETIME        NULL,
  reason      VARCHAR(200)    NOT NULL DEFAULT '',
  by_user_id  BIGINT UNSIGNED NULL,
  created_at  DATETIME        NOT NULL,
  CONSTRAINT fk_chat_mute_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
