# Người học và lịch sử thưởng minh họa

Mở **Quản trị → Người học minh họa** (`/admin/demo-learners`). Chỉ admin sử dụng; biên tập và học viên không có quyền gọi API.

## Sử dụng

- Tab Người học: thêm tên, nhịp học, phút học, số ngày và phần hoàn thành; sửa, ẩn hoặc xóa.
- Tab Lịch sử thi & thưởng: chọn người học đã tạo, nhập cuộc thi, tháng, ngày thi, hạng, tên giải, số tiền và trạng thái.
- Công tắc **Hiển thị người học** đưa người đó vào Top chăm học phía học viên. Công tắc **Danh sách đăng ký cuộc thi** và **Bảng cuộc thi** điều khiển riêng hai tab tương ứng; tắt công tắc cha thì người đó không xuất hiện ở bất kỳ tab nào.
- Dữ liệu minh họa được ghép vào kết quả API dành cho học viên nhưng vẫn nằm riêng trong `demo_*`: không tạo tài khoản, lượt thi hay đăng ký thật và không tham gia xét thưởng. Ở bảng cuộc thi, kết quả thật luôn đứng trước; người minh họa không nhận nhãn giải thưởng.
- Thay đổi đã lưu tồn tại sau khi tải lại trang. Không tự sinh người học hoặc lịch sử mẫu trong database.
- Xóa người học sẽ xóa lịch sử thưởng minh họa liên quan; hộp thoại xác nhận ghi rõ. Xóa một lịch sử vẫn giữ người học.
- Mỗi người có công tắc **Tự động tăng phút theo nhịp học**. Người mới mặc định bật trong form; người đã tạo trước migration 024 giữ tắt cho tới khi admin bật.
- Nhẹ nhàng: 5–15 phút/ngày, 3–4 ngày/tuần. Đều đặn: 15–35 phút/ngày, 4–5 ngày/tuần. Chăm chỉ: 30–60 phút/ngày, 5–6 ngày/tuần. Lịch mỗi người khác nhau, chia hai lượt/ngày, có ngày nghỉ.
- Số phút tăng theo thời gian đã trôi trong lượt học, không tăng ngay chỉ vì bấm lưu. Một ngày chỉ tính một lần khi bắt đầu có phút. Mỗi 25 phút tự động cộng thêm một phần hoàn thành (quy ước minh họa, không tạo bài học thật).
- Tắt tự động sẽ chốt số tới thời điểm tắt; bật lại chỉ tiếp tục từ thời điểm bật, không cộng bù thời gian tắt. Ẩn người sẽ gỡ khỏi cả ba bảng phía học viên nhưng không tắt nhịp học.
- Admin vẫn chỉnh phút/ngày/phần bằng tay được. Giao diện gửi giá trị gốc để lưu tên/ảnh/cấu hình không ghi đè phần phút tự tăng trong lúc form đang mở.
- Sang tháng mới, số liệu tháng hiện tại bắt đầu từ 0 và giữ tổng của tháng cũ. Khối Nhịp hoạt động cho chọn người, xem lịch dự kiến tuần, phút đã cộng và lịch sử 24 tháng gần nhất.
- Lịch một ngày đã tạo được giữ ổn định; thay nhịp chỉ tác động ngày chưa có lịch. Chạy cron hoặc tải lại nhiều lần không cộng trùng; khi cron chạy muộn hệ thống tính phần còn thiếu từ lần đồng bộ trước.
- Form người học có chọn/đổi/xóa avatar; xem trước ảnh trước khi lưu, không chọn ảnh thì hiện chữ viết tắt. JPG/PNG/WebP tối đa 5 MB, server kiểm định dạng thật và chuẩn hóa ảnh vuông tối đa 192px.

## Database và triển khai

Chạy `cd api` rồi `php app/tools/install.php --migrate` (phải có `--migrate`). Migration `022_nguoi_hoc_minh_hoa.sql` tạo:

- `demo_learners`: tên, mức hoạt động, phút/ngày/phần học, các cờ hiển thị, ngày tạo/cập nhật.
- `demo_rewards`: khóa ngoại người học, cuộc thi, tháng và ngày thi, hạng, tên giải, tiền, trạng thái, cờ hiển thị, ngày tạo/cập nhật.

Hai bảng riêng trong cùng database, không tham chiếu `users` hay bảng quỹ/chi thưởng. Khóa ngoại xóa dây chuyền chỉ giữa hai bảng minh họa. Các thao tác được ghi `audit_log`.

Migration `023_avatar_minh_hoa.sql` bổ sung `demo_learners.avatar_path`. Ảnh nằm riêng trong `uploads/demo-avatar/`; `POST /admin/demo-learners/avatar` nhận multipart `file`. Chỉ upload khi lưu form. Xóa/đổi avatar sẽ dọn ảnh cũ nếu không còn người minh họa nào dùng. Ảnh tải lên nhưng lưu form thất bại có thể còn trong kho; chưa có tác vụ quét tệp mồ côi.

API admin: `GET/POST /admin/demo-learners`, `PUT/DELETE /admin/demo-learners/{id}` và bộ tương ứng `/admin/demo-rewards`. Payload theo trường giao diện; tên người ở lịch sử lấy từ người học liên kết nên đổi tên sẽ cập nhật cả lịch sử.

Migration `024_nhip_hoc_minh_hoa.sql` thêm `auto_enabled`, `auto_cursor`, `stats_month`; hai bảng `demo_activity_days` (lịch và phút đã cộng, khóa duy nhất theo người/ngày) và `demo_activity_months` (tổng từng tháng). Xóa người học cũng xóa lịch ngày/tháng liên quan.

Tác vụ `demoActivity` đã ghép vào `app/cron/every5min.php` có sẵn. Hosting dùng cron 5 phút theo README. Mở danh sách admin hoặc `GET /admin/demo-learners/{id}/activity` cũng đồng bộ phần đến hạn, nên local không cần cài lịch Windows để thử. Trang admin hỏi lại mỗi 60 giây khi không mở form; đồng hồ và tháng dùng múi giờ của `Clock` (+07:00). Chưa triển khai migration/code này lên hosting thì server thật chưa có tính năng.

Cấu hình gửi trường `tuDong` (boolean). Trường tùy chọn `goc: { phut, ngay, bai }` là số liệu lúc mở form: trường nào admin không sửa sẽ giữ số mới nhất sau đồng bộ, trường nào đã sửa áp dụng giá trị mới. Không truyền `goc` được hiểu là chỉnh tay cả ba trường.

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
10. Đăng nhập học viên, mở Top chăm học: người bật **Hiển thị người học** xuất hiện đúng thứ tự phút học và có avatar; người đã ẩn không xuất hiện.
11. Mở tab Đăng ký và Cuộc thi: mỗi người chỉ xuất hiện theo đúng công tắc riêng. Khi chưa có lịch thi thật, danh sách minh họa vẫn hiển thị; không gắn chữ “minh họa” trên giao diện học viên.
12. Tạo kết quả thi thật rồi bật người minh họa ở Bảng cuộc thi: người thật giữ nguyên thứ hạng và giải thưởng; người minh họa đứng sau, không có nút hay nhãn nhận thưởng.
13. Lỗi mạng: hiện lỗi/tải lại, không báo lưu thành công; bấm lưu/xóa bị vô hiệu khi đang xử lý.
14. Chọn ảnh → xem trước → lưu → F5: ảnh còn đúng trong danh sách, bản xem trước và giao diện học viên. Đổi/xóa ảnh rồi lưu giữ đúng trạng thái; hủy form không đổi avatar đã lưu. File sai định dạng/quá 5 MB bị chặn.
15. Bật tự động và xem lịch tuần: ngày nghỉ không có phút; trước giờ dự kiến chưa tăng. Trong lượt học, làm mới sau vài phút thấy tăng dần. Tải lại nhiều lần cùng thời điểm không cộng thêm.
16. Tắt/bật giữa một lượt học: không cộng khoảng bị tắt. Đổi tên/ảnh khi mở form lâu vẫn giữ phần phút đã tự tăng. Chỉnh tay phút xuống/thêm lên rồi tiếp tục tự động vẫn tính tiếp từ giá trị chỉnh.
17. Qua ranh giới tháng (test dùng Clock đóng băng), tổng tháng mới về 0 và tổng tháng cũ không mất; cron chạy muộn nhiều ngày vẫn tính đủ mỗi ngày theo lịch.

Kiểm thử tự động backend: `api/tests/Unit/DemoLearnerTest.php` (CRUD, validation, route admin, cascade, giữ tài khoản thật).
Nhịp tự động: `api/tests/Unit/DemoActivityTest.php` (lịch trong giới hạn, ngày nghỉ, cộng dần/chống trùng, tắt/bật, sửa tay, đổi tháng, cron muộn, xóa dây chuyền).
Ghép sang giao diện học viên: `api/tests/Unit/TopHocTest.php` và `api/tests/Unit/ContestTest.php` (ẩn/hiện, thứ tự Top chăm học, hai công tắc Đăng ký/Cuộc thi, không phụ thuộc lịch thi thật).
