-- 80 người học minh họa, tên hư cấu, không có ảnh đại diện.
-- Chọn đúng database của Zìkǎ trước khi chạy. Cần migration 022, 023 và 024.
-- Chỉ thêm vào demo_learners; người trùng tên đã có được giữ nguyên.
-- Không tạo tài khoản, đăng ký thi, kết quả thi hoặc lịch sử thưởng thật.
-- Bật cả ba mục: hiện = 1, tự động = 1, đăng ký = 1, cuộc thi = 1.
-- Nếu đã import bản cũ, dùng file 80-nguoi-hoc-bat-ba-muc.sql để bật các mục còn thiếu.
-- Phân bố: 20 nhẹ nhàng, 40 đều đặn, 20 chăm chỉ.
-- Số liệu tham chiếu 14 ngày, chỉ tính tới hôm qua để hôm nay không bị đếm hai lần.
-- Nếu import trước ngày 15, số liệu giảm theo số ngày đã qua trong tháng.
-- Từ ngày 15 trở đi giữ mức tham chiếu; nhịp tự động tiếp tục từ thời điểm import.
-- Sang tháng mới, ứng dụng lưu tháng cũ và bắt đầu số liệu tháng mới từ 0.

SET NAMES utf8mb4;

-- Dùng UTC + 07:00 để khớp Clock của ứng dụng, không phụ thuộc múi giờ MySQL.
SET @zika_seed_now = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR);
SET @zika_seed_month = DATE_FORMAT(@zika_seed_now, '%Y-%m');
SET @zika_seed_elapsed = LEAST(DAYOFMONTH(@zika_seed_now) - 1, 14);

DROP TEMPORARY TABLE IF EXISTS tmp_zika_demo_80_20261115;
CREATE TEMPORARY TABLE tmp_zika_demo_80_20261115 (
  name VARCHAR(80) NOT NULL PRIMARY KEY,
  activity_level VARCHAR(12) NOT NULL,
  minutes INT UNSIGNED NOT NULL,
  study_days TINYINT UNSIGNED NOT NULL,
  completed_parts INT UNSIGNED NOT NULL,
  auto_enabled TINYINT(1) NOT NULL,
  is_visible TINYINT(1) NOT NULL,
  is_registered TINYINT(1) NOT NULL,
  show_contest TINYINT(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cột: tên, nhịp học, phút học, ngày học, phần hoàn thành,
--      tự động tăng, hiển thị, danh sách đăng ký, bảng cuộc thi.
INSERT INTO tmp_zika_demo_80_20261115
  (name, activity_level, minutes, study_days, completed_parts,
   auto_enabled, is_visible, is_registered, show_contest)
VALUES
  ('Nguyễn Hoàng Minh',    'cham', 648, 12, 25, 1, 1, 1, 1),
  ('Trần Ngọc Anh',        'vua',  264,  9, 10, 1, 1, 1, 1),
  ('Lê Thanh Hằng',        'vua',  217,  8,  8, 1, 1, 1, 1),
  ('Phạm Quốc Bảo',        'it',    72,  6,  2, 1, 1, 1, 1),
  ('Hoàng Thảo Nguyên',    'cham', 572, 11, 22, 1, 1, 1, 1),
  ('Phan Minh Khang',      'vua',  246,  9,  9, 1, 1, 1, 1),
  ('Vũ Khánh Linh',        'it',    63,  6,  2, 1, 1, 1, 1),
  ('Đặng Tuấn Kiệt',       'vua',  192,  8,  7, 1, 1, 1, 1),
  ('Bùi Hà Phương',        'cham', 486, 10, 19, 1, 1, 1, 1),
  ('Đỗ Gia Huy',           'vua',  295, 10, 11, 1, 1, 1, 1),
  ('Hồ Quỳnh Trang',       'vua',  168,  7,  6, 1, 1, 1, 1),
  ('Ngô Đức Anh',          'it',    48,  5,  1, 1, 1, 1, 1),
  ('Dương Bảo Ngọc',       'cham', 594, 12, 23, 1, 1, 1, 1),
  ('Đinh Thành Đạt',       'vua',  231,  8,  9, 1, 1, 1, 1),
  ('Lý Nhã Uyên',          'it',    81,  7,  3, 1, 1, 1, 1),
  ('Võ Minh Quân',         'vua',  203,  7,  8, 1, 1, 1, 1),
  ('Nguyễn Thùy Dương',    'cham', 528, 11, 21, 1, 1, 1, 1),
  ('Trần Hải Đăng',        'vua',  276,  9, 11, 1, 1, 1, 1),
  ('Lê Ngọc Diệp',         'vua',  154,  7,  6, 1, 1, 1, 1),
  ('Phạm Anh Tuấn',        'it',    54,  6,  2, 1, 1, 1, 1),
  ('Hoàng Mai Chi',        'cham', 462, 10, 18, 1, 1, 1, 1),
  ('Phan Đức Minh',        'vua',  252,  9, 10, 1, 1, 1, 1),
  ('Vũ Thanh Trúc',        'it',    77,  7,  3, 1, 1, 1, 1),
  ('Đặng Quang Huy',       'vua',  179,  8,  7, 1, 1, 1, 1),
  ('Bùi Phương Thảo',      'cham', 615, 12, 24, 1, 1, 1, 1),
  ('Đỗ Khánh Nam',         'vua',  238,  8,  9, 1, 1, 1, 1),
  ('Hồ Ngọc Hà',           'vua',  186,  7,  7, 1, 1, 1, 1),
  ('Ngô Trung Hiếu',       'it',    42,  4,  1, 1, 1, 1, 1),
  ('Dương Tú Anh',         'cham', 504, 11, 20, 1, 1, 1, 1),
  ('Đinh Hoàng Phúc',      'vua',  288, 10, 11, 1, 1, 1, 1),
  ('Lý Minh Châu',         'it',    66,  6,  2, 1, 1, 1, 1),
  ('Võ Thành Long',        'vua',  209,  8,  8, 1, 1, 1, 1),
  ('Nguyễn Bích Ngọc',     'cham', 558, 11, 22, 1, 1, 1, 1),
  ('Trần Hữu Phước',       'vua',  171,  7,  6, 1, 1, 1, 1),
  ('Lê Hoài An',           'vua',  224,  8,  8, 1, 1, 1, 1),
  ('Phạm Thiên Phúc',      'it',    91,  7,  3, 1, 1, 1, 1),
  ('Hoàng Ngọc Lan',       'cham', 438, 10, 17, 1, 1, 1, 1),
  ('Phan Nhật Minh',       'vua',  267,  9, 10, 1, 1, 1, 1),
  ('Vũ Thu Hà',            'it',    35,  4,  1, 1, 1, 1, 1),
  ('Đặng Gia Bảo',         'vua',  198,  8,  7, 1, 1, 1, 1),
  ('Bùi Thùy Linh',        'cham', 582, 12, 23, 1, 1, 1, 1),
  ('Đỗ Minh Trí',          'vua',  243,  9,  9, 1, 1, 1, 1),
  ('Hồ Diệu Linh',         'vua',  162,  7,  6, 1, 1, 1, 1),
  ('Ngô Quốc Hưng',        'it',    59,  5,  2, 1, 1, 1, 1),
  ('Dương Khánh An',       'cham', 516, 11, 20, 1, 1, 1, 1),
  ('Đinh Hải Nam',         'vua',  279,  9, 11, 1, 1, 1, 1),
  ('Lý Thanh Tâm',         'it',    84,  7,  3, 1, 1, 1, 1),
  ('Võ Tuấn Anh',          'vua',  213,  8,  8, 1, 1, 1, 1),
  ('Nguyễn Ngọc Hân',      'cham', 630, 12, 25, 1, 1, 1, 1),
  ('Trần Công Thành',      'vua',  147,  7,  5, 1, 1, 1, 1),
  ('Lê Phương Nghi',       'vua',  256,  9, 10, 1, 1, 1, 1),
  ('Phạm Minh Đức',        'it',    46,  5,  1, 1, 1, 1, 1),
  ('Hoàng Như Quỳnh',      'cham', 474, 10, 18, 1, 1, 1, 1),
  ('Phan Quang Vinh',      'vua',  234,  8,  9, 1, 1, 1, 1),
  ('Vũ Kim Ngân',          'it',    69,  6,  2, 1, 1, 1, 1),
  ('Đặng Bảo Long',        'vua',  183,  7,  7, 1, 1, 1, 1),
  ('Bùi Hồng Nhung',       'cham', 546, 11, 21, 1, 1, 1, 1),
  ('Đỗ Thành Trung',       'vua',  303, 10, 12, 1, 1, 1, 1),
  ('Hồ Trúc Linh',         'vua',  201,  8,  8, 1, 1, 1, 1),
  ('Ngô Hoàng Việt',       'it',    38,  4,  1, 1, 1, 1, 1),
  ('Dương Phương Anh',     'cham', 606, 12, 24, 1, 1, 1, 1),
  ('Đinh Quốc Khánh',      'vua',  222,  8,  8, 1, 1, 1, 1),
  ('Lý Ngọc Huyền',        'it',    75,  6,  3, 1, 1, 1, 1),
  ('Võ Đức Huy',           'vua',  159,  7,  6, 1, 1, 1, 1),
  ('Nguyễn Cẩm Tú',        'cham', 498, 10, 19, 1, 1, 1, 1),
  ('Trần Minh Thành',      'vua',  273,  9, 10, 1, 1, 1, 1),
  ('Lê Quỳnh Như',         'vua',  195,  8,  7, 1, 1, 1, 1),
  ('Phạm Hữu Khang',       'it',    51,  5,  2, 1, 1, 1, 1),
  ('Hoàng Gia Linh',       'cham', 564, 11, 22, 1, 1, 1, 1),
  ('Phan Anh Khoa',        'vua',  249,  9,  9, 1, 1, 1, 1),
  ('Vũ Minh Thư',          'it',    88,  7,  3, 1, 1, 1, 1),
  ('Đặng Thanh Sơn',       'vua',  174,  7,  6, 1, 1, 1, 1),
  ('Bùi Ngọc Trâm',        'cham', 450, 10, 18, 1, 1, 1, 1),
  ('Đỗ Quang Minh',        'vua',  291, 10, 11, 1, 1, 1, 1),
  ('Hồ Khánh Vy',          'vua',  207,  8,  8, 1, 1, 1, 1),
  ('Ngô Đức Thịnh',        'it',    57,  5,  2, 1, 1, 1, 1),
  ('Dương Thanh Vân',      'cham', 534, 11, 21, 1, 1, 1, 1),
  ('Đinh Minh Hoàng',      'vua',  261,  9, 10, 1, 1, 1, 1),
  ('Lý Hoài Thương',       'it',    44,  4,  1, 1, 1, 1, 1),
  ('Võ Nhật Quang',        'vua',  189,  8,  7, 1, 1, 1, 1);

-- Để MySQL tự cấp id; chỉ chèn người chưa có tên tương ứng.
-- Chạy lại file không ghi đè số liệu/cấu hình đã sửa của người đã tồn tại.
INSERT INTO demo_learners
  (name, avatar_path, activity_level, minutes, study_days, completed_parts,
   auto_enabled, is_visible, is_registered, show_contest,
   auto_cursor, stats_month, created_at, updated_at)
SELECT
  s.name,
  NULL,
  s.activity_level,
  ROUND(s.minutes * s.elapsed_study_days / s.study_days),
  s.elapsed_study_days,
  FLOOR(s.completed_parts * s.elapsed_study_days / s.study_days),
  s.auto_enabled,
  s.is_visible,
  s.is_registered,
  s.show_contest,
  @zika_seed_now,
  @zika_seed_month,
  @zika_seed_now,
  @zika_seed_now
FROM (
  SELECT t.*, FLOOR(t.study_days * @zika_seed_elapsed / 14) AS elapsed_study_days
  FROM tmp_zika_demo_80_20261115 t
) s
WHERE NOT EXISTS (SELECT 1 FROM demo_learners d WHERE d.name = s.name)
ORDER BY s.name;

SET @zika_seed_inserted = ROW_COUNT();

-- Lần đồng bộ tiếp theo của ứng dụng tự tạo bản tổng kết demo_activity_months.
-- auto_cursor là lúc import, nên không cộng bù các ngày trước đó vào số liệu mẫu.
SELECT
  @zika_seed_inserted AS so_nguoi_vua_them,
  80 - @zika_seed_inserted AS so_ten_da_co_duoc_bo_qua,
  @zika_seed_month AS thang_so_lieu;

DROP TEMPORARY TABLE tmp_zika_demo_80_20261115;
