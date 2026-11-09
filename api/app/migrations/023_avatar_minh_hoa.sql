-- 022 đã được áp dụng: bổ sung cột bằng migration mới để máy đã chạy vẫn nâng cấp được.
ALTER TABLE demo_learners ADD COLUMN avatar_path VARCHAR(500) NULL AFTER name;
