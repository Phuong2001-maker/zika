# Quyền truy cập & hiển thị theo vai trò

Vào **Admin → Cài đặt → Quyền truy cập & hiển thị**. Mỗi hàng là một mục cha/con, mỗi cột là Học viên, Biên tập hoặc Admin. Gạt các mục cần đổi rồi bấm **Lưu quyền**. **Hủy thay đổi** chỉ bỏ những lựa chọn chưa lưu.

## Cách hoạt động

- Trạng thái riêng của từng ô được lưu trong DB. Quyền hiệu lực bằng trạng thái của chính nó VÀ tất cả mục cha.
- Tắt cha ẩn toàn bộ con, không ghi lại các ô con. Bật lại cha khôi phục đúng các lựa chọn trước đó.
- Khi cha tắt, ô con vẫn hiển thị trạng thái riêng nhưng bị vô hiệu hóa và ghi “Theo cha: tắt”.
- Các vai trò độc lập; thay cột học viên không làm thay cột admin/biên tập.
- Mục **Hiển thị** chỉ điều khiển giao diện. Mục **Truy cập** còn chặn đường dẫn và API. Ví dụ tắt Thanh đầu trang → Quỹ tháng chỉ ẩn nút; tắt Thông tin quỹ thưởng ẩn thông tin tiền quỹ trong các hộp liên quan.
- Lối vào tài khoản, đăng xuất, mật khẩu, đóng hộp, điều hướng/nộp bài và khu quản trị là chức năng thiết yếu. Không có công tắc tắt quyền quản trị. Nếu tắt Chuyển nhanh, thanh đầu có lối về Bàn học thay thế.
- Nếu một phần học bị tắt, đường dẫn trực tiếp cũng bị chuyển về phần được phép; nếu tắt toàn bộ phần học và Giáo trình thì về Tài khoản.
- Lượt kiểm tra đang làm được phép tiếp tục lưu/nộp, kể cả khi admin vừa tắt phần học/cuộc thi. Không cấp quyền làm lượt mới; các kiểm tra sở hữu, thời hạn và đăng ký thi vẫn giữ nguyên.
- Đây là quyền theo vai trò, không phải công tắc xuất bản từng bài hoặc phân giáo trình cho từng tài khoản. Muốn học được còn phải có nội dung đã xuất bản và đúng giáo trình.

## Danh mục

| Cha | Con |
|---|---|
| Thanh đầu trang | Điểm mở bài, Quỹ tháng, Top chăm học/bảng xếp hạng, chuông, Chuyển nhanh |
| Các phần học | Lật thẻ, Bài tập, Kiểm tra/thi thử, Luyện viết, Đọc hội thoại, Ngữ pháp |
| Lật thẻ | Lật thẻ, Ghép cặp, Trắc nghiệm, Gõ chữ |
| Giáo trình | File nghe, Đáp án, Điểm và nhận điểm mở bài |
| Cộng đồng | Quán trà, Góp ý, Top chăm học, Thông báo, Cuộc thi |
| Cuộc thi | Thông tin, đăng ký/hủy, vào thi, bảng xếp hạng, danh sách đăng ký, lịch sử, thông tin giải thưởng/nhận thưởng |
| Ủng hộ | Bé túi Shopee, link trong Quỹ tháng (Ủng hộ trực tiếp đã bỏ 09/11) |
| Thông tin quỹ thưởng | Công tắc riêng cho nội dung quỹ |
| Bàn học | Học tiếp, chuỗi ngày, thống kê từ, gợi ý ôn tập |
| Tài khoản | Thống kê, avatar, tên, cài đặt học, phiên đăng nhập, xóa tài khoản |

Quán trà, Bài tập và Kiểm tra mặc định tắt cho học viên/biên tập, bật cho admin để giữ trạng thái menu cũ. Admin có thể mở lại bằng bảng này; không cần sửa code.

Các phụ thuộc khác vẫn áp dụng: bé túi cần công tắc/link ở Ủng hộ & Shopee; file nghe/đáp án cần sách có nội dung; đăng ký cần lịch thi và chưa đến giờ bắt đầu. Các hàng Thông tin cuộc thi/Thông tin giải thưởng là điều khiển hiển thị; dữ liệu trạng thái cuộc thi dùng chung cho các mục đăng ký/vào thi, nên tắt Thông tin không làm tắt những mục đó.

## Backend, database và triển khai

- Chạy `cd api` rồi `php app/tools/install.php --migrate` trước khi sử dụng code mới. Migration `025_quyen_hien_thi.sql` tạo `feature_access` (khóa theo mục/vai trò) và `feature_access_state` (phiên bản cấu hình).
- Danh mục và mặc định: `api/app/src/Services/FeatureAccess.php`. Không nhận danh mục/quan hệ cha từ client. `effective()` tính quyền, `enforce()` áp vào API sau xác thực và CSRF.
- API chỉ admin: `GET/PUT /admin/feature-access`. Ghi nhận audit; phiên bản cũ trả 409 để tránh ghi đè cấu hình của admin khác. Toàn bộ thay đổi được lưu trong transaction.
- API phiên: `/me` có `features` (quyền hiệu lực của vai trò hiện tại) và `activeTests`; `/me/features` trả cùng hai trường để làm mới. Không gửi ma trận của các vai trò khác cho học viên.
- Frontend: `session.can(key)`, `FeatureGate.svelte`, `auth/features.ts`, `auth/routes.ts`. Bảng admin có trạng thái riêng trong `feature-access.svelte.ts`.
- Quyền API có hiệu lực ngay ở request kế tiếp. Giao diện tải lại quyền khi mở trang và mỗi 60 giây; người vừa lưu được cập nhật ngay. F5 để thấy ngay ở một tài khoản đang mở sẵn.
- Tắt điểm mở bài cũng chặn nhận điểm và mở bài bằng điểm; bài đã được mở vẫn đọc được, admin vẫn có thể cấp mở từ trang quản trị.
- Link `/go` kiểm vai trò và vị trí link (Icon/Quỹ) trước khi chuyển hướng; khách không đăng nhập vẫn có thể truy cập liên kết công khai theo hành vi cũ.
- Chưa đưa migration/code này lên hosting. Dữ liệu người học/lịch sử thưởng minh họa vẫn chỉ nằm trong admin, không trộn vào bảng thật.

## Kịch bản kiểm tra

1. Dùng admin đổi riêng Học viên → Quán trà, lưu rồi F5. Học viên thấy/không thấy mục đúng cấu hình; nhập URL và gọi `/api/chat` cũng theo quyền.
2. Tắt Cộng đồng khi Quán trà đang bật: toàn bộ con bị ẩn, ô Quán trà riêng vẫn bật. Bật lại Cộng đồng: Quán trà xuất hiện lại.
3. Tắt riêng Quán trà: Top chăm học và Cuộc thi vẫn được phép. Admin không bị ảnh hưởng bởi cột Học viên.
4. Tắt Thanh đầu trang: các nút con ẩn, tài khoản và lối về Bàn học còn dùng được. Bật lại giữ nguyên từng lựa chọn của con.
5. Tắt File nghe: nút nghe trên sách ẩn, file nghe API trả 403; trang sách/đáp án còn theo quyền riêng. Tắt đáp án tương tự. Đang phát mà quyền thay đổi thì tạm dừng khi giao diện nhận quyền mới.
6. Tắt một chế độ luyện từ: tab đó ẩn, chuyển sang chế độ còn được mở. Tắt tất cả chế độ: có thông báo chưa mở, không để tab đã tắt tiếp tục hiện.
7. Tắt Thông tin cuộc thi nhưng giữ Đăng ký: đăng ký không tự bị tắt. Tắt cha Cuộc thi: không đăng ký mới hoặc vào thi mới được.
8. Bắt đầu thi rồi admin tắt chức năng: vẫn lưu/nộp lượt đó, không dùng lượt của người khác. Tắt quyền không thay đổi giờ kết thúc.
9. Tắt một tab bảng xếp hạng hoặc lịch sử: tab/nút tương ứng ẩn, API tương ứng từ chối. Thông tin giải thưởng không hiển thị khi tắt mục đó.
10. Tắt Bé túi độc lập với link trong Quỹ tháng. Công tắc tổng/link Shopee cũ vẫn có hiệu lực.
11. Tắt chỉnh avatar/tên/cài đặt/phiên/xóa tài khoản: đúng khu vực ẩn và API bị chặn; đăng xuất và đổi mật khẩu luôn dùng được.
12. Mở hai phiên admin, lưu ở phiên thứ nhất rồi lưu dữ liệu cũ ở phiên thứ hai: báo cấu hình đã đổi (409), không ghi đè.
13. Học viên/biên tập gọi API cấu hình nhận 403; sai khóa, vai trò, kiểu dữ liệu hoặc cố tắt Tài khoản bị từ chối 422.

Test tự động: `FeatureAccessTest.php`, `auth/features.test.ts`. Test Shopee/Quán trà điều chỉnh để dùng cấu hình quyền thay cho yêu cầu admin viết cứng.

## Ẩn/hiện nội dung theo giáo trình → cấp/quyển → bài

Vào **Admin → Giáo trình → Ẩn/hiện nội dung**. Chọn giáo trình, mở cấp/quyển rồi gạt bài muốn hiện hoặc ẩn. Mỗi lần gạt lưu ngay; nếu lỗi thì giữ nguyên trạng thái trước đó. Công tắc giáo trình và cấp không ghi đè lựa chọn của con.

- Một bài có **một trạng thái chung cho cả 6 phần**. Không còn công tắc riêng ở từng tab soạn bài. Các tab soạn bài không có nút dẫn sang cây này hay nút Xem như người học (bỏ 09/11 theo yêu cầu); vào thẳng Giáo trình → Ẩn/hiện nội dung.
- Tắt giáo trình chặn toàn bộ cấp và bài của chính giáo trình đó. Tắt cấp chỉ ảnh hưởng cấp của giáo trình đang chọn; giáo trình khác có cùng số cấp vẫn giữ nguyên.
- Tắt bài loại bài khỏi danh sách, Học tiếp, tổng hợp/ôn tập và cả 6 API nội dung. URL cũ trả 404. Dữ liệu soạn bài, phân giáo trình và tiến độ đã học vẫn được giữ.
- Bật lại cha chỉ mở các con có trạng thái riêng đang bật. Học viên không tự chuyển sang giáo trình khác khi bộ mình bị ẩn.
- Cấp bị ẩn không xuất hiện thành thẻ “Sắp có” trên 6 trang chọn cấp. Cấp đang bật nhưng chưa có bài vẫn giữ trạng thái đang soạn.
- Admin/biên tập vẫn xem trước bằng `?preview=1`; học viên không dùng tham số này để vượt quyền. Lượt kiểm tra đã bắt đầu được tiếp tục/nộp; lượt mới bị chặn.
- Công tắc theo vai trò ở **Cài đặt** và cây nội dung cùng có hiệu lực: phải được phép vào phần học, đúng giáo trình và cả cây nội dung đang hiện mới học được. Cây này quản lý 6 phần soạn theo bài **và cả màn đọc sách** (từ 09/11, xem mục dưới).

### Màn đọc sách (`/learn/books`)

- Bài sách số `n` của quyển cấp `c` ứng với bài `lesson_no = n`, `level_id = c` của giáo trình (dữ liệu nạp từ chính sách nên cùng cách đánh số). Học viên xét theo giáo trình của mình; biên tập/quản trị đọc cả hai bộ nên bộ HSK xét theo Giáo trình HSK 3.0, bộ Hán ngữ theo giáo trình gọi cấp "Quyển".
- Bài ẩn: mục lục bỏ bài đó, trang của bài thành thẻ "Bài n đang tạm ẩn"; server không gửi tên bài, file nghe; ảnh trang, trang đáp án, file nghe và nút mở bằng điểm trả 404 — kể cả bài đã mở bằng điểm từ trước và kể cả quản trị, giống 6 phần học. Bài mở tiếp bằng điểm bỏ qua bài ẩn để học viên không kẹt.
- Ẩn cấp/quyển hoặc ẩn giáo trình: quyển biến khỏi kệ sách, mọi đường dẫn kể cả bìa trả 404.
- Bật lại là đọc được ngay; bài đã mở bằng điểm và số điểm không mất. Bài sách chưa có trong dữ liệu thì không bị chặn (cây chỉ ẩn được bài nó có).
- Code: `BookAccess::an()`, `trangThai()` trả thêm `an`, `BookController::doc()`/`chanBaiAn()`. API quyền đọc trả thêm `quyen.an` (số bài ẩn).

### Lưu trữ và bảo trì

- Migration `026_an_hien_noi_dung.sql`: thêm `curricula.is_visible`, bảng `curriculum_levels` khóa theo `(curriculum_id, level_id)`. Bài dùng `lessons.status` sẵn có, không tạo 6 cờ cho 6 phần.
- Migration sao chép trạng thái cấp cũ vào từng giáo trình, không tự mở cấp đã ẩn. Giáo trình mới tạo qua API cũng có 6 dòng cấp riêng. Dữ liệu cũ/công cụ nạp chưa tạo dòng cấp dùng `levels.is_visible` làm giá trị dự phòng.
- API `GET/PATCH /admin/content-visibility` chỉ admin. PATCH nhận `kind`, `curriculumId`, `levelId`/`lessonId` theo phạm vi và `visible` boolean. Kiểm bài thuộc đúng cha, khóa giao dịch và ghi audit; mỗi request chỉ đổi đúng một mục.
- `Services/ContentVisibility.php` là luật hiệu lực. Chặn ở `App` trước khi đọc cache; `ContentBuilder`, tiến độ và ôn tập cũng dùng luật này. Lưu thay đổi xóa cache cấp/danh sách/bài liên quan.
- Không thêm lại công tắc ẩn/hiện cấp ở trang Từ vựng/Cài đặt hay công tắc bài ở từng tab. `review_published_only` cũ không còn cho phép tổng hợp bài nháp cho học viên.
- Service worker hỏi mạng trước khi dùng nội dung học; phản hồi 401/403/404 luôn được giữ và xóa bản đã lưu. Mất mạng vẫn đọc được bài còn hạn đã lưu trước đó (tối đa 30 ngày); thay đổi quyền không thể thu hồi nội dung đã tải khi máy hoàn toàn ngoại tuyến.
- Bộ nhớ danh sách bài hỏi lại server mặc định khi điều hướng, tránh danh sách cũ còn giữ bài vừa ẩn.
- `/me` và `/me/features` chỉ trả thêm ID/tên bài của lượt chưa nộp thuộc chính tài khoản. `TestRunner` dùng thông tin này khi tải lại lượt thi, không cần mở cây bài đã ẩn để tìm ID. Server vẫn kiểm chủ lượt và hạn làm bài.

Test tự động bổ sung: `ContentVisibilityTest.php` (cây cha/con, giáo trình độc lập, 6 phần, ôn tập, cache và lượt thi), `authorized-content.test.ts` (server từ chối, ngoại tuyến, hết hạn, kho đầy), `BookPageTest.php` các test `testBaiAn…`, `testAnCap…`, `testCayCuaGiaoTrinhKhac…` (màn đọc sách), `sach.test.ts` (bài ẩn khoá ở giao diện).

Nhầm thường gặp: hai giáo trình đều có "Bài 1 · Xin chào". Tài khoản chưa được gán giáo trình (kể cả admin) học giáo trình mặc định Hán ngữ 6 tập, nên ẩn bài của Giáo trình HSK 3.0 rồi vào khu học vẫn thấy "Bài 1 · Xin chào" của Hán ngữ — đó là bài khác, không phải lỗi.
