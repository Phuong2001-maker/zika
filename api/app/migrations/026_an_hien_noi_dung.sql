ALTER TABLE curricula ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1;
CREATE TABLE curriculum_levels (
    curriculum_id INT UNSIGNED NOT NULL,
    level_id TINYINT UNSIGNED NOT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (curriculum_id, level_id),
    FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO curriculum_levels (curriculum_id, level_id, is_visible)
SELECT c.id, v.id, v.is_visible FROM curricula c CROSS JOIN levels v;
