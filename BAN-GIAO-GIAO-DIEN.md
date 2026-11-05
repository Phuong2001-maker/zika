# Bàn giao giao diện — 05/11/2026

## Phạm vi và quyết định đã chốt

Đây là **bản local để người dùng duyệt**, chưa triển khai hosting. Link Shopee, tiền quỹ tháng, cuộc thi và bảng xếp hạng cuộc thi đã có backend thật (06/11);
giáo trình và top chăm học vẫn chưa nối backend. Không coi số liệu mẫu là dữ liệu thật.

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

- `MonthlyCommunity.svelte`: một nút gộp túi và số tiền quỹ, cùng cúp Top chăm học, cạnh nút chuyển nhanh,
  chuông và tài khoản. Có ở `Topbar.svelte` (desktop dùng chung), header Bàn học và header các màn chọn cấp
  (`PartHome.svelte`) trên điện thoại. Điện thoại ở màn chọn bài và màn đang làm bài **cố ý không có cụm này**:
  header đã có nút quay lại và tên bài, không đủ chỗ và cũng không nên làm người học phân tâm.
- Bỏ logo/chữ Zìkǎ ở Topbar dùng chung, header Bàn học và các màn chọn cấp trên điện thoại.
- Rà UI/UX 06/11: chữ thanh đầu trang và hộp thoại tăng lên (không còn chữ 8–10px), nút trên điện thoại rộng hơn nhưng
  vẫn vừa khổ 320px; bỏ tiêu đề lặp và khung lồng khung ở hộp quỹ; tháng trong tiêu đề lấy từ biến thay vì ghi cứng;
  ba hạng đầu có huy chương.
- Trên điện thoại, 3 hộp quỹ, cuộc thi, xếp hạng treo ngay dưới thanh đầu trang (`Modal` với `anchor="top"`) thay vì trượt
  từ đáy, vì người dùng thấy hộp quá xa nút vừa bấm. Các hộp thoại khác của web vẫn trượt từ đáy.
- **Đã bỏ hộp Shopee riêng** theo yêu cầu người dùng (06/11): bấm túi hay số tiền đều mở hộp quỹ "Cùng học, cùng tiến bộ",
  không tách lại thành hai hộp. Câu "Bấm liên kết chưa làm tăng quỹ" nằm trong hộp đó nên cũng mất theo.
- Đã bỏ khối dài cuối Bàn học. Bấm quỹ mở hộp thông tin; không lặp lại bảng top chăm học trong hộp này.
- Bấm cúp mở top 10 chăm học, có tab Cuộc thi ở trạng thái chưa có kết quả. Top chăm học là theo
  phút học trong tháng, cùng thời gian thì dự kiến ưu tiên số bài hoàn thành; không đồng nhất với top thi.
- **Tiền quỹ do admin nhập tay** ở `/admin/support` (khối Quỹ tháng), người dùng chủ động chọn cách này thay vì đồng bộ Shopee.
  Danh sách học viên/phút học vẫn **hardcode để minh họa**. Chưa chốt tỷ lệ chia tiền thưởng.
- Tháng được ghi lúc bấm Lưu, nên sang tháng mới mà chưa nhập lại thì vẫn hiện đúng tháng của số đó, không tự đổi.
  Chưa nhập hoặc để trống rồi lưu thì người học thấy "Đang cập nhật". Lưu trong `site_settings` khoá `fund`, API `PUT /admin/support/fund`,
  người học đọc qua `monthlyFund` trong `GET /support/links`.
- Mục tiêu về sau: mỗi tháng tổ chức thi giải đề, top đầu được thưởng dựa trên tổng hoa hồng thực tế đã đối soát;
  không suy tiền từ lượt bấm.
- **Link Shopee đã nối thật (06/11), xem mục "Link Shopee" ngay dưới.** Top vẫn là số minh hoạ, chưa có tích hợp
  tiền hoa hồng. Không gọi API admin từ tài khoản học viên và không tự thay bằng link Shopee chung làm mất affiliate.

### Cuộc thi: tạo bài thi, đếm ngược, vào thi

- Admin ở `/admin/contests` (chỉ quản trị): tên bài thi, **giờ bắt đầu, số phút thi**, cấp, đề. Giờ kết thúc do server tính.
  Đề chọn từ **Đề thi thử** có sẵn hoặc "+ Tạo đề mới" (lấy N câu từ bài kiểm tra của cấp).
- **Trang soạn đề là bản tạm** (`/admin/mock-tests/{id}`, nút Soạn đề): danh sách câu, thêm tay, nhập Excel, dùng chung dạng câu với bài kiểm tra.
  Người dùng sẽ tự lấy đề từ bên ngoài và sửa lại trang này sau, nên cố ý chưa có khung HSK, phần Nghe/Đọc/Viết hay ảnh trong đề.
- **Đăng ký thi:** chỉ ai đăng ký trước giờ bắt đầu mới được làm bài (server chặn 403), huỷ được trước giờ đó. Danh sách ở tab Đăng ký của Top chăm học,
  admin xem cả email bằng nút số người trong bảng.
- **Cơ cấu giải:** mẫu Nhất 50%, Nhì 25%, Ba 15%, hai giải khuyến khích 5%. Admin sửa, thêm, bớt ở `/admin/contests`, tổng không quá 100%.
  Số tiền = phần trăm của Quỹ tháng, làm tròn xuống nghìn đồng. Hiện ở hộp cuộc thi. Chưa tự trao thưởng và chưa gắn tiền vào bảng xếp hạng.
- **Dùng lại đề thi thử** nên không phải viết lại bộ chấm điểm và màn làm bài. Đề để **Nháp** thì không hiện ở mục Thi thử, người học chỉ
  vào được trong giờ thi (`ContestService::isOpen`, gác ở `TestService::source`). Đã vào làm thì hết giờ vẫn chấm và xem lại được.
  Đề đang Công khai thì admin được cảnh báo vì người học làm trước giờ thi được. Không xoá được đề đang gắn bài thi.
- Người học thấy ở hộp quỹ và hộp cuộc thi: nhãn **Sắp diễn ra** kèm đồng hồ ngày, giờ, phút, giây (`ContestCountdown.svelte`).
  Về 0 thì tự chuyển sang **Đang diễn ra**, đồng hồ đếm tới giờ đóng, nút thành **Vào thi ngay**. Chưa có bài thi thì nhãn "Chưa có lịch".
  API `GET /contest/current` trả số giây còn lại chứ không trả giờ, để đồng hồ máy người học sai cũng không đếm lệch; mã đề chỉ có khi đã mở thi.
- **Hết giờ tự dừng và nộp** dù chưa bấm nộp: mọi người cùng dừng lúc "giờ bắt đầu + số phút thi", vào muộn thì còn ít thời gian hơn. Màn khoá lại bằng hộp "Hết giờ", nộp các câu đã làm, mất mạng thì tự thử lại. Đóng tab trước thì server chấm theo câu
  đã lưu, khi có người mở bảng xếp hạng hoặc khi cron chạy. Quá hạn (cộng 10 giây bù độ trễ mạng) thì server từ chối lưu thêm.
- **Bảng xếp hạng** (`GET /contest/ranking`, tab Cuộc thi trong Top chăm học, top 50 và luôn có hạng của mình): đúng nhiều hơn xếp trên,
  bằng nhau thì làm nhanh hơn, vẫn bằng thì nộp sớm hơn. Thời gian tính tới hạn nộp chứ không tới lúc chấm. Đang thi thì bảng cập nhật mỗi lần mở.
- **Mỗi người 1 lượt** (đề xuất đã nêu với người dùng, họ chưa phản đối): `UNIQUE (contest_id, user_id)`. Đã nộp thì nút thành Xem bảng xếp hạng, vào lại chỉ thấy kết quả.
  Thi thử thường không bị giới hạn. **Cuộc thi còn mở thì giấu đáp án** để người nộp sớm không truyền cho người chưa thi; hết giờ mới hiện.
- Hai bài thi trùng giờ: hộp quỹ và bảng xếp hạng cùng chọn bài bắt đầu sớm nhất còn mở. Nên tránh đặt trùng giờ, form chưa chặn.
- **Chưa làm:** trao thưởng theo hạng; tính top chăm học thật (tab Chăm học vẫn minh hoạ). Bảng xếp hạng tính cả tài khoản quản trị nếu họ vào thi.
- **Migration 009** (`009_cuoc_thi.sql`) tạo bảng `contests`; **010** (`010_lan_thi_cuoc_thi.sql`) thêm `contest_id`, `correct_count` vào `test_attempts`;
  **011** (`011_dang_ky_thi.sql`) thêm `duration_min` và bảng `contest_registrations`. Máy local đã chạy, server thật chưa.

### Link Shopee: quản trị và người học

- Admin ở `/admin/support`, form chỉ còn link, tên và **Hiện ở đâu** (Icon, Quỹ tháng). Đã bỏ "Hiện từ ngày", "Đến ngày", "Bật ngay sau khi lưu":
  link mới luôn bật; không dùng nữa thì tắt bằng công tắc trên dòng đó hoặc xoá.
- **Icon** là bé túi di chuyển, **chỉ 1 link**. Khi link khác đang giữ Icon, cả 2 ô vị trí bị khoá mờ và link này mặc định ở Quỹ tháng.
  Muốn đổi Icon thì sửa link đang giữ, bỏ chọn Icon trước. Server cũng chặn y như vậy (`OpsController::saveLink`), chỉ link đang giữ Icon mới sửa được 2 ô này.
  **Quỹ tháng**: hộp quỹ chỉ có một dòng "Ghé Shopee" kèm số sản phẩm; bấm vào mở màn sản phẩm có ô tìm (gõ không dấu được), theo `sort_order`.
  Hình sản phẩm chưa có: cột `image_path` có sẵn nhưng form admin chưa có ô tải ảnh.
- Công tắc **Hiện link ủng hộ cho người học** tắt thì API trả rỗng: bé túi không hiện, hộp quỹ không có phần Shopee, `/go` không chuyển
  sang Shopee nữa. Bật thì hiện đúng theo vị trí. Tab học tự cập nhật khi quay lại tab hoặc mở hộp quỹ.
- Bé túi chỉ hiện khi có link Icon. Bấm bé hoặc lời nhắn mở link ở tab mới rồi bé ẩn. Cụm quỹ/top trên thanh đầu trang vẫn hiện dù công tắc tắt,
  vì đó là phần cuộc thi, không phải link.
- API: `GET /api/support/links` (đăng nhập), `POST /api/support/view` body `{place: icon|fund}`, `GET /go/{id}?place=icon|fund`.
  Giao diện chỉ thấy đường `/go`, không thấy link thật. Link tắt hoặc đã xoá thì `/go` đưa về Bàn học. `/go` công khai nên chỉ đếm lượt bấm của
  người đã đăng nhập. Mã: `SupportController.php`, `web/src/lib/learn/support-links.svelte.ts`, test `api/tests/Unit/SupportLinksTest.php`.
- Bảng admin cộng thêm sự kiện thô hôm nay, vì `affiliate_daily` chỉ có số tới hôm qua. Thẻ thống kê ở trang Tổng quan admin vẫn chỉ đọc `affiliate_daily`.
- **Migration 007** (`007_vi_tri_link_shopee.sql`): thêm `place_icon`, `place_fund`; bỏ `place_desk`, `place_done`, `starts_on`; link đang gắn vị trí
  cũ chuyển sang Quỹ tháng; Icon không tự gán nên **sau khi chạy phải vào admin gắn 1 link Icon thì bé mới hiện**.
  **Migration 008** (`008_bo_ngay_het_han_link.sql`): tắt link đã quá hạn rồi bỏ `ends_on`.
  Máy local đã chạy cả hai. Server thật chưa: `php tools/install.php --migrate`. Luật `/go` đã có sẵn trong `deploy/htaccess-root`.

### Bé túi Shopee chạy quanh giao diện học

- `ShopeeBuddy.svelte` gắn một lần ở layout gốc, chỉ khi đường dẫn bắt đầu `/learn/` và route được phép hiển thị.
- **Chỉ hiện ở Bàn học và màn chọn cấp**, không hiện ở màn chọn bài và màn đang làm bài vì lời nhắn từng che nút "Kiểm tra", "Bắt đầu" làm
  người học bấm nhầm. Luật ở `shopee-buddy.ts`, có test. Component vẫn sống khi sang các màn đó để bé đã ẩn thì không hiện lại.
- Túi SVG vẽ lại 06/11: mắt to chớp và nhìn theo con trỏ, má hồng, bùa tim đung đưa, rê chuột vào thì cười
  tít mắt và thả tim. **Không có bóng dưới chân và không dùng `filter: drop-shadow`**: người dùng thấy như lớp mờ
  đi theo bé nên đã bỏ, đừng thêm lại.
- Cử động: đi có nhún và nén, chân nhấc lần lượt, tay đánh ngược, quai và bùa đung đưa, người nghiêng theo đà.
  Đến nơi thì nén xuống như vừa đáp. Lúc đứng tự đổi ngẫu nhiên giữa vẫy tay, nhảy, nhún nhảy, ngó nghiêng, nháy mắt,
  xoay người (`data-gesture`, chỉ hình cử động, chữ lời nhắn đứng yên). Lời nhắn hiện tại:
  **“Chạm tớ, tớ ẩn nhé!” / “Chạm là góp quỹ thưởng rồi, không cần mua gì nhé 🧡” / “Liên kết tiếp thị”.**
  Câu giữa **người dùng đã chốt giữ ngày 06/11** dù đã được báo về quy tắc ở cuối mục này, đừng tự đổi lại.
- Chữ trôi theo hình gây hoa mắt nên **chữ chỉ hiện khi bé đứng yên**: vào trang đứng nói 12 giây, sau đó đi
  quãng ngắn (khoảng 240px, 36px/giây) rồi đứng nói 9 giây. Lúc đi chỉ có dấu tay nhỏ báo bấm được.
- Đường đi hình chữ U men theo cạnh màn hình, không đi chéo qua nội dung; điện thoại đi cao hơn thanh đáy.
- Rê chuột/focus thì dừng và hiện chữ ngay; đang nhập liệu hoặc có dialog thì tạm ẩn; có xử lý resize và
  `prefers-reduced-motion` (đứng yên, nói một lần rồi im). Rời màn thì dọn frame, timer và event listener.
- Bấm túi hoặc lời nhắn (đã bỏ dấu × ngày 06/11): mở link Icon ở tab mới, chạy hiệu ứng biến mất, không tăng tiền quỹ.
  Không còn cách ẩn bé mà không ghé Shopee, và F5 lại hiện bé: nên chốt khi làm chính sách "hiện lại sau 1 ngày".
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
3. Đưa lên server thật: chạy migration 007 đến 011, gắn link Icon, nhập số tiền quỹ, tạo bài thi.
   Nếu sau này đồng bộ hoa hồng tự động thì cần mốc đối soát và thống nhất dùng hoa hồng nào để lập quỹ/thưởng.
4. Chốt mức thưởng từng hạng; làm top chăm học thật khi người dùng yêu cầu.
5. Sau khi test bé túi đạt, mới đổi chính sách xuất hiện lại sang một ngày theo yêu cầu tiếp theo.

## Kiểm tra

- Tự động: `cd web`, chạy `npm run check`, `npm run lint`, `npm test`, `npm run build`.
- Quy tắc một giáo trình/người được kiểm trong `web/src/lib/curricula/demo.svelte.test.ts`.
- Kịch bản UI: `KICH-BAN-TEST.md` phần I (quỹ, top, bé túi; đã chạy khổ giả lập ngày 06/11, chưa thử thiết bị thật).
  Không coi build/test tự động là bằng chứng đã kiểm hình ảnh hay chuyển động trên thiết bị thật.
  Chưa có checklist riêng cho `/admin/curricula`.
- Trước commit phải chạy thêm PHPUnit theo `CLAUDE.md`. API mới có ở phần link Shopee, tiền quỹ và cuộc thi; giáo trình và top chăm học vẫn chưa có API.
