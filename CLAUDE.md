# CLAUDE.md — đọc cái này trước

Zìkǎ (字卡): web học tiếng Trung theo cấp HSK 1→6, có 6 phần học và khu quản trị.
SvelteKit 2 + Svelte 5 (SPA tĩnh) · PHP 8.4 thuần, không framework · MariaDB.

Đọc thêm khi cần: [README.md](README.md) (cấu trúc, đưa lên hosting, SMTP, Google),
[DECISIONS.md](DECISIONS.md) (vì sao chọn cách làm đó), [CHAY-O-MAY.md](CHAY-O-MAY.md)
(chạy trên máy Windows). Đặc tả gốc nằm ở `../zika-spec/`, **không** nằm trong repo.

## Chạy

Cần 3 thứ chạy cùng lúc. Nhanh nhất: bấm đúp `dev\CHAY-TAT-CA.cmd`.

| Phần | Cổng | Lệnh |
|---|---|---|
| MariaDB | 3306 | `"D:/zika-devtools/mariadb/bin/mariadbd.exe" --defaults-file="D:/zika-devtools/mariadb-data/my.ini" --console` |
| API PHP | 8787 | `cd api && "D:/zika-devtools/php/php.exe" -S 127.0.0.1:8787 dev-router.php` |
| Web | 5173 | `cd web && npm run dev` |

Mở http://localhost:5173. Vite proxy `/api`, `/go`, `/uploads` sang 8787.

Tài khoản mẫu, mỗi vai trò một: `admin@zika.local`, `bientap@zika.local`, `hocvien@zika.local`,
mật khẩu `Zika@2026`.
Quên thì `cd api && php app/tools/install.php --reset-demo-password`.

Email ở máy dev không gửi thật, ghi vào `api/app/storage/logs/mail-*.log`.

Chạy migration mới: `cd api && php app/tools/install.php --migrate`. Không có tham số (kể cả `--help`) là cài đặt thật: ở máy dev nó đặt lại mật khẩu và vai trò 3 tài khoản mẫu.

## Kiểm tra trước khi commit — bắt buộc chạy hết

```bash
cd web && npm run check && npm run lint && npm test
```

```bash
cd api && "D:/zika-devtools/php/php.exe" app/vendor/bin/phpunit -c phpunit.xml
```

Mốc hiện tại: web **0 lỗi 0 cảnh báo**, lint sạch, 109/109 test; API 201/201; `python data/nap-sach/test_doc_edge.py` 12/12.
Đừng commit khi còn đỏ hoặc còn cảnh báo.

## Bản đồ nhanh

```
web/src/routes/          màn hình (SvelteKit)
web/src/lib/components/  component dùng chung (Select, Modal, Icon, Switcher…)
web/src/lib/admin/       riêng khu quản trị (menu.ts, api.ts, LessonHeader.svelte)
web/src/lib/admin/import/ nhập Excel cho 6 phần soạn bài — xem bẫy số 6
web/src/lib/curricula/   màn Giáo trình + Phân quyền học (giáo trình → cấp → bài, API thật)
web/src/lib/auth/        routes.ts — toàn bộ luật chặn và điều hướng theo vai trò
web/src/lib/stores/cap.svelte.ts  cách gọi cấp theo giáo trình ("HSK 1" / "Quyển 1"), màn học lấy chữ từ đây
web/src/lib/learn/che-do/  3 chế độ luyện ở màn Lật thẻ (Ghép cặp, Trắc nghiệm, Gõ chữ)
web/src/routes/learn/chat/  Quán trà — trò chuyện chia bàn theo chủ đề (hỏi tin mới 4 giây/lần), quản trị ở /admin/chat
web/src/lib/learn/sach.ts  giáo trình /learn/books: học viên thấy bộ sách của giáo trình mình (Hán ngữ 1–6 hoặc HSK 1–3)
api/app/src/Services/BookAccess.php  ai đọc được trang nào: điểm mở bài, bài đã mở, mã ký ?t= (xem bẫy số 9)
web/src/lib/learn/giao-trinh/  màn đọc: mục lục, thanh phát file nghe, đáp án — dữ liệu từ sach.json (data/giao-trinh/dung-sach.py)
data/han-ngu/            nạp giáo trình Hán ngữ 6 tập: chép từ ảnh → sinh → nạp → giọng → quét → gói server
web/src/app.css          tokens + class dùng chung TOÀN CỤC (xem bẫy số 1); cuối file là khung học `zk-*`
api/app/src/             Controllers/, Services/, Core/
api/app/config.php       bí mật (DB, Google, SMTP) — gitignore, KHÔNG commit
```

Ba vai trò: `learner`, `editor`, `admin`. Quyền admin cần cả `users.role = 'admin'` **và** email nằm trong `admin_emails` của
`config.php` (rỗng = không giới hạn); vai trò dùng để phân quyền lấy qua `UserService::withEffectiveRole`, đừng đọc thẳng cột `role`.

Giáo trình là cha của bài: truy vấn bài/từ cho người học phải lọc `curriculum_id` (`Services/Curricula.php`). Học viên chưa phân quyền (`users.curriculum_id` NULL, cả tài khoản cũ lẫn mới) học giáo trình bật `curricula.is_default`, hiện là
Hán ngữ 6 tập; lấy bằng `Curricula::defaultId()`, đừng coi id 1 là mặc định. Giáo trình số 1 (`Curricula::HSK_ID`, "Giáo trình HSK 3.0")
giữ dữ liệu HSK gốc, chỉ ai được gán mới học, không xoá được.

## Bẫy — đọc kỹ, đã sa vào thật

### 1. Trùng tên class với `app.css`

`app.css` có nhiều class dùng chung (`.pick`, `.who`, `.opt`, `.bar`, `.empty`, `.hl`,
`.row`, `.card`, `.tag`, `.inp`, `.side`, `.block`, `.tian`…). Svelte thêm hậu tố
`.svelte-hash` nên luật cục bộ thắng — **nhưng chỉ ở thuộc tính nó có đặt**. Thuộc tính
nào luật toàn cục đặt mà cục bộ bỏ trống thì vẫn rò xuống.

Đã gây 4 lỗi thật: `.pick` tô viền mọi `<span>` con làm vỡ thẻ thông báo; `.who` ép khung
tên thành hình tròn 40×40; `.bar` ép thanh công cụ cao 8px `overflow: hidden` cắt mất 76px
nội dung; `.opt` thêm viền và đổ bóng cho từng dòng dropdown.

**Trước khi đặt tên class mới trong `<style>` của component, grep `app.css` xem đã có chưa.**
Nguy hiểm nhất là luật toàn cục nhắm vào con cháu (`.pick span`) — style cục bộ không với tới.

### 2. Đổi tham số route thì SvelteKit không dựng lại trang

Đi từ `/admin/lessons/1/exercises` sang `/admin/lessons/16/exercises` là **cùng một route**,
component không remount nên `onMount` không chạy lại → URL đổi mà nội dung vẫn là bài cũ.
Dùng `$effect` bám theo tham số:

```ts
$effect(() => {
  void lessonId;   // tạo phụ thuộc; ESLint chặn câu lệnh trơ nên phải có `void`
  void load();
});
```

Đổi tab (khác slug) thì remount bình thường, không cần lo.

### 3. Không dùng `<select>` nữa

Danh sách bung ra của `<select>` do hệ điều hành vẽ, CSS không đụng được. Dùng
`web/src/lib/components/Select.svelte`. Panel của nó là `position: fixed` (không phải
`absolute`) vì `.modal` có `overflow-y: auto` sẽ cắt mất panel.

### 4. PHP: dùng `Clock`, đừng dùng `NOW()` của SQL

`NOW()` lấy giờ máy chủ database nên bỏ qua `Clock::freeze()`, làm test phụ thuộc ngày chạy
thật. Tính mốc thời gian bằng `Clock::sql(Clock::now()->modify('-N days'))` rồi bind vào.

### 5. Điều hướng theo vai trò nằm một chỗ

`web/src/lib/auth/routes.ts`: `homePath(role)`, `canView(role, path)`, `landingPath()`.
Đừng hardcode đường dẫn đích ở chỗ khác. Admin → `/admin`, editor → `/admin/lessons`,
learner → `/learn/flashcards`. Guard thật nằm ở `web/src/routes/+layout.svelte`.

### 6. Nhập Excel: đọc file ở trình duyệt, không đọc ở PHP

`web/src/lib/admin/xlsx.ts` tự đọc/ghi `.xlsx` (ZIP + XML, dùng `DecompressionStream`), nên hosting
không phải cài PhpSpreadsheet. Mỗi phần khai báo một `ImportSpec` trong `web/src/lib/admin/import/`:
cột, dòng mẫu, và hàm đổi bảng thành dữ liệu API. Hộp thoại `ImportModal.svelte` không biết gì về
từng phần — thêm bộ nhập mới chỉ là thêm một file spec.

Lọc ở trình duyệt **không thay** cho kiểm tra ở server: 4 endpoint `*/bulk` vẫn chạy
`QuestionPayload::clean()` / `Validator` như khi soạn tay, dòng nào sai thì bỏ qua kèm số dòng
chứ không làm hỏng cả mẻ.

### 7. Vite ở máy dev đôi khi bỏ lỡ file vừa sửa

Sửa file lúc trang đang mở (nhất là khi nhiều tiến trình cùng ghi) có lúc Vite vẫn phát CSS cũ của
component — giao diện "không ăn" dù code đúng, tải lại trang cũng không hết. Chạm lại file (`touch`)
hoặc khởi động lại `npm run dev`. Bản build không bị.

### 8. `structuredClone` không sao chép được `$state`

Phần tử lấy ra từ mảng `$state` là proxy của Svelte 5, `structuredClone(proxy)` ném `DataCloneError`. Ở trang soạn Ngữ pháp lỗi
này rơi vào `catch` của `load()` nên trang báo "0 điểm" dù API trả đủ. Lấy bản sao bằng `$state.snapshot(x)`.

### 9. Giáo trình: chặn ở server, mọi đường dẫn file cần mã ký

Ảnh trang, trang đáp án, file nghe đi qua `BookController`, kiểm `BookAccess` (bài đã mở) **và** mã `?t=` của
chính tài khoản. Thêm lối mới trả file sách thì phải gọi cả hai, đừng chỉ dựa vào giao diện ẩn nút. Điểm chỉ cộng ở `/go`
(`SupportController::go`); test nhớ `site_settings` giữ nguyên giữa các test nên phải đặt lại `support`/`points` trong `setUp`.

## Nếp viết

- **Tiếng Việt** cho chữ hiển thị, comment và thông điệp commit. Tên biến/hàm có thể tiếng Việt khi rõ nghĩa hơn.
- Màn học mới dùng khuôn chung: `DauBai.svelte` + một `section.block.zk-khung` (xem màn Lật thẻ theo bài).
- Thanh đầu trang máy tính của người học **luôn** là `lib/components/Topbar.svelte` (đường dẫn truyền làm nội dung con,
  điểm mở bài, Quỹ tháng, Top chăm học, chuông, Chuyển nhanh, tài khoản). Đừng tự viết `<header class="topbar">` trong màn mới:
  trước 08/11 mỗi màn một kiểu, người dùng báo thiếu menu. Ngoại lệ duy nhất: màn đang thi (`TestRunner`).
  Điện thoại **luôn** là `lib/components/MTopbar.svelte` (dòng 1 giống hệt mọi màn; nút lùi + tên màn ở dòng 2 qua
  `back`/`title`/`sub` hoặc nội dung con). Hai thanh cùng thứ tự: điểm mở bài, Quỹ tháng, Top chăm học, chuông,
  Chuyển nhanh, tài khoản. Không đặt logo "字 Zìkǎ" ở thanh đầu trang nào (người dùng bỏ 08/11).
- Dấu ấn chữ Hán dùng lại, đừng tự chế kiểu khác: `ChuDien.svelte` (chữ trong ô chữ điền), `.dau-xong` (triện "完" bài đã xong),
  `.lv-nen` + `.lv-hoa` với `SO_HAN` trong `lib/learn/hoa-tiet.ts` (hoạ tiết số Hán của cấp).
- Mỗi màn phức tạp tách trạng thái ra `state.svelte.ts` (class runes), bố cục máy tính và điện thoại **dùng chung** trạng thái đó — kéo cửa sổ qua 768px không mất chữ đang gõ.
- Bố cục điện thoại/máy tính chọn bằng `media.isMobile` (quản trị dùng `media.isAdminMobile`, ngắt ở 900px).
- Comment giải thích **vì sao**, không mô tả lại code.
- Svelte 5 runes (`$state`, `$derived`, `$effect`, `$props`), không dùng cú pháp Svelte 4.

## Việc còn treo

- **Đã lên server 08/11/2026 (đã kiểm trên web thật):** code `ad9032a` (Top chăm học/Lịch sử thi số thật, sửa 2 lỗi giao diện),
  khoá quản trị theo `admin_emails` trong `config.php` server (2 email: phuongnv24012001, nvpchaytran), ví dụ lật thẻ Hán ngữ đủ
  2.990 từ + 586 file giọng. 496 câu tự soạn ở `data/han-ngu/vi-du/` (luật và cách chạy ở
  [data/han-ngu/README.md](data/han-ngu/README.md)). Sửa ví dụ lần sau: `nap_vi_du_hn.py` ghi SQL chỉ thay ví dụ, giữ bài và
  tiến độ; đừng chạy lại `02-han-ngu.sql` để cập nhật ví dụ.
- **Mở bài giáo trình bằng điểm + chặn tải (08/11/2026), đã lên server 08/11** (migration 019–021, code `084c1e3`), xem NHAT-KY 08/11 và phần O
  của KICH-BAN-TEST. Còn: ở quản trị gắn lại link Shopee (DB server đã xoá link) thì bé túi mới hiện.
  **Không bán sách**: phần gói học/chuyển khoản đã làm rồi bỏ (rủi ro pháp lý), đừng thêm lại khi chưa có giấy phép bản quyền.
  **Điểm và Shopee đã tách riêng (08/11):** nút "Nhận điểm" gọi `/learn/points/daily`, lần đầu +3, các ngày sau +1;
  bé túi và mọi `/go` chỉ mở link/ghi lượt ủng hộ, không cộng điểm. Chế độ test điểm chỉ làm mỗi lần bấm **Nhận điểm** được +1
  và trên server phải để tắt. Bấm bé túi hoặc link Quỹ tháng thì bé nghỉ đến 0h hôm sau, nhớ theo tài khoản ở server (`clickedToday`); chưa lên server.
- **Server thật đã lên bản 08/11/2026 (đã kiểm):** DB xoá hết chỉ giữ tài khoản, nạp lại 2 giáo trình; code, âm thanh,
  ảnh sách đã lên. Kiểm trên web thật: 116 bài × 6 phần giống local (bỏ qua `?v=` nét chữ và `updatedByName`), 21.313
  file âm thanh/nét chữ đủ và đúng cỡ, 43 màn chạy ở máy tính/máy tính bảng/điện thoại. Lúc nạp, `01-cau-truc.sql` dừng
  ở migration 013 vì bảng words trên server có chỉ mục khác (`uq_words_lesson_hanzi`, `ix_words_level_hanzi`); đã sửa
  tay rồi chạy tiếp — xem NHAT-KY 08/11. **Đừng dùng gói giọng 03/11 cũ** (`cap-nhat-giong-moi-20261103-0355.zip`).
- **Giáo trình kèm file nghe + đáp án (08/11/2026):** học viên thấy bộ sách theo giáo trình học (Hán ngữ → 6 quyển Hán ngữ),
  thanh phát file ghi âm, đáp án Q3–6. Đã lên server. Chưa thử màn hình khoá điện thoại thật (GT18). Xem NHAT-KY 08/11.
- **Hán ngữ đúng như sách + tiếng Anh (08/11/2026):** sách là chuẩn — bản chép đã soát lại cả 76 bài theo ảnh, web khớp
  bản chép từng chữ (migration 017). Lỗi in của sách và chỗ dịch sai đã sửa trên web, chữ sách in giữ ở `loi_sach`.
  Tiếng Anh chỉ hiện ở Lật thẻ (ngữ pháp có dữ liệu tiếng Anh nhưng không hiện). Mọi phần nghe của Hán ngữ là giọng tự tạo,
  kể cả nút Nghe bài khoá (`gan_hn.py` `DUNG_BANG = False`). Sách ở `HanNgu/New folder/` → đặt `ZIKA_HANNGU_DIR` khi đọc PDF
  (thư mục "File nghe…" vẫn ở `HanNgu/`). Xem NHAT-KY 08/11.
- **Giáo trình "Hán ngữ 6 tập" (07/11/2026):** 76 bài đã nạp ở local, quy trình ở [data/han-ngu/README.md](data/han-ngu/README.md).
  Chỉ có 4 phần, Bài tập và Kiểm tra cố ý để trống. Gói server ở `../dong-goi-han-ngu/`, **chưa đưa lên**: cần code mới trước,
  rồi chạy `01-cau-truc.sql` (migration 006–018, đã chạy thì tự bỏ qua) và `02-han-ngu.sql`, chép zip uploads, xoá `storage/cache/content`.
  Màn học gọi cấp theo `curricula.level_label` qua `lib/stores/cap.svelte.ts` ("Quyển n"), đừng viết cứng "HSK n" ở màn học; riêng "Thi thử HSK n" giữ vì là đề HSK dùng chung. Góp ý ở màn học đã nối 08/11 (Chuyển nhanh → Góp ý, `POST /feedback`, học viên đọc trả lời trong hộp).

- **Giao diện giáo trình admin + quỹ thưởng/top chăm học/bé túi Shopee (05–06/11/2026):** đọc
  [BAN-GIAO-GIAO-DIEN.md](BAN-GIAO-GIAO-DIEN.md) trước khi tiếp tục. Chỉ frontend/dữ liệu mẫu;
  mỗi học viên một giáo trình do admin gán, không thêm màn chọn giáo trình cho học viên.
  Bé túi hiện trên mọi màn `/learn/`, kể cả đang làm bài (người dùng chấp nhận che nút, 06/11); bấm xong thì nghỉ đến 0h hôm sau (08/11).
  Giáo trình và tab Đề thi đã có API thật; đề thi thử cuối cấp vẫn dùng chung mọi giáo trình.
  Link Shopee đã có API thật (Icon 1 link, Quỹ tháng nhiều link, công tắc ở `/admin/support`), tiền quỹ do admin nhập tay ở cùng màn đó;
  cuộc thi tạo ở `/admin/contests`, chọn đề từ tab `/admin/exams` (chỉ cần tên, câu nạp sau), đề Nháp chỉ làm được trong giờ thi, hết giờ tự nộp, xếp hạng theo câu đúng rồi thời gian, mỗi người 1 lượt.
  đăng ký trước giờ thi mới được làm, cơ cấu giải chỉnh ở cùng màn. Trang soạn đề là bản tạm. **Server thật còn cần migration 007 đến 018 và xoá `storage/cache/content`** rồi gắn link Icon thì bé mới hiện. Top chăm học và Lịch sử thi lấy số thật từ 08/11; không viết lại dữ liệu minh hoạ vào giao diện người học. Checklist: phần I, J, K, L của [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

- **ĐANG DỞ — giọng đọc mới toàn bộ + sửa UI/UX (03/11/2026), chỉ còn đưa lên server:** mọi file đọc
  mẫu chuyển sang giọng Microsoft (edge-tts), câu thoại giọng riêng từng nhân vật, `TTS_VERSION = 6`;
  quy trình [HUONG-DAN-GIONG-DOC.md](data/nap-sach/HUONG-DAN-GIONG-DOC.md). Local: 10.343 file, 10.077 đạt,
  266 chờ người nghe (`ra/nghe-tay-20261103.zip`), 0 lỗi; quét qua web 8.581 âm thanh + 697 nét chữ **0 lỗi**;
  13 khoá băng thật lệch sách đã thay (`bang_lech_sach.json`). Gói `release/cap-nhat-giong-moi-20261103-0355.zip`
  (web + mp3 + `cap-nhat-am-thanh.sql`) **thay** gói `cap-nhat-lat-the-nam-20261102.zip` (chưa từng lên server).
  Phần web của gói này **đã cũ**: đóng trước khi bỏ vuốt ở lật thẻ, đổi giáo trình sang cuộn dọc,
  khoá giáo trình chỉ cho quản trị, lật thẻ nền trắng + ẩn lời giải ví dụ (03–04/11) — đóng lại cả
  web, PHP **và `.htaccess` gốc** (luật chặn `uploads/giao-trinh/`) trước khi đưa lên, rồi chạy
  **migration 006** (hội thoại mặc định ẩn pinyin/nghĩa).
  Còn: upload + import SQL nhỏ + xoá `storage/cache/content` theo
  [HUONG-DAN-SERVER.md](data/nap-sach/HUONG-DAN-SERVER.md), rồi kiểm web thật. Kiểm 23:48 02/11: web thật
  vẫn bản build 07:06 (`version.json` = `1793577989636`). Chưa có kết nối hosting; giữ mục này đến khi test server.

- **Ví dụ lật thẻ + bộ thủ + chuông thông báo (02/11/2026)** (giọng Kokoro của lần này đã thay ở trên): xem
  [NHAT-KY-CAP-NHAT.md](NHAT-KY-CAP-NHAT.md) — quy trình nạp, lần quét lỗi gần nhất, kịch bản test.
  `speech.ts` tự tìm file `/uploads/tts/<giọng>/<sha1 câu chữ>.mp3` nên đọc chữ ở đâu cũng có giọng
  người; thêm nội dung mới thì chạy lại bước 5 trong nhật ký, không thì rơi về giọng trình duyệt.
  Server thật cần migration 004 + 005 và chép `uploads/tts`, `uploads/audio`, `uploads/hanzi`.

- **Giáo trình HSK 1–3 (40 bài) đã nạp đủ 6 phần ở máy local (02/11/2026)** bằng bộ mới
  [data/nap-sach/](data/nap-sach/README.md): chép từ ảnh trang sách ra `bai/*.json` → `sinh.py` →
  `nap.py` (gọi API quản trị, giữ đủ lớp kiểm tra) → `doi_chieu.py` + `doi_chieu_sach.py`.
  Bộ tách cũ `data/hsk1/tach-pdf.py` và hai file TIEN-DO cũ coi như đã thay. Nội dung sách
  (`bai/`, `ra/`, `anh/`) không đưa lên git. **Web thật đã nạp bản mới ngày 02/11**, đối chiếu
  giống local ở lần triển khai trước (xem nhật ký); lần này chỉ còn cập nhật tốc độ giọng đọc.

- **Hai đường nạp nội dung, dùng đường nào cũng được:**
  - **Nhập Excel** (bẫy số 6) — đi qua API nên giữ đủ 3 lớp kiểm tra. Chỉ chạy ở máy local.
  - **Sinh SQL** `data/hsk1/xuat-sql.py` → `data/hsk1/sql/*.sql` → phpMyAdmin. Dùng khi đẩy lên
    hosting. Mất 3 lớp kiểm tra, đã bù 2 (đếm `cnt_*` và trỏ nét chữ), còn `QuestionPayload::clean()`
    thì chưa — xem `data/hsk1/TIEN-DO.md` Phần B.

- **Màn đọc giáo trình** (`/learn/books`): 9 quyển (Hán ngữ 1–6, HSK 1–3) đọc theo ảnh từng trang, kèm file nghe và
  đáp án khi sách có. Dựng bằng `data/giao-trinh/dung-sach.py` vào `<uploads>/giao-trinh/<mã>/` (có `sach.json`),
  **không có trong git** (~740MB) — xem mục "Giáo trình" ở [README.md](README.md) trước khi triển khai.

- API còn khoảng 56 chỗ dùng `NOW()`, phần lớn vô hại (truy vấn thống kê, hoặc đặt mốc thời
  gian lúc ghi). Chỉ thành vấn đề khi hàm đó có test đóng băng đồng hồ **và** so sánh với một
  mốc cắt — như `MaintenanceService` đã dính. Gặp trường hợp đó thì chuyển sang `Clock`.
- 9 chỗ class trùng tên còn lại là **cố ý** dùng lại utility (`.inp`, `.side`, `.block`,
  `.tian`, `.tag`, `.row`, `.mtop`, `.mcontent`, `.lbl`) — để nguyên, đừng "sửa".
- Đăng nhập Google chỉ chạy khi `config.google.client_id` có giá trị; máy nào chưa điền thì
  nút tự ẩn, đó là chủ ý chứ không phải lỗi.
- **SMTP trên server thật đang để trống** nên email xác minh và quên mật khẩu không gửi được,
  chỉ ghi vào `storage/logs/mail-*.log`. Xem README mục "Gửi email thật (SMTP)".
- **Đẩy code lên hosting có 4 cái bẫy đã sa vào thật** (Paste của 1Panel thay cả thư mục nên xoá
  mất `config.php`; `api/index.php` trong repo trỏ sai đường dẫn; đừng copy `uploads`; service
  worker cache cả đường dẫn 404). Đọc README mục "Đưa lên hosting" trước khi deploy.
