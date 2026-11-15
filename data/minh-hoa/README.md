# Người học minh họa: bộ 80 người và bảng cuộc thi

## Dữ liệu và import

[80-nguoi-hoc.sql](80-nguoi-hoc.sql) tạo 80 hồ sơ hư cấu với họ tên Việt Nam đầy đủ, có dấu; không có ảnh. Giao diện dùng chữ viết tắt khi `avatar_path = NULL`.

- 20 người học nhẹ nhàng, 40 đều đặn, 20 chăm chỉ; số phút, ngày học và phần hoàn thành tương ứng nhịp học.
- Bản import hiện tại bật tự động tăng và hiển thị ở Chăm học, Đăng ký, Cuộc thi.
- Database cần migration 022–024; không có migration mới trong lần cập nhật này.
- Trong phpMyAdmin: chọn đúng database → Import → chọn file SQL, dùng UTF-8.

Hoặc chạy trong MySQL CLI sau khi chọn đúng database:

```sql
SOURCE D:/zika-devtools/Project/zika/data/minh-hoa/80-nguoi-hoc.sql;
```

SQL chỉ thêm tên chưa có vào `demo_learners`, để database tự cấp ID. Chạy lại tuần tự không ghi đè người đã tồn tại; nếu một số tên đã có thì số người thêm mới có thể ít hơn 80. Việc so tên dùng collation của database.

Số liệu thuộc tháng import theo giờ Việt Nam. Mức tham chiếu là 14 ngày đã qua, tính đến hôm qua. Nếu import trước ngày 15 thì giảm theo số ngày đã qua; ngày đầu tháng bằng 0. Từ ngày 15 trở đi giữ mức tham chiếu. `auto_cursor` bắt đầu ở lúc import để không cộng bù vào số mẫu. Ứng dụng tự tạo tổng kết tháng ở lần đồng bộ tiếp theo, lưu tháng cũ và bắt đầu tháng mới từ 0.

## Bật Đăng ký và Cuộc thi cho bộ đã import

Bản đầu tiên được gửi trong cuộc trao đổi chỉ bật Chăm học. Nếu đã dùng bản đó, import [80-nguoi-hoc-bat-ba-muc.sql](80-nguoi-hoc-bat-ba-muc.sql) để bật đủ ba mục cho các tên thuộc bộ 80 người.

File bật cờ giữ nguyên ID, phút/ngày/phần hoàn thành, nhịp tự động và mốc bắt đầu tự tăng. Chạy lại không sửa người đã bật đủ ba mục. Kết quả có số người tìm thấy và số hiển thị từng mục.

Ở **Quản trị → Người học minh họa**, sửa mỗi người để bật/tắt riêng **Danh sách đăng ký cuộc thi** hoặc **Bảng cuộc thi**. Tắt **Hiển thị người học** sẽ ẩn người đó khỏi cả ba mục.

## Chỉ hiện 20 người minh họa ở Cuộc thi

Trên database trong ảnh người dùng cung cấp ngày 15/11/2026, bộ 80 người có ID 2–81. Câu đã hướng dẫn để chỉ bật 20 người đầu:

```sql
UPDATE demo_learners
SET show_contest = CASE WHEN id BETWEEN 2 AND 21 THEN 1 ELSE 0 END
WHERE id BETWEEN 2 AND 81;
```

Khoảng ID này chỉ đúng với database đó. Khi dùng ở database khác, kiểm tra ID trước khi chạy. Không chạy lại file bật ba mục sau bước này nếu muốn giữ giới hạn 20 người. Câu trên giữ nguyên Chăm học và Đăng ký.

Giới hạn trả về hiện tại: Chăm học 10 dòng, Cuộc thi 50 dòng, Đăng ký 200 dòng. Kết quả thi thật luôn đứng trước người minh họa nên 20 người minh họa không nhất thiết chiếm vị trí 1–20 nếu đã có người thi thật.

## Xáo trộn tên và số câu đúng: chỉ cập nhật một file PHP

Thay đổi cuối cùng tập trung trong `api/app/src/Services/ContestService.php`, tại `withDemoRanking()`:

- Xáo trộn ổn định tên người đang bật `show_contest`, bằng khóa CRC32 theo tên và ID để phá hòa. Đăng ký vẫn theo ID của code hiện hành; Chăm học vẫn theo phút học.
- Người minh họa đầu có 40/40 câu đúng; các số còn lại được sinh trong khoảng 1–39, dựa trên tên và ID cuộc thi, rồi xếp giảm dần.
- Tải lại cùng danh sách và cuộc thi không đổi thứ tự/điểm. Đổi tên, ẩn/hiện người hoặc đổi cuộc thi có thể thay đổi cách phân điểm.
- Không tạo lượt thi thật, không ghi điểm minh họa vào DB. Kết quả, thứ hạng và quyền nhận giải của người thi thật giữ nguyên.
- Bản xem trước quản trị hiện vẫn dùng quy tắc cũ của màn quản trị; thứ tự xáo trộn và điểm mới áp dụng tại bảng Cuộc thi phía người học. Dòng phụ của giao diện cũ vẫn là “Chờ cập nhật kết quả”, còn cột câu đúng hiển thị số minh họa mới.

Không cần cập nhật `DemoActivity.php`, controller quản trị hoặc build frontend cho yêu cầu cuối cùng này. Các thay đổi trung gian và gói ZIP ba mục cũ đã được dọn khỏi lần bàn giao.

## Đưa file lên hosting

File đích:

```text
/home/buwsofujhosting/zika-app/src/Services/ContestService.php
```

Tải file cũ về dự phòng, mở đúng thư mục `zika-app/src/Services`, Upload file mới và chọn **Overwrite**. Chỉ ghi đè một file, không thay cả thư mục. Sau đó tải lại web bằng Ctrl+Shift+R. Nếu PHP vẫn giữ bản cũ, làm mới OPcache từ panel hosting.

Người dùng đã thực hiện thao tác upload qua 1Panel trong cuộc trao đổi; không ghi nhận là đã kiểm độc lập trên web thật. Không chạy lại test trước commit theo yêu cầu trực tiếp của người dùng.

Dữ liệu minh họa nằm riêng trong `demo_*`: không tạo tài khoản, đăng ký thi thật, tiến độ hay khoản chi thưởng. Top chăm học có ghép người minh họa vào bảng nên thứ hạng hiển thị ở mục đó có thể thay đổi. Import SQL trực tiếp không tạo `audit_log` như lưu qua form.
