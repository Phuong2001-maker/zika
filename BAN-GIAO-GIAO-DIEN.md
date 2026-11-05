# Bàn giao giao diện — 05/11/2026

## Phạm vi và quyết định đã chốt

Đây là **bản giao diện local để người dùng duyệt**, chưa triển khai hosting và chưa nối backend
cho giáo trình, quỹ thưởng, cuộc thi, bảng xếp hạng hay Shopee. Không coi số liệu mẫu là dữ liệu thật.

### Giáo trình: chỉ admin chọn cho học viên

- `/admin/curricula` có hai tab **Giáo trình** và **Phân quyền học**, chỉ admin được mở.
- Mỗi học viên được admin gán **một giáo trình**. Đổi bộ mới phải thay bộ cũ. Chưa gán là trạng thái
  chờ admin xử lý, không phải học viên được tự chọn.
- **Giữ nguyên luồng học HSK → bài → phần học của học viên.** Chỉ nội dung khác theo bộ admin gán.
  Đã gỡ màn `/learn/curricula` và các nút chọn giáo trình của học viên. Không khôi phục chúng.
- Tab Giáo trình: bảng tên bộ, cấp HSK, số bài, trạng thái, số học viên; có nút Thêm giáo trình.
  Người dùng đã yêu cầu bỏ thống kê, tìm/lọc giáo trình và cột Thao tác (Soạn bài/cài đặt).
- Hộp thêm giáo trình chỉ có thông tin, giới thiệu, màu, công tắc hiển thị; **không có tab Quyền học**.
- Tab Phân quyền học: tìm tên/email (có thể gõ không dấu), bảng học viên với một ô chọn giáo trình
  cho mỗi người, trạng thái, nút Hủy/Lưu. Đã bỏ tiêu đề, mô tả, số liệu, bộ lọc và ghi chú dữ liệu mẫu
  theo yêu cầu người dùng. Tìm kiếm không làm mất thay đổi đang chọn ở các học viên bị lọc khỏi bảng.
- Trạng thái mẫu lưu ở `localStorage['zk.curricula.preview.single.v2']`; không tác động quyền thật/API.

Mã: `web/src/lib/curricula/` và `web/src/routes/admin/curricula/+page.svelte`.
`CurriculumPreview.svelte` còn nhánh xem thử học viên từ bản thiết kế ban đầu, nhưng không còn route
hay nút nào mở nhánh đó. Khi dọn code có thể tách thành component thuần admin; không tạo thêm luồng học viên.

### Quỹ thưởng và top chăm học trên thanh đầu trang

- `MonthlyCommunity.svelte`: nút túi Shopee, số tiền quỹ và cúp Top chăm học cạnh nút chuyển nhanh,
  chuông và tài khoản. Có ở `Topbar.svelte` (desktop dùng chung), header Bàn học và header các màn chọn cấp
  (`PartHome.svelte`) trên điện thoại. Điện thoại ở màn chọn bài và màn đang làm bài **cố ý không có cụm này**:
  header đã có nút quay lại và tên bài, không đủ chỗ và cũng không nên làm người học phân tâm.
- Bỏ logo/chữ Zìkǎ ở Topbar dùng chung, header Bàn học và các màn chọn cấp trên điện thoại.
- Rà UI/UX 06/11: chữ thanh đầu trang và hộp thoại tăng lên (không còn chữ 8–10px), nút trên điện thoại rộng hơn nhưng
  vẫn vừa khổ 320px; bỏ tiêu đề lặp và khung lồng khung ở hộp quỹ; tháng trong tiêu đề lấy từ biến thay vì ghi cứng;
  hộp Shopee bỏ câu cảm ơn trước khi người học làm gì, nút chưa mở ghi "Sắp mở"; ba hạng đầu có huy chương.
- Đã bỏ khối dài cuối Bàn học. Bấm quỹ mở hộp thông tin; không lặp lại bảng top chăm học trong hộp này.
- Bấm cúp mở top 10 chăm học, có tab Cuộc thi ở trạng thái chưa có kết quả. Top chăm học là theo
  phút học trong tháng, cùng thời gian thì dự kiến ưu tiên số bài hoàn thành; không đồng nhất với top thi.
- Quỹ **1.250.000 ₫**, tháng **11/2026**, danh sách học viên/phút học đều **hardcode để minh họa**.
  Chưa tự đổi tháng, chưa có dữ liệu thật, chưa chốt ngày thi hay tỷ lệ chia tiền thưởng.
- Mục tiêu sau khi nối backend: mỗi tháng tổ chức thi giải đề, top đầu được thưởng dựa trên tổng
  hoa hồng thực tế đã đối soát; tiền hiển thị phải khớp Shopee, không suy từ lượt bấm.
- Nút Shopee hiện mở giải thích; nút ra Shopee trong hộp **đang disabled**. Admin đã có quản lý link
  `/admin/support`, nhưng chưa có API cho học viên lấy link và chưa có tích hợp tiền hoa hồng.
  Không gọi API admin từ tài khoản học viên và không tự thay bằng link Shopee chung làm mất affiliate.

### Bé túi Shopee chạy quanh giao diện học

- `ShopeeBuddy.svelte` gắn một lần ở layout gốc, chỉ khi đường dẫn bắt đầu `/learn/` và route được phép hiển thị.
- **Không hiện ở màn đang làm bài** (`lesson`, `review`, `mock`, đọc giáo trình) vì lời nhắn từng che nút "Kiểm tra" làm
  người học bấm nhầm. Luật ở `shopee-buddy.ts`, có test. Component vẫn sống khi sang các màn đó để bé đã ẩn thì không hiện lại.
- Túi SVG vẽ lại 06/11: mắt to chớp và nhìn theo con trỏ, má hồng, bùa tim đung đưa, rê chuột vào thì cười
  tít mắt và thả tim. Lời nhắn hiện tại:
  **“Chạm tớ, tớ ẩn nhé!” / “Chạm là góp quỹ thưởng rồi, không cần mua gì nhé 🧡” / “Liên kết tiếp thị”.**
  Câu giữa **người dùng đã chốt giữ ngày 06/11** dù đã được báo về quy tắc ở cuối mục này, đừng tự đổi lại.
- Chữ trôi theo hình gây hoa mắt nên **chữ chỉ hiện khi bé đứng yên**: vào trang đứng nói 12 giây, sau đó đi
  quãng ngắn (khoảng 240px, 36px/giây) rồi đứng nói 9 giây. Lúc đi chỉ có dấu tay nhỏ báo bấm được.
- Đường đi hình chữ U men theo cạnh màn hình, không đi chéo qua nội dung; điện thoại đi cao hơn thanh đáy.
- Rê chuột/focus thì dừng và hiện chữ ngay; đang nhập liệu hoặc có dialog thì tạm ẩn; có xử lý resize và
  `prefers-reduced-motion` (đứng yên, nói một lần rồi im). Rời màn thì dọn frame, timer và event listener.
- Bấm túi hoặc lời nhắn (đã bỏ dấu × ngày 06/11): chạy hiệu ứng biến mất và hiện thông báo đây là bản xem trước,
  **chưa mở link Shopee thật**, không tăng tiền quỹ.
- **Yêu cầu test hiện tại: F5 phải hiện bé lại ngay**, không ghi trạng thái ẩn vào sessionStorage/localStorage.
  Chuyển route học bình thường trong cùng layout vẫn giữ trạng thái đang ẩn.
- **Việc tiếp theo sau khi người dùng duyệt: chuyển sang hiện lại sau 1 ngày. Chưa làm ngay.**
  Cần chốt “sau 24 giờ” hay “ngày kế tiếp”, phạm vi theo tài khoản hay thiết bị trước khi nối dữ liệu thật.
- Chưa có căn cứ cho việc “chỉ bấm là sinh hoa hồng”. Lời nhắn của bé là ngoại lệ người dùng đã chốt; còn lại vẫn
  không tự tăng quỹ hay hiển thị thông báo đã góp tiền chỉ vì bấm. Khi nối link thật, cần đối chiếu lại câu này với điều khoản tiếp thị.

## Việc cần làm tiếp

1. Tiếp tục duyệt giao diện và chạy checklist trong `KICH-BAN-TEST.md` phần mới cuối file.
2. Khi người dùng yêu cầu backend: thêm quan hệ giáo trình → cấp → bài, mỗi học viên một bộ;
   API trả nội dung theo bộ được gán, bảo toàn tiến độ và xác thực quyền ở server. Phải thiết kế
   migration dữ liệu/tiến độ đang có trước khi chuyển, không chỉ lọc ở frontend.
3. API đọc link affiliate đang bật, đúng vị trí/ngày hiệu lực; dữ liệu hoa hồng được nhập/đồng bộ có
   mốc đối soát và thời điểm cập nhật; thống nhất dùng hoa hồng nào để lập quỹ/thưởng.
4. Chốt thể lệ thi, thời gian, phân hạng/đồng điểm, tiền thưởng rồi mới làm API cuộc thi và top thật.
5. Sau khi test bé túi đạt, mới đổi chính sách xuất hiện lại sang một ngày theo yêu cầu tiếp theo.

## Kiểm tra

- Tự động: `cd web`, chạy `npm run check`, `npm run lint`, `npm test`, `npm run build`.
- Quy tắc một giáo trình/người được kiểm trong `web/src/lib/curricula/demo.svelte.test.ts`.
- Kịch bản UI: `KICH-BAN-TEST.md` phần I (quỹ, top, bé túi; đã chạy khổ giả lập ngày 06/11, chưa thử thiết bị thật).
  Không coi build/test tự động là bằng chứng đã kiểm hình ảnh hay chuyển động trên thiết bị thật.
  Chưa có checklist riêng cho `/admin/curricula`.
- Trước commit phải chạy thêm PHPUnit theo `CLAUDE.md`. Không sửa PHP/API trong đợt giao diện này.
