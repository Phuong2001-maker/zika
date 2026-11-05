-- =====================================================================
-- Zìkǎ · 009 — Cuộc thi tháng
--
-- Cuộc thi dùng lại đề thi thử (mock_tests) nên có sẵn bộ soạn câu, chấm điểm và màn làm bài.
-- Đề để nháp thì không hiện ở danh sách Thi thử, chỉ làm được trong khoảng starts_at..ends_at.
-- Không cho xoá đề đang gắn cuộc thi (RESTRICT): xoá nhầm sẽ làm mất cuộc thi đã công bố giờ.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(120) NOT NULL,
  mock_test_id  INT UNSIGNED NOT NULL,
  starts_at     DATETIME     NOT NULL,
  ends_at       DATETIME     NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_contest_time (ends_at, starts_at),
  KEY ix_contest_mock (mock_test_id),
  CONSTRAINT fk_contest_mock FOREIGN KEY (mock_test_id) REFERENCES mock_tests(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
