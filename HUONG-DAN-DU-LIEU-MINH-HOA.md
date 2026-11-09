# Người học và lịch sử thưởng minh họa

Mở **Quản trị → Người học minh họa** (`/admin/demo-learners`). Chỉ admin sử dụng; biên tập và học viên không có quyền gọi API.

## Sử dụng

- Tab Người học: thêm tên, nhịp học, phút học, số ngày và phần hoàn thành; sửa, ẩn hoặc xóa.
- Tab Lịch sử thi & thưởng: chọn người học đã tạo, nhập cuộc thi, tháng, ngày thi, hạng, tên giải, số tiền và trạng thái.
- Nút hiện/ẩn chỉ điều khiển bản xem trước trong admin. Không đưa dữ liệu vào bảng xếp hạng, đăng ký, lịch sử hay xét thưởng thật.
- Thay đổi đã lưu tồn tại sau khi tải lại trang. Không tự sinh người học hoặc lịch sử mẫu trong database.
- Xóa người học sẽ xóa lịch sử thưởng minh họa liên quan; hộp thoại xác nhận ghi rõ. Xóa một lịch sử vẫn giữ người học.
- Nhịp học được lưu để dùng sau; **chưa tự tăng phút hoặc tạo hoạt động theo ngày**. Các phút hiện tại do admin nhập.
- Form người học có chọn/đổi/xóa avatar; xem trước ảnh trước khi lưu, không chọn ảnh thì hiện chữ viết tắt. JPG/PNG/WebP tối đa 5 MB, server kiểm định dạng thật và chuẩn hóa ảnh vuông tối đa 192px.

## Database và triển khai

Chạy `cd api` rồi `php app/tools/install.php --migrate` (phải có `--migrate`). Migration `022_nguoi_hoc_minh_hoa.sql` tạo:

- `demo_learners`: tên, mức hoạt động, phút/ngày/phần học, các cờ hiển thị, ngày tạo/cập nhật.
- `demo_rewards`: khóa ngoại người học, cuộc thi, tháng và ngày thi, hạng, tên giải, tiền, trạng thái, cờ hiển thị, ngày tạo/cập nhật.

Hai bảng riêng trong cùng database, không tham chiếu `users` hay bảng quỹ/chi thưởng. Khóa ngoại xóa dây chuyền chỉ giữa hai bảng minh họa. Các thao tác được ghi `audit_log`.

Migration `023_avatar_minh_hoa.sql` bổ sung `demo_learners.avatar_path`. Ảnh nằm riêng trong `uploads/demo-avatar/`; `POST /admin/demo-learners/avatar` nhận multipart `file`. Chỉ upload khi lưu form. Xóa/đổi avatar sẽ dọn ảnh cũ nếu không còn người minh họa nào dùng. Ảnh tải lên nhưng lưu form thất bại có thể còn trong kho; chưa có tác vụ quét tệp mồ côi.

API admin: `GET/POST /admin/demo-learners`, `PUT/DELETE /admin/demo-learners/{id}` và bộ tương ứng `/admin/demo-rewards`. Payload theo trường giao diện; tên người ở lịch sử lấy từ người học liên kết nên đổi tên sẽ cập nhật cả lịch sử.

## Kịch bản kiểm tra

1. Admin vào màn lần đầu: danh sách trống, không có dữ liệu mẫu tự chèn.
2. Thêm người, F5: tên, nhịp học và các số đã nhập còn nguyên.
3. Sửa và ẩn người, F5: dữ liệu và trạng thái được giữ; bản xem trước phản ánh trạng thái.
4. Thêm lịch sử cho người đó, nhập đủ ngày/tháng, tiền và trạng thái. F5, chuyển tab: lịch sử vẫn còn.
5. Sửa trạng thái thưởng, ẩn/hiện lịch sử: bản xem trước và dữ liệu sau F5 khớp nhau.
6. Đổi tên người học: lịch sử hiển thị tên mới.
7. Xóa một lịch sử: người học vẫn còn. Xóa người học sau xác nhận: các lịch sử liên quan biến mất.
8. API từ chối ngày không có thật, tháng ngoài 01–12, số âm, trạng thái ngoài danh mục, người học không tồn tại; không tạo dòng lỗi.
9. Học viên/biên tập không mở màn hoặc gọi các API này được. Không đăng nhập bị yêu cầu đăng nhập; ghi dữ liệu vẫn qua CSRF chung.
10. Kiểm tra bảng xếp hạng và lịch sử thật không tăng thêm người, giải hay tiền do thao tác trong admin này.
11. Lỗi mạng: hiện lỗi/tải lại, không báo lưu thành công; bấm lưu/xóa bị vô hiệu khi đang xử lý.
12. Chọn ảnh → xem trước → lưu → F5: ảnh còn đúng trong danh sách và cả ba tab xem trước. Đổi/xóa ảnh rồi lưu giữ đúng trạng thái; hủy form không đổi avatar đã lưu. File sai định dạng/quá 5 MB bị chặn.

Kiểm thử tự động backend: `api/tests/Unit/DemoLearnerTest.php` (CRUD, validation, route admin, cascade, giữ tài khoản thật).
