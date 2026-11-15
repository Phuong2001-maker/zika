-- Bật Chăm học, Đăng ký và Cuộc thi cho bộ 80 tên đã import.
-- Chọn đúng database Zìkǎ trong phpMyAdmin rồi import file này (UTF-8).
-- Chỉ cập nhật cờ hiển thị của tên thuộc bộ 80 người; không thêm tài khoản/kết quả thật.
-- Giữ nguyên phút, ngày, phần hoàn thành, nhịp tự động và thời điểm bắt đầu tự tăng.
-- Chạy lại an toàn: người đã bật đủ ba mục không bị cập nhật lại.
-- Thứ tự khác nhau giữa hai tab Đăng ký/Cuộc thi cần bản PHP đi kèm.
-- Bản PHP cũ đều đọc ORDER BY id; chỉ SQL không thể đổi thứ tự riêng của hai tab.

SET NAMES utf8mb4;

DROP TEMPORARY TABLE IF EXISTS tmp_zika_demo_80_hien_thi;
CREATE TEMPORARY TABLE tmp_zika_demo_80_hien_thi (
  name VARCHAR(80) NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_zika_demo_80_hien_thi (name) VALUES
  ('Nguyễn Hoàng Minh'),
  ('Trần Ngọc Anh'),
  ('Lê Thanh Hằng'),
  ('Phạm Quốc Bảo'),
  ('Hoàng Thảo Nguyên'),
  ('Phan Minh Khang'),
  ('Vũ Khánh Linh'),
  ('Đặng Tuấn Kiệt'),
  ('Bùi Hà Phương'),
  ('Đỗ Gia Huy'),
  ('Hồ Quỳnh Trang'),
  ('Ngô Đức Anh'),
  ('Dương Bảo Ngọc'),
  ('Đinh Thành Đạt'),
  ('Lý Nhã Uyên'),
  ('Võ Minh Quân'),
  ('Nguyễn Thùy Dương'),
  ('Trần Hải Đăng'),
  ('Lê Ngọc Diệp'),
  ('Phạm Anh Tuấn'),
  ('Hoàng Mai Chi'),
  ('Phan Đức Minh'),
  ('Vũ Thanh Trúc'),
  ('Đặng Quang Huy'),
  ('Bùi Phương Thảo'),
  ('Đỗ Khánh Nam'),
  ('Hồ Ngọc Hà'),
  ('Ngô Trung Hiếu'),
  ('Dương Tú Anh'),
  ('Đinh Hoàng Phúc'),
  ('Lý Minh Châu'),
  ('Võ Thành Long'),
  ('Nguyễn Bích Ngọc'),
  ('Trần Hữu Phước'),
  ('Lê Hoài An'),
  ('Phạm Thiên Phúc'),
  ('Hoàng Ngọc Lan'),
  ('Phan Nhật Minh'),
  ('Vũ Thu Hà'),
  ('Đặng Gia Bảo'),
  ('Bùi Thùy Linh'),
  ('Đỗ Minh Trí'),
  ('Hồ Diệu Linh'),
  ('Ngô Quốc Hưng'),
  ('Dương Khánh An'),
  ('Đinh Hải Nam'),
  ('Lý Thanh Tâm'),
  ('Võ Tuấn Anh'),
  ('Nguyễn Ngọc Hân'),
  ('Trần Công Thành'),
  ('Lê Phương Nghi'),
  ('Phạm Minh Đức'),
  ('Hoàng Như Quỳnh'),
  ('Phan Quang Vinh'),
  ('Vũ Kim Ngân'),
  ('Đặng Bảo Long'),
  ('Bùi Hồng Nhung'),
  ('Đỗ Thành Trung'),
  ('Hồ Trúc Linh'),
  ('Ngô Hoàng Việt'),
  ('Dương Phương Anh'),
  ('Đinh Quốc Khánh'),
  ('Lý Ngọc Huyền'),
  ('Võ Đức Huy'),
  ('Nguyễn Cẩm Tú'),
  ('Trần Minh Thành'),
  ('Lê Quỳnh Như'),
  ('Phạm Hữu Khang'),
  ('Hoàng Gia Linh'),
  ('Phan Anh Khoa'),
  ('Vũ Minh Thư'),
  ('Đặng Thanh Sơn'),
  ('Bùi Ngọc Trâm'),
  ('Đỗ Quang Minh'),
  ('Hồ Khánh Vy'),
  ('Ngô Đức Thịnh'),
  ('Dương Thanh Vân'),
  ('Đinh Minh Hoàng'),
  ('Lý Hoài Thương'),
  ('Võ Nhật Quang');

UPDATE demo_learners d
JOIN tmp_zika_demo_80_hien_thi s ON s.name = d.name
SET
  d.is_visible = 1,
  d.is_registered = 1,
  d.show_contest = 1,
  d.updated_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR)
WHERE d.is_visible <> 1 OR d.is_registered <> 1 OR d.show_contest <> 1;

SET @zika_demo_flags_changed = ROW_COUNT();

SELECT
  @zika_demo_flags_changed AS so_nguoi_vua_bat_them_muc,
  COUNT(*) AS so_nguoi_tim_thay,
  COALESCE(SUM(d.is_visible = 1), 0) AS hien_cham_hoc,
  COALESCE(SUM(d.is_visible = 1 AND d.is_registered = 1), 0) AS hien_dang_ky,
  COALESCE(SUM(d.is_visible = 1 AND d.show_contest = 1), 0) AS hien_cuoc_thi
FROM demo_learners d
JOIN tmp_zika_demo_80_hien_thi s ON s.name = d.name;

DROP TEMPORARY TABLE tmp_zika_demo_80_hien_thi;
