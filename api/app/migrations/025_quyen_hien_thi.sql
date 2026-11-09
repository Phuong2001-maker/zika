-- Cấu hình cha/con theo vai trò; lưu giá trị riêng của con, không ghi đè khi tắt cha.
CREATE TABLE IF NOT EXISTS feature_access (
  feature_key VARCHAR(80) NOT NULL,
  role VARCHAR(12) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (feature_key, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS feature_access_state (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  revision INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO feature_access_state (id) VALUES (1);
