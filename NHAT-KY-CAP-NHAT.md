# Nhật ký cập nhật Zìkǎ

Mỗi lần cập nhật nội dung hoặc tính năng thì ghi thêm một mục ở đầu phần "Các lần cập nhật":
đã làm gì, đã test gì, kết quả, việc còn treo. Sau mỗi lần nạp/sửa nội dung chạy lại

```bash
cd data/nap-sach && python quet.py
```

để mục "Lần quét gần nhất" ở cuối file tự cập nhật (âm thanh, nét chữ, bộ thủ, ví dụ, hội thoại).
Kịch bản test giao diện: [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

## Quy trình nạp và kiểm tra nội dung (máy local)

| Bước | Lệnh (trong `data/nap-sach/`) | Ghi chú |
|---|---|---|
| 1. Nạp giáo trình | `python sinh.py 1 2 3` → `python nap.py 1 2 3` | xem [README](data/nap-sach/README.md) |
| 2. Ví dụ lật thẻ | soạn `vi-du/hsk*.json` → `python kiem_vi_du.py 1 2 3` (phải 0 lỗi) → `python nap_vi_du.py 1 2 3` | 2 câu/từ, HSK1 ≤ 12 chữ, HSK2 ≤ 16, HSK3 ≤ 22, chỉ dùng chữ trong từ vựng các cấp ≤ cấp đó |
| 3. Phân tích chữ (bộ thủ) | `python phan_tich_chu.py` | từ `tu-dien/` (soạn tay + Unihan/Make Me a Hanzi) |
| 4. File nghe thật | `python nghe_gan.py 1 2` → `python gan_nghe.py 1 2` | mp3 giáo trình gửi kèm, HSK 1–2 |
| 5. Giọng đọc mẫu | **một lệnh:** `powershell -ExecutionPolicy Bypass -File .\sinh-giong-doc.ps1` | xem [HUONG-DAN-GIONG-DOC.md](data/nap-sach/HUONG-DAN-GIONG-DOC.md) |
| 6. Quét lỗi | `python quet.py` | cập nhật cuối file này |

## Các lần cập nhật

### 09/11/2026 01:03 — Sự cố web thật: RewriteRule ngừng chạy, đã sửa

- Khoảng 00:56 (sau khi dọn zip thừa trên 1Panel, không liên quan) web thật: trang chủ mở được nhưng `/api/...`, `/learn/...`
  trả 404 trang LiteSpeed, ảnh sách `uploads/giao-trinh/` mở thẳng được (200). `api/index.php` gọi thẳng vẫn chạy, `config.php`
  còn; `Header` trong `.htaccess` vẫn có tác dụng — chỉ khối `<IfModule mod_rewrite.c>` bị bỏ qua. Lưu lại file không ăn.
- **Sửa:** bỏ khối `<IfModule mod_rewrite.c>` bọc quanh luật chuyển hướng trong `.htaccess` trên server (luật giữ nguyên).
  01:03 kiểm lại: `/api/me` JSON 200, `/learn/books` 200, ảnh sách thẳng 403, màn đọc Hán ngữ 1 tải ảnh trang.
- `deploy/htaccess-root` trong repo sửa giống hệt bản server, để lần đóng gói sau không mang khối bọc trở lại. README thêm bẫy 5.

### 09/11/2026 00:50 — Đã lên server: Nhận điểm riêng, bé túi nghỉ theo ngày, bỏ Chế độ test, mở cả quyển

- **Đã lên server** code `main` `9f033b5` bằng `dong-goi-code/zika-code-20261109-0045.zip` (cùng cấu trúc, cùng 403 file như
  gói 08/11 13:43; `api/index.php` bản server). Không có SQL, không đụng `config.php`. Gồm 4 mục 08–09/11 ngay dưới.
- **Kiểm web thật** bằng tài khoản quản trị: `version.json` = `1794159920009`; `PUT /admin/book-points/test-mode` trả 404,
  `POST /admin/book-points/open-book` có (403 CSRF khi gọi thiếu mã); `/learn/points` không còn `link`; `/support/links` có
  `clickedToday`. Điểm mở bài không còn công tắc test; Chi tiết học viên có Mở cả quyển 6 quyển Hán ngữ, sổ ghi "Lần đầu nhận
  điểm". Màn Giáo trình đủ 9 bìa, mọi yêu cầu 200. **Chưa thử bằng tài khoản học viên** (Nhận điểm, bé túi nghỉ sau khi bấm).
- Dòng `points` cũ trong `site_settings` của server vẫn còn nhưng code mới không đọc, để nguyên.

### 09/11/2026 — Quản trị mở cả quyển cho học viên

- Quản trị → Điểm mở bài → **Chi tiết** có phần **Mở cả quyển**: liệt kê các quyển thuộc giáo trình học viên đang học
  (Hán ngữ 1–6 hoặc HSK 1–3) kèm "đã mở x/y bài"; bấm hai lần là mở mọi bài của quyển, **không trừ điểm** (người dùng chốt).
- Không mở được quyển của bộ khác, không áp cho biên tập/quản trị (đã đọc tự do). Sổ điểm ghi một dòng 0 điểm
  "Quản trị mở cả quyển …"; nhật ký quản trị ghi `book_points.open_book`.
- Thứ tự mở bằng điểm không đổi: mở Quyển 2 khi Quyển 1 còn dở thì học viên vẫn dùng điểm mở nốt Quyển 1, rồi nhảy sang Quyển 3.
- Hàm chọn bộ sách theo giáo trình chuyển từ `BookController` sang `BookAccess::boCuaNguoi` để dùng chung. Không có migration.
- Test `BookPageTest::testQuanTriMoCaQuyenKhongTruDiem`. Trình duyệt: hộp Chi tiết hiện đủ 6 quyển Hán ngữ, bước "Bấm lần nữa
  để mở" chạy; chưa bấm mở thật trên dữ liệu local. **Đã lên server 09/11** (xem mục 00:50).

### 09/11/2026 — Bỏ hẳn Chế độ test điểm

- Web đã dùng thật nên bỏ công tắc **Chế độ test** cùng dải cảnh báo và đoạn mô tả ở Quản trị → Điểm mở bài (người dùng khoanh).
- API bỏ `PUT /admin/book-points/test-mode`, cài đặt `points.test_mode` và nhánh "coi như chưa nhận" trong `BookAccess`:
  dù DB server còn dòng `points` cũ thì code mới cũng không đọc, Nhận điểm luôn theo luật lần đầu +3, mỗi ngày +1.
- Sổ điểm: dòng cũ ghi lúc còn chế độ test (khoá `thu:…`) hiện là "Nhận điểm". Điểm đã cộng dư lúc test **không tự trừ**;
  ở local `hocvien@zika.local` còn 127 điểm — cần thì trừ tay ở bảng học viên. Đã xoá dòng `points` trong DB local.
- **Đã lên server 09/11** (xem mục 00:50).

### 08/11/2026 — Bé túi bấm xong nghỉ đến 0h hôm sau

- Bấm bé túi (hoặc một link Quỹ tháng) thì trong ngày bé không hiện lại, kể cả tải lại trang hay đổi máy: server đọc lượt bấm
  hôm nay của tài khoản trong `affiliate_events` và trả `clickedToday` ở `/support/links`. 0h hôm sau bé hiện lại.
- Không có migration; không thêm nút tắt, không đổi lời bé, không có chế độ thử cho bé (người dùng chốt). Phía quản trị
  người dùng tự sửa sau.
- Test `SupportLinksTest::testBamLinkThiBeTuiNghiDenNgayHomSau`. **Đã lên server 09/11** (xem mục 00:50).

### 08/11/2026 — Tách nhận điểm khỏi bé túi Shopee

- Nút **Nhận điểm** gọi thẳng API điểm: lần đầu +3, các ngày sau +1; bấm không mở tab hay ứng dụng Shopee.
- Bé túi và các link trong Quỹ tháng chỉ chuyển sang Shopee, ghi lượt bấm ủng hộ và không thay đổi điểm mở bài.
- Bỏ trạng thái/lời nhắn tặng điểm khỏi bé túi; sửa lời hướng dẫn ở kệ sách, bài khoá và trang quản trị điểm.
- Test API khóa hai luồng: bấm `/go` giữ nguyên 0 điểm, sau đó bấm Nhận điểm mới thành 3. Đã chạy đủ: web 0 lỗi/0 cảnh báo,
  lint sạch, 109/109; API 200/200. README mục "Điểm mở bài" sửa theo luật mới. **Đã lên server 09/11** (xem mục 00:50).

### 08/11/2026 — Bỏ dữ liệu minh hoạ; điểm mở bài + Quán trà đã lên server

- **Đã lên server** bản `084c1e3`: `cap-nhat-db-019-021.sql` (migration 019–021, chạy thử 2 lần trên bản sao DB giống server)
  + `zika-code-20261108-1118.zip`, xoá `storage/cache/content`. Kiểm web thật: `version.json` mới, API trả JSON, ảnh sách
  mở thẳng 403, Quán trà đủ 5 bàn, Chế độ test **tắt**. Chưa gắn link Shopee nên bé túi chưa hiện.
- **Bỏ dữ liệu minh hoạ** (người dùng thấy trên web thật): Top chăm học có 10 tên bịa, Lịch sử thi có 3 cuộc thi bịa
  ("Bạn" Giải Nhì 300.000đ) và form nhận thưởng nhập ngân hàng mà không gửi đi đâu.
  Nay Top chăm học tính từ phút học thật (`visits.duration_sec`, cùng nguồn tổng giờ học), chỉ học viên đang hoạt động, từ
  1 phút; Lịch sử thi lấy các cuộc thi đã kết thúc (không ghi số tiền vì quỹ không chụp lại theo cuộc thi). Người đạt giải
  gửi thông tin nhận thưởng qua Góp ý. Code: `Services/TopHoc.php`, `ContestService::history`, `GET /contest/study-top`,
  `GET /contest/history`; bỏ `PrizeClaim.svelte`. Test: `TopHocTest`, `ContestTest`; kịch bản I16–I18.
  Lên server chỉ cần gói code mới, không có migration. **Đã lên server cùng ngày** (code `ad9032a`, `admin_emails` trong
  `config.php`, `cap-nhat-vi-du-hn.sql` + zip giọng); kiểm trên web thật: Q1 B1/B8, Q3 B8, Q6 B25 đủ ví dụ và giọng.
- **Kiểm web thật bằng tài khoản quản trị (08/11):** 24 màn quản trị + 29 màn học × 3 khổ không tràn, không lỗi; bấm thử lật
  thẻ 3 chế độ, Chuyển nhanh, Top chăm học, Quỹ tháng, Lịch sử thi, đọc sách, Quán trà, sổ điểm, quản trị Quán trà. Sửa và đưa
  lên cùng ngày: tab "Đăng ký" của bảng xếp hạng bị khung hộp cắt ở điện thoại (3 tab nay chia đều, `.mc-tabs`).
- **Khoá quyền quản trị bằng `admin_emails` trong `config.php`:** DB ghi admin mà email ngoài danh sách thì chỉ là người học
  (`Auth::attach`/`login`, `UserService::toApi`, đếm "còn ít nhất một quản trị"); đổi quyền sang admin cho email ngoài danh sách
  bị chặn. Server dùng 2 email `phuongnv24012001@gmail.com`, `nvpchaytran@gmail.com`. Test `AdminEmailsTest`.
- **Ví dụ lật thẻ Hán ngữ đủ 2.990/2.990 từ** (trước thiếu 535, 16 từ "ví dụ" chỉ là chính từ đó): câu chính bài → câu sách
  bài trước → 496 câu tự soạn (`data/han-ngu/vi-du/`, qua `kiem_vi_du_hn.py`; 6 agent soạn theo quyển, đọc soát ngẫu nhiên 45
  câu). Nạp local bằng `nap_vi_du_hn.py` (559 từ, giữ id). Server: `cap-nhat-vi-du-hn.sql` (tìm từ theo giáo trình + quyển +
  bài + chữ + pinyin; đã thử trên bản sao DB đưa về trạng thái server: khớp local 3.997 câu, HSK không đổi, chạy 2 lần như
  nhau) + zip giọng đọc câu mới. Không chạy lại `02-han-ngu.sql`: xoá bài là mất tiến độ học viên.
- **Quét giao diện 08/11 (local):** 25 màn quản trị + 32 màn học viên × 3 khổ (375/768/1280px) không tràn, không lỗi;
  bấm tay lật thẻ 3 chế độ, Chuyển nhanh, chuông, đọc sách, file nghe, Quán trà, Top chăm học, Đổi quyền. Sửa 2 lỗi tìm được:
  bài 0 câu (Bài tập/Kiểm tra Hán ngữ) vẫn có nút "Bắt đầu làm bài" ở điện thoại; trang lỗi Bài tập/Lật thẻ mất thanh đầu
  trang. Kịch bản phần R.

### 08/11/2026 — Mở bài giáo trình bằng điểm, chặn tải

- **Luật lúc triển khai:** học viên chỉ đọc được bài đã mở. Lần đầu bấm bé túi/link Shopee +3 điểm, từ hôm sau mỗi ngày bấm +1 (giữ điểm chưa
  dùng, không bù ngày không bấm). 1 điểm mở 1 bài, lần lượt; quyển sau khoá đến khi mở hết quyển trước.
  Bé túi: giai đoạn test vẫn hiện mỗi lần tải trang (người dùng yêu cầu); khi chốt thì nghỉ đến 0h hôm sau.
- **Chặn tải:** server trả 403 cho ảnh trang, đáp án, file nghe của bài chưa mở; mọi đường dẫn file phải kèm mã ký của chính
  tài khoản (sống 6 giờ). Màn đọc tắt chuột phải, giữ ngón tay, kéo ảnh, Ctrl+S/P, không in trang sách.
- **Code:** migration `019_diem_mo_bai.sql` (`book_points`, `book_unlocks`); `Services/BookAccess.php`,
  `Controllers/BookPointsController.php`, `Controllers/Admin/BookPointsController.php`; web: `lib/learn/diem-doc.svelte.ts`,
  `lib/learn/DiemChip.svelte`, `giao-trinh/KhoaBai.svelte`, `/admin/book-points`. Cài đặt mới `points.test_mode`.
- **Đã test:** API 19 test giáo trình (chặn, mã ký, điểm theo ngày, mở lần lượt, chế độ test); web `sach.test.ts` luật mở trang.
  Trình duyệt (local, tài khoản mẫu): kệ sách, thẻ khoá, nhận điểm, mở Bài 1, file nghe 206, Bài 2 vẫn 403,
  màn quản trị điểm; máy tính và 375px. Kịch bản: phần O của [KICH-BAN-TEST.md](KICH-BAN-TEST.md).
- **Sửa thêm cùng ngày:** đã làm phần **bán gói học** (trọn bộ, một lần dùng mãi mãi, chuyển khoản + quản trị duyệt) rồi
  **bỏ hẳn** theo người dùng vì rủi ro pháp lý khi thu tiền bản scan sách — xoá code, bảng `book_packages/orders/owned`,
  cài đặt `payment`, trang `/learn/books/goi`;
  thanh đầu trang thống nhất ở mọi màn (máy tính `Topbar`, điện thoại `MTopbar`), bỏ logo; ô điểm ở mọi màn; lời bé túi giữ
  ý "góp quỹ thưởng" cùng dòng điểm; **Chế độ test điểm** (mỗi lần bấm bé túi +1) — từng bật ở máy local cho người dùng thử.
  Luật này đã được thay bằng mục "Tách nhận điểm khỏi bé túi Shopee" ở trên.
- **Góp ý cho người học:** Chuyển nhanh → "Góp ý · báo lỗi màn này" (`FeedbackModal`, `POST /feedback`, `GET /feedback/mine`),
  tự kèm phần/cấp/bài/trang sách, server tra đúng bài để quản trị "Mở để sửa"; học viên đọc trả lời ngay trong hộp. Phần P.
- **Quán trà:** phòng trò chuyện chia bàn theo chủ đề (`/learn/chat`, `/learn/chat/<bàn>`, migration 020 + 021, `Services/QuanTra.php`,
  quản trị `/admin/chat`). Tin quản trị không báo cáo được. Phần Q.
- **Còn treo:** chưa lên server (cần code mới + chạy migration 019, 020, 021 + gắn lại link Shopee ở quản trị; **tắt Chế độ test**).
  Chưa thử giữ ngón tay trên điện thoại thật. Rủi ro bản quyền của sách scan vẫn còn (đã báo người dùng).
  Điểm, bài đã mở của `hocvien@zika.local` ở local là dữ liệu dev, không đóng gói lên server.

### 08/11/2026 — Đã đưa lên server thật và kiểm

- Thứ tự đã làm: xoá DB (giữ tài khoản) → `01-cau-truc.sql` (dừng ở 013: bảng words trên server có `uq_words_lesson_hanzi`
  + `ix_words_level_hanzi` thay cho `uq_words_level_hanzi`; sửa tay: bỏ 2 chỉ mục đó, thêm khoá thường
  `uq_words_curriculum_hanzi`, thêm `users.curriculum_id`, ghi 013 vào `schema_migrations`, chạy lại 01) → `02-han-ngu.sql`
  → `03-hsk-giong-moi.sql` → giải nén `uploads-han-ngu.zip`, `uploads-hsk-giong-moi.zip`, 3 zip ảnh sách, `zika-code-20261108.zip`
  tại `/home/buwsofujhosting`. Câu so cấu trúc: `../dong-goi-han-ngu/kiem-cau-truc.sql` (trống = khớp local).
- Kiểm web thật (đăng nhập quản trị): 116 bài × 6 phần + danh sách bài giống local; 21.313 file âm thanh/nét chữ có đủ, đúng
  cỡ; 43 màn không lỗi; bấm tay Lật thẻ (3 chế độ), Luyện viết, Hội thoại, Ngữ pháp, Bài tập/Kiểm tra HSK, Giáo trình
  (nghe, tua 206, đáp án) ở máy tính, máy tính bảng, điện thoại. Đối chiếu lại với bản chép sách: Hán ngữ khớp hoàn toàn,
  HSK 40/40 bài khớp.

### 08/11/2026 — Chuẩn bị đưa cả 2 giáo trình lên server

- DB server: người dùng chạy lệnh xoá, chỉ giữ tài khoản (email, mật khẩu, tên, vai trò), thêm lại thư chào mừng tự động.
- So bài HSK trên server (gói 02/11) với local: giống từng bảng, chỉ khác đường dẫn âm thanh hội thoại (đợt giọng 03/11
  chưa lên). SQL giọng 03/11 cũ tìm bài theo cấp + số bài không lọc giáo trình → sẽ ghi đè Hán ngữ Q1–3. Thay bằng
  `../dong-goi-hsk/` (`03-hsk-giong-moi.sql` lọc `curriculum_id = 1`, `uploads-hsk-giong-moi.zip` 8.586 file).
- Thử cả chuỗi xoá → 01 → 02 Hán ngữ → 03 HSK trên DB giống server: HSK 3.0 và Hán ngữ 6 tập giống local từng bảng,
  mặc định là Hán ngữ, cấp 1–6 đều hiện.
- 3 zip ảnh giáo trình đóng lại cùng tiền tố `domains/tiengtrunghanu.io.vn/uploads/` như gói Hán ngữ.

### 08/11/2026 — Giáo trình: thêm 6 quyển Hán ngữ, file nghe và đáp án

Người dùng: kệ Giáo trình của học viên Hán ngữ phải là sách Hán ngữ, có file ghi âm để bật nghe khi đọc, có đáp án, quyển
nào không có thì ẩn; chỉ làm giao diện cho dễ dùng.

- **Dữ liệu** — `data/giao-trinh/dung-sach.py` (thay `tach-giao-trinh.py`) dựng 9 quyển vào `uploads/giao-trinh/<mã>/`:
  ảnh trang, `nghe/NN.mp3`, `dap-an/NNN.webp`, `sach.json`. Hán ngữ 1–6: 1.558 trang, 181 file nghe (~9 giờ); HSK 1–2:
  143 file; đáp án Hán ngữ 3–6 (116 trang) từ 4 file Word YUEXIN, đổi PDF bằng Word (`ra/dap-an/`). Hán ngữ 1–2 và HSK
  không có đáp án; HSK 3 không có file nghe. Tổng ~740MB.
- **MP3 Hán ngữ 6** bitrate thay đổi, thiếu header Xing: trình duyệt đoán 510 giây cho file 595 giây và không tua quá mốc
  đó. Script cho ffmpeg (gói `imageio-ffmpeg`) đóng gói lại, chép nguyên âm thanh. Đã kiểm: trình duyệt đọc 595,4 giây.
- **API** `Controllers/BookController.php` (bỏ `Admin/BookController.php`): `GET /learn/books` (kệ, lọc theo giáo trình),
  `/learn/books/{mã}` (mục lục, nghe, đáp án), trang, `/dap-an/{trang}`, `/nghe/{số}` (Range 206). `Response::fileRange`.
- **Web** — kệ nhóm theo bộ, thẻ "Đọc tiếp", nhãn file nghe/đáp án; màn đọc có Mục lục, dải tên bài với nút Nghe bài này/
  Đáp án, thanh phát dính đáy (`lib/learn/giao-trinh/`), chuyển Sách ↔ Đáp án. 8 icon thêm tay cuối `lib/icons.ts`.
- **Service worker** nay không cache `/api/learn/books` (trước đó đổi sang `/api/learn/` thì ảnh sách lọt vào kho bài học
  300 mục và file nghe 206 sẽ câm).
- Test: API 173/173 (BookPageTest 9 test), web 106/106. Kịch bản GT6–GT19.
- **Server**: cần code mới + chép `uploads/giao-trinh/` (cả 9 thư mục, có `sach.json`). Gói ở `release/`.

### 08/11/2026 — Hán ngữ 6 tập đúng như sách in, thêm tiếng Anh

Người dùng: sách là chuẩn, web phải đúng từng chữ và pinyin, đủ mọi thứ sách in ở 4 phần, và thêm mọi phần tiếng Anh
sách in. Soát lại cả 1.296 trang của 76 bài theo ảnh (16 agent, hướng dẫn chung trong lần làm), sửa bản chép `bai/*.json`
(bản trước khi sửa: `data/han-ngu/ra/bai-truoc-soat-20261108/`):
- Lỗi chép thật: chữ Hán sai/thiếu (Q6 B17 试一次 chép thành 试一试, Q6 B24 thiếu chữ 地, Q3 B2 写汉字 历 → 厉), mục thiếu
  (Q2 B20 thiếu 二日…十日, Q1 B1 thiếu 5 chữ, Q3 B7 thiếu 10 câu so sánh), chú thích trong ngoặc sách in bị bỏ.
- Ngữ pháp: bỏ tên mục, công thức, giải thích, ví dụ người chép tự đặt; chép lại nguyên văn sách.
- Pinyin tự thêm (Q3–Q6 và ví dụ): theo bảng 生词 của sách — thanh nhẹ, viết hoa tên riêng, 不 trong bổ ngữ khả năng
  "bu", bổ ngữ xu hướng sau động từ qilai/xialai/huiqu như 生词 Q4 B14 in, đa âm tự theo nghĩa (很长时间 cháng, 待 dāi,
  一场雪 cháng, 东西 dōngxi, 地方 dìfang). Lệch bảng 生词: 528 → còn vài chỗ báo nhầm (一个人 ≠ 个人).
- Tiếng Anh: 2.986/2.990 thẻ từ có nghĩa tiếng Anh, 630 tên mục, 611 đoạn giải thích, 65 ví dụ, công thức và lưu ý.
- 65 lỗi in của sách (鸡/蛋 đảo nghĩa, 西边 "phía đông", 南京 "Giang Tây"…): người dùng muốn **sửa trên web**. Bản chép
  vẫn ghi lại chữ sách in ở `loi_sach` (`sach_in` → `sua_thanh`, `da_sua: true`), còn nội dung đã thay bằng bản sửa.
- Rà lại toàn bộ chữ Việt và Anh (6 agent, chỉ đọc chữ): 375 chỗ sửa — nghĩa từ sai (旁边 "gần đây" → "bên cạnh",
  顿时 "chốc lát" → "lập tức", 监考 "giám khảo" → "coi thi"), nghĩa Anh lệch nghĩa Việt (相会 chép nhầm nghĩa 相亲),
  bản dịch câu sai nghĩa (不是…而是 "không chỉ… mà là"), xưng hô, tên người (罗兰 thống nhất Roland), chính tả.
  Sửa vào phần sách in thì thêm mục `loi_sach`; bản tự dịch sửa thẳng.
- Người dùng chỉ muốn tiếng Anh ở **Lật thẻ**: trang Ngữ pháp bỏ phần tiếng Anh (dữ liệu vẫn trong DB, ô sửa ở trang soạn giữ).
- **Phần nghe**: mọi bài khoá dùng giọng tự tạo (`gan_hn.py` `DUNG_BANG = False`), cùng giọng nhân vật, tốc độ, độ to
  với câu thoại và với HSK 3.0; không dùng băng (băng Q1, Q5, Q6 là bản 修订本 đọc khác sách). Giọng đọc bỏ ký hiệu sách in
  trước khi gửi máy (`doc_edge.bo_ngoac`): `*` câu sai trước bị đọc thành 星号, `'` trọng âm; chú thích trong ngoặc vốn đã bỏ.
- Bỏ dòng "Bản dịch tiếng Việt là bản tham khảo của Zìkǎ — sách không in." ở tình huống bài khoá (189 bài khoá, cả 60 bài HSK):
  bộ nạp thôi chèn, migration 018 dọn dữ liệu đã nạp (server chạy cùng `01-cau-truc.sql`).
- Tốc độ: 305 câu Hán ngữ đọc nhanh hơn 5,8 âm tiết/giây sinh lại chậm thêm 15% (`doc_cham_lai.py` với danh sách Hán ngữ),
  như chuẩn HSK 3.0. Quét qua web: 13.116 âm thanh + 1.381 nét chữ, 0 lỗi.
- Thanh đường dẫn 5 màn học theo bài: "Phần / Quyển n", không lặp số bài và tên bài đã có ở khối tiêu đề.
- **Giáo trình (`/learn/books`) mở cho mọi tài khoản** theo yêu cầu người dùng: hiện ở Chuyển nhanh, bỏ chặn đường dẫn,
  ảnh trang qua `/api/learn/books/...` (đòi đăng nhập; `/admin/books` cũ giữ lại). Thư mục ảnh vẫn bị `.htaccess` chặn.
  Thử bằng học viên mẫu: menu có Giáo trình, ảnh 3 quyển tải được; khách chưa đăng nhập 401/403.
- Gói server `../dong-goi-han-ngu/` (migration 006–018, 14.497 file, 337 MB) thử trên DB giống server: **ĐẠT**. Chạy thử
  toàn bộ web qua trình duyệt: 22 trang học viên + 17 trang quản trị đạt, không lỗi console.

Web và API (migration 017): sách in lại một từ ở bài sau / một chữ hai cách đọc trong bài (30 từ trước bị bỏ, 好 hào/hǎo
bị gộp) nay đều có thẻ — trùng chỉ xét trong một bài theo chữ Hán + pinyin; cột tiếng Anh cho từ và ngữ pháp; ngữ pháp
giữ tới 40 ví dụ (không còn tách "(tiếp)"), tên 255 và công thức 2.000 ký tự, không bỏ ô công thức chỉ có chữ Hán;
Luyện viết hiện cách đọc 写汉字 của từng bài (`lesson_chars.pinyin`, 行 háng); tên bài khoá theo sách. Màn học hiện
tiếng Anh ở Lật thẻ (dưới nghĩa Việt) và Ngữ pháp (tên, công thức, khối "English", ví dụ, lưu ý); màn soạn có ô tiếng Anh.
Bộ sinh `data/han-ngu/sinh.py` viết lại phần từ, hội thoại, ngữ pháp cho khớp.

Kiểm tra: so web với bản chép từng trường (thẻ, chữ viết, câu thoại, ví dụ, cả tiếng Anh) — **khớp hoàn toàn** 76 bài.
Trình duyệt: thẻ 英文 hiện "English" in nghiêng; Q5 B6 ngữ pháp hiện tên, câu tiếng Anh và khối English. Web 0 lỗi/0 cảnh
báo, lint sạch, 103/103; API 168/168. Giọng đọc: 747 file mới cho câu đã sửa.


Migration 016: giáo trình số 1 đổi tên "Giáo trình HSK chuẩn" → "Giáo trình HSK 3.0"; thêm cột `curricula.is_default` và bật cho
"Hán ngữ 6 tập". Mọi học viên chưa phân quyền, cả tài khoản cũ lẫn mới đăng ký, học Hán ngữ; ai học HSK thì admin gán HSK 3.0 ở
Phân quyền học (tiến độ HSK cũ còn nguyên). `Curricula::DEFAULT_ID` đổi thành `HSK_ID` (giáo trình gốc của công cụ nạp và chữ mẫu cấp),
giáo trình của học viên chưa gán lấy bằng `Curricula::defaultId()`. Form giáo trình có công tắc **Giáo trình mặc định**, bật bộ khác thì
bộ cũ tự tắt; không tắt hay xoá được bộ đang mặc định. Đổi mặc định thì bỏ lượt ôn dở của học viên chưa phân quyền, như khi gán tay.
Gói server: `01-cau-truc.sql` tới migration 016, `02-han-ngu.sql` tự chuyển cờ sang Hán ngữ; **phải đóng gói lại**.
Lúc làm có chạy nhầm `install.php --help` (lệnh này không có `--help`) ở DB dev: 3 tài khoản mẫu bị đặt lại mật khẩu `Zika@2026`,
nội dung và tiến độ không đổi. Bản đầu của migration 016 (cột `for_signup`, chỉ áp cho tài khoản mới) đã chạy ở DB dev và `zika_test`
rồi được gỡ để chạy bản này. Kiểm tra: web 0 lỗi/0 cảnh báo, lint sạch, 103/103; API 164/164 (1107 assertions); `thu_server.php` ĐẠT với gói mới. Trình duyệt: `/admin/curricula` đúng tên,
nhãn Mặc định và công tắc. Chưa đăng ký thử tài khoản thật, chưa bấm Phân quyền học trên trình duyệt.

Cùng ngày sửa trang soạn **Ngữ pháp**: tab ghi "Ngữ pháp 8" nhưng trang báo "0 điểm ngữ pháp" và "Không tải được phần ngữ pháp",
vì `structuredClone` không sao chép được proxy `$state` (bẫy số 8). Đổi sang `$state.snapshot` ở Ngữ pháp, Bài tập, Kiểm tra và
Đề thi thử. Đã bấm trên trình duyệt: Q2 Bài 16 hiện đủ 8 điểm ngữ pháp; HSK 1 Bài 1 hiện đủ 8 câu bài tập, 5 câu kiểm tra.

Menu trái nhóm **Phần học · soạn theo bài**: trình duyệt chưa mở bài nào thì 5 mục đều trỏ về `/admin/lessons` nên sáng nhầm ở
"Bài học HSK"; bài gần nhất cũng chỉ đọc lúc tải trang, mở bài khác vẫn trỏ bài cũ tới khi F5. Nay menu bám theo bài trên URL, chưa có
bài thì lấy bài đầu của giáo trình đang soạn. Đã bấm thử cả 5 mục khi xoá bài đã nhớ, và chuyển bài không tải lại trang.

### 07/11/2026 — Học viên Hán ngữ thấy "Quyển n" thay cho "HSK n"

Thêm cột `curricula.level_label` (migration 015, chọn ở form giáo trình), API trả `user.levelLabel`, màn học đọc qua
`lib/stores/cap.svelte.ts`. Chữ mẫu trên thẻ cấp của giáo trình khác HSK lấy từ bài đầu của chính nó. Không đổi: URL `/hsk/n`,
tên "Thi thử HSK n" (đề HSK dùng chung), màn thống kê của quản trị (Người dùng, Phiên truy cập, Tổng quan). Gói server phải đóng lại
vì `01-cau-truc.sql` thêm migration 015. Lúc làm có chạy nhầm `install.php` không tham số ở DB dev: 3 tài khoản mẫu bị đặt lại mật khẩu
`Zika@2026`, nội dung và tiến độ không đổi. Muốn chỉ chạy migration thì dùng `--migrate`.
Kiểm tra: web 0 lỗi/0 cảnh báo, lint sạch, 103/103 test, build được; API 161/161 (1088 assertions); `thu_server.php` ĐẠT với gói mới.
Trình duyệt: học viên Hán ngữ qua trang học của 4 phần có nội dung, danh sách Bài tập và Kiểm tra, trang Tài khoản (N13); học viên HSK qua
Bàn học và Luyện viết (N14); quản trị và Xem thử (N15); Bàn học và màn chọn bài ở 375px không tràn ngang. Chưa đưa lên server.

### 07/11/2026 — Nạp giáo trình Hán ngữ 6 tập, sửa lỗi sau đợt rà toàn hệ thống

Giáo trình mới 76 bài, 2.959 từ, 129 bài khóa, 1.774 câu, 682 mục ngữ pháp, 3.090 chữ luyện viết, chỉ gồm Lật thẻ, Luyện viết,
Hội thoại, Ngữ pháp. Quy trình và các quyết định ở `data/han-ngu/README.md`. Âm thanh bài khóa: 69 dùng file băng, 35 cắt từ file
chung, 25 ghép giọng mẫu vì băng đọc khác sách.
Sửa ở API: chấm bài cuộc thi dài, ngày học thật (`study_days`, migration 014), editor bị chặn đúng chỗ, tái mở kết quả cuộc thi đã
đóng, xếp lại bài, luật admin hoạt động. Sửa ở web: học tiếp ở lật thẻ, ôn tập, bài tập, luyện viết; khoá trùng ở trang người dùng;
cache bài theo giáo trình; tooltip ném lỗi khi nút đang focus biến mất.

### 06/11/2026 — Bỏ mũi tên thẳng trên nút, đồng bộ điều hướng bằng chevron

Bỏ mũi tên trang trí cạnh chữ ở các nút cuộc thi, Bàn học, thẻ cấp, Gõ chữ, Trắc nghiệm, chuyển câu kiểm tra,
Ngữ pháp, Luyện viết và thông báo. Các nút quay lại, chuyển từ, trang sách, phân trang admin và mở chi tiết
đổi sang chevron; giữ nhãn hỗ trợ đọc màn hình, trạng thái vô hiệu và hành động bấm.
Ghi quy ước cho người/AI tiếp tục trong `BAN-GIAO-GIAO-DIEN.md`, thêm checklist phần M trong `KICH-BAN-TEST.md`.
Kiểm tra web: Svelte 0 lỗi/0 cảnh báo, lint sạch, 88/88 test và build thành công; API 133/133 test (934 assertions) đạt.
Checklist hình ảnh trên thiết bị thật còn chờ kiểm tay.

### 06/11/2026 — Hoàn thiện giao diện cuộc thi, lịch sử thưởng và luồng vào thi muộn

Rà lại toàn bộ hộp cuộc thi trên máy tính/điện thoại: thông tin hiện tại gọn theo hai cột, điện thoại toàn màn,
nút đăng ký/vào thi rõ trạng thái, bỏ chữ và hành động lặp; tooltip dùng một giao diện chung. Thêm màn **Lịch sử thi & thưởng**
và bản xem trước nhận thưởng qua ngân hàng; phần lịch sử, ngân hàng và trạng thái chuyển thưởng vẫn chỉ là dữ liệu mẫu phía web, chưa gửi/lưu thật.

Sửa logic giờ thi: người đã đăng ký có thể bấm **Vào thi ngay** bất kỳ lúc nào từ giờ mở đến trước giờ đóng, vào muộn chỉ còn
thời gian tới giờ đóng chung. Sau khi kết thúc, người đăng ký nhưng chưa từng vào thi vẫn xuất hiện cuối bảng với 0 điểm và
nhãn **Không tham gia**, không tạo bản ghi lượt thi giả. Test trọng tâm `ContestTest`: 36/36 đạt (117 assertions).
Toàn bộ kiểm tra: web check 0 lỗi/0 cảnh báo, lint sạch, 88/88 test, build thành công; API 133/133 test (934 assertions).
Checklist chi tiết ở K23b–K30 trong [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

### 06/11/2026 — Hiện bé túi trên mọi màn học; đăng nhập về lật thẻ

Theo yêu cầu, bỏ giới hạn màn chọn bài/đang làm bài của bé túi: mọi đường `/learn/` đều được phép hiện,
người dùng chấp nhận che nút. Giữ nguyên link, chuyển động, bấm ẩn, F5 hiện lại và tạm ẩn khi có dialog/nhập liệu.
Học viên đăng nhập với `next=/account` nay về `/learn/flashcards`; vẫn mở Tài khoản và quay lại link bài học được.
Đã cập nhật test route và hướng dẫn trong `BAN-GIAO-GIAO-DIEN.md`, checklist I9/K9 trong `KICH-BAN-TEST.md`.
Kiểm tra: web 88/88 test đạt, Svelte 0 lỗi/0 cảnh báo, lint sạch, build thành công; API 131/131 test (923 assertions) đạt.
Chưa kiểm lại chuyển động trên thiết bị thật.

### 06/11/2026 — Giáo trình làm cha của bài học, tab Đề thi, hộp quỹ sắp lại

**Giáo trình:** thêm bảng `curricula`, `lessons`, `words`, `users` có `curriculum_id` (**migration 013**). Dữ liệu cũ về giáo trình số 1, đã chạy thử trên bản sao DB: 40 bài, 932 từ, số dòng không đổi.
`/admin/curricula` bỏ bản demo, nối API thật; `/admin/lessons` và `/admin/words` chọn giáo trình trước. Người học chỉ thấy bài của bộ được gán, cache tách theo giáo trình.
**Đề thi:** tab `/admin/exams` chỉ cần tên (**migration 012**), form tạo bài thi chọn đề từ đó. **Hộp quỹ:** Shopee chung khối với số tiền, giải thu gọn ba ô, bản đủ ở Xem thể lệ; bảng xếp hạng hiện giải theo hạng.
**Lỗi tìm thấy khi rà:** nút Xem như người học không có tác dụng (`setPreview` chưa được gọi), `/admin/mock-tests` tải hai lần và mặc định HSK 2, bài thi dài hơn 1 ngày không sửa được.
Hai migration đã chạy ở máy local, server thật chưa; khi đưa lên nhớ xoá `storage/cache/content`. Chi tiết: [BAN-GIAO-GIAO-DIEN.md](BAN-GIAO-GIAO-DIEN.md), kịch bản: phần L trong [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

### 06/11/2026 — Lịch thi gọn, đăng ký thi, cơ cấu giải, trang soạn đề tạm

Tạo bài thi chỉ còn giờ bắt đầu và số phút thi, mọi người cùng dừng khi hết giờ. Chỉ ai đăng ký trước giờ bắt đầu mới được làm bài, có danh sách đăng ký.
Thêm cơ cấu giải theo phần trăm Quỹ tháng, admin tự chỉnh. Trang soạn đề `/admin/mock-tests/{id}` là bản tạm, người dùng sẽ tự nạp đề từ ngoài và sửa sau.
**Migration 011** đã chạy ở máy local, server thật chưa. Rà toàn bộ 48 màn ở máy tính và điện thoại: sửa bé túi che nút ở màn chọn bài, ô tên giải bị bóp trên điện thoại,
ghi chú sai khi đang làm dở bài thi.

### 06/11/2026 — Cuộc thi: tạo bài thi, đồng hồ đếm ngược; màn sản phẩm Shopee có tìm kiếm

Thêm `/admin/contests` để tạo bài thi từ đề thi thử và đặt giờ; người học thấy đồng hồ ngày, giờ, phút, giây, tới giờ có nút Vào thi ngay.
Nút Xem cuộc thi rộng hết hộp. "Ghé Shopee" mở màn sản phẩm có ô tìm không dấu. Hết giờ thì màn khoá và tự nộp dù chưa bấm, hạn nộp không vượt giờ đóng cuộc thi.
Bảng xếp hạng thật: đúng nhiều hơn, rồi làm nhanh hơn; mỗi người 1 lượt; còn mở thì giấu đáp án. **Migration 009, 010** đã chạy ở máy local, server thật chưa.
Sửa kèm: `used_sec` tính tới hạn nộp và dùng `Clock`, không còn phụ thuộc lúc cron chấm. Chi tiết: mục "Cuộc thi" trong [BAN-GIAO-GIAO-DIEN.md](BAN-GIAO-GIAO-DIEN.md),
kịch bản: phần K trong [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

### 06/11/2026 — Nối link Shopee thật: admin chọn vị trí, người học thấy theo công tắc

Form Thêm link bỏ "Hiện từ ngày", "Đến ngày", "Bật ngay sau khi lưu". Vị trí đổi thành Icon (bé túi, 1 link) và Quỹ tháng (nhiều link);
đã có link giữ Icon thì 2 ô bị khoá, link mới vào Quỹ tháng. Admin tự nhập tiền quỹ tháng, người học thấy đúng số đó.
Thêm API `GET /support/links`, `POST /support/view`, `GET /go/{id}`, `PUT /admin/support/fund`. **Migration 007, 008** đổi cột trong `affiliate_links`,
đã chạy ở máy local, server thật chưa. Chi tiết: mục "Link Shopee" trong [BAN-GIAO-GIAO-DIEN.md](BAN-GIAO-GIAO-DIEN.md),
kịch bản: phần J trong [KICH-BAN-TEST.md](KICH-BAN-TEST.md). Top vẫn là số minh hoạ.

### 06/11/2026 — Rà UI/UX quỹ thưởng, top chăm học và vẽ lại bé túi (giao diện)

Vẫn chỉ là giao diện, chưa có backend. Rà ở 320/375/768/1280px, sáng và tối, kết quả ở phần I của
[KICH-BAN-TEST.md](KICH-BAN-TEST.md). Lỗi đã sửa: lời nhắn của bé che nút "Kiểm tra" trong bài (nay bé không hiện ở
màn đang làm bài); chữ 8–10px khó đọc ở thanh đầu trang và hộp thoại; điện thoại chỉ Bàn học có cụm quỹ/top;
hộp quỹ lặp tiêu đề và lồng khung; nhãn thẻ cấp bị cắt ở 320px. Bỏ hộp Shopee riêng, bấm túi hay số tiền đều mở hộp quỹ.
Bé túi vẽ lại, chữ chỉ hiện khi bé đứng yên để bớt hoa mắt, bỏ dấu × ở lời nhắn, bỏ lớp bóng mờ, thêm cử động.
Chi tiết: [BAN-GIAO-GIAO-DIEN.md](BAN-GIAO-GIAO-DIEN.md).

### 05/11/2026 — Giáo trình admin, quỹ thưởng tháng và bé túi Shopee (giao diện)

Thêm `/admin/curricula` với danh sách giáo trình và bảng phân cho mỗi học viên một bộ, tìm tên/email
không dấu. Giữ giao diện học viên theo HSK/bài như cũ, không cho tự chọn giáo trình. Bổ sung nút quỹ
thưởng và top chăm học trên thanh đầu trang; túi Shopee SVG chạy nhảy toàn màn học, bấm thì ẩn,
F5 hiện lại để test. Di chuyển bằng requestAnimationFrame/translate3d và vận tốc làm mềm (cách đi đã đổi ngày 06/11).

Chỉ frontend, dữ liệu giáo trình lưu localStorage; tiền/top minh họa, link Shopee chưa nối. Không có
API, migration, đồng bộ hoa hồng hay thi thật. Đã loại các khối/thống kê/text theo góp ý người dùng.
Chi tiết trạng thái cuối, đường dẫn code, hạn chế và việc cần tiếp tục:
[BAN-GIAO-GIAO-DIEN.md](BAN-GIAO-GIAO-DIEN.md). Kiểm thủ công bổ sung ở cuối KICH-BAN-TEST.md.

### 04/11/2026 — Hiện đầy đủ tình huống ở hội thoại

Theo yêu cầu cuối của người dùng, bỏ nút Xem tình huống/Thu gọn và hiển thị đầy đủ mô tả
ngay từ đầu. Bỏ rút gọn chữ và giới hạn hai dòng; xuống dòng tự nhiên trên điện thoại/máy tính.
Giữ nguyên phần nghe và lời thoại.
Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 73/73 test web đạt. Chưa cập nhật hosting.

### 04/11/2026 — Chuyển nhanh chỉ hiện Bài tập/Kiểm tra cho admin

Lọc hai mục Bài tập và Kiểm tra theo `session.isAdmin` trong menu Chuyển nhanh, áp dụng cả lưới
chuyển phần và ô Đang dùng. Học viên và biên tập không thấy hai mục này; các mục còn lại giữ nguyên.
Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 73/73 test web đạt. Chưa cập nhật hosting.

### 04/11/2026 — Gọn giao diện học từ theo yêu cầu

Bỏ dòng gợi ý Space/mũi tên ở Lật thẻ theo bài và tổng hợp. Bỏ chân đồng hồ giây và gợi ý
phím của Ghép cặp, Trắc nghiệm, Gõ chữ. Ghép cặp bỏ nhãn Lượt và các ký hiệu số/chữ phím
ở hai cột. Giữ tiến độ, số câu ở các chế độ còn lại, cách chấm, phát âm, phím tắt và thống kê kết quả.
Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 73/73 test web đạt. Chưa cập nhật hosting.

### 04/11/2026 (chiều) — Làm web nổi bật hơn: khối "Hôm nay", ô chữ điền, dấu triện

**1. Bàn học có khối "Hôm nay"** (`web/src/routes/learn/flashcards/+page.svelte`):
- Thẻ lớn **Học tiếp** dẫn thẳng vào bài đang dở (hoặc bài kế tiếp chưa học), có 3 chữ Hán mẫu, tiến độ
  thẻ và nút "Học tiếp". Góc thẻ in mờ chữ Hán đầu của bài bằng font bút lông. Dữ liệu `continue` đã có
  sẵn trong `/progress/home` từ trước nhưng màn này chưa dùng.
- Thẻ **chuỗi ngày học**: số ngày học liên tiếp, 7 ngày gần nhất (đã học = chấm đỏ có dấu tích, hôm nay
  viền đỏ), số từ đã lật xong.
- Các cấp chưa mở gom thành một dòng "Sắp có HSK 4 · HSK 5 · HSK 6", không còn 3 thẻ xám to.
- API: `/progress/home` trả thêm `today: {streak, week[7], words}` (`ProgressService::today`). "Ngày có học" =
  ngày có ghi `lesson_progress`/`review_progress` hoặc bắt đầu bài tập/kiểm tra; chỉ mở web thì không tính.
  Hôm nay chưa học thì chuỗi vẫn giữ đến hôm qua. Test: `api/tests/Unit/TodayStreakTest.php`.

**2. Chữ Hán trên thẻ lật đặt trong ô chữ điền** (`web/src/lib/components/ChuDien.svelte`): mỗi chữ một ô
田字格 nét đứt, cỡ ô theo số chữ (≤ 4 chữ; dài hơn thì in thường), theo cài đặt cỡ chữ Hán. Dùng ở mặt
sau thẻ (và mặt trước khi chọn "Chữ Hán trước") của màn Lật thẻ theo bài và màn ôn Tổng hợp. Pinyin tô
màu thanh điệu đã có từ trước, giữ nguyên.

**3. Dấu ấn riêng** (cuối `web/src/app.css`, `web/src/lib/learn/hoa-tiet.ts`):
- Bài đã xong: dấu triện đỏ **完** thay chấm xanh, nền thẻ bài màu giấy (`.dau-xong`, `.les.done`) — ở mọi
  danh sách bài của 6 phần.
- Mỗi cấp có hoạ tiết số Hán kiểu chữ kép 壹 贰 叁 肆 伍 陆 viết bút lông, in mờ màu của cấp: ở thẻ cấp
  (Bàn học, Chọn cấp của 5 phần) và khung đầu trang danh sách bài. Không dùng 一 二 三 vì in mờ trông như vết bẩn.
- Dấu "完成" ở màn xong bài đập xuống như đóng triện (tắt khi máy bật giảm chuyển động).
- Font bút lông Ma Shan Zheng chỉ tải ở các màn có hoạ tiết (`taiChuBut()`), Google Fonts tự cắt theo chữ dùng.

**Đã test:** web 0 lỗi 0 cảnh báo, lint sạch, 73/73; API 59/59. Xem tận mắt Bàn học, danh sách bài HSK 1–2,
thẻ lật ở 1280px và 375px, giao diện tối; quét 10 màn ở 375px không tràn ngang. Kịch bản: mục H của
[KICH-BAN-TEST.md](KICH-BAN-TEST.md).

**Đưa lên server:** chép lại web + `api/app/src/Services/ProgressService.php`. Không có migration mới.

### 04/11/2026 — Đồng bộ giao diện mọi màn học; học viên đổi ảnh đại diện

**Giao diện một khuôn.** Thêm khối "Khung học dùng chung" cuối `web/src/app.css` (lớp `zk-*`: `.zk-khung`,
`.zk-khung-dau`, `.zk-so`, `.zk-chan`, `.zk-o`, `.zk-tab`, `.zk-nut`…) và component `DauBai.svelte`
(đường về + "HSK · Bài · …" + tên bài). Mọi màn học theo bài và 6 màn ôn tổng hợp dùng chung khuôn:
đầu bài + một khung trắng chứa trọn nội dung, nút, điều hướng. Cụ thể:
- Bài tập, Kiểm tra: nút Kiểm tra / Câu trước / Câu tiếp / dãy số câu / Nộp bài vào trong khung;
  Kiểm tra câu cuối nút "Câu tiếp" thành "Nộp bài" như trước.
- Luyện viết: dải chữ, ô viết, 3 cách viết (`.zk-tab`), thông tin chữ trong một khung; ĐT ô viết vẫn
  trong màn hình đầu.
- Ngữ pháp: dãy điểm `.zk-tab`, ví dụ mỗi câu một ô; thêm "← Điểm trước / Điểm sau →" cuối khung;
  bài chưa có điểm (HSK 1 Bài 1–2) báo "Bài này chưa có phần ngữ pháp." thay vì màn trắng.
- Hội thoại: bài khoá trong khung, "Đã đọc xong / Bài tiếp" ở chân khung; nút Pinyin / Nghĩa giữ nguyên.
- `PartLevel` (chọn bài của 5 phần) có khung đầu lớn tô màu cấp giống màn chọn bài Lật thẻ.
- Ôn Lật thẻ cả cấp: cùng khuôn màn Lật thẻ theo bài, thêm phím Space / ← / → (gợi ý phím đã hiện
  sẵn nhưng trước đây không chạy).
Class riêng từng màn có tiền tố (`btp-`, `kt-`, `vt-`, `np-`, `th-`, `ht-`, `pl-`), đã grep `app.css`.

**Ảnh đại diện.** Màn Tài khoản có **Thêm/Đổi ảnh** và **Gỡ ảnh**. Tối ưu cho dung lượng có hạn:
- Trình duyệt cắt vuông giữa, đọc đúng chiều EXIF, thu về 384px WebP (`web/src/lib/utils/anh.ts`):
  ảnh 1MB gửi đi còn ~51KB.
- Server (`Upload::image`, loại `avatar`) lưu 192×192 WebP q80 vào `uploads/avatar/` (~14KB), chặn ảnh
  > 24 megapixel trước khi GD giải nén (tránh vượt memory_limit hosting).
- Đổi ảnh / gỡ ảnh / xoá tài khoản thì xoá tệp cũ nếu không ai còn dùng (tên tệp theo sha1 nên hai
  người cùng ảnh dùng chung một tệp). Ảnh Google (URL ngoài) và ảnh cũ trong `uploads/img/` không đụng.
- Giới hạn 10 lần đổi / giờ / người. Thêm `DELETE /me/avatar`.

**Sửa lỗi có sẵn:** nút Xoá tài khoản luôn hỏng — web không gửi chữ xác nhận, server đòi "XOÁ" có dấu
trong khi màn bảo gõ "XOA", và không có ô mật khẩu. Nay gửi đủ, server nhận cả "XOA", hộp thoại có ô mật khẩu.

**Kiểm tra:** Svelte 0 lỗi/0 cảnh báo, lint sạch, 73/73 test web; API 57/57 (thêm `AvatarTest`: nén 192px
< 20KB, xoá ảnh cũ, ảnh dùng chung không xoá, ảnh Google không đụng, chặn ảnh 24.6MP, xoá tài khoản "xoa"
+ mật khẩu). Trình duyệt: quét 24 màn học viên ở 375px — không tràn ngang, không lỗi JS; xem từng màn ở
375 và 1280px, giao diện tối; đổi ảnh thật 1MB → gửi 51KB → lưu 14KB 192×192, ảnh cũ 404, gỡ ảnh, báo lỗi
tệp hỏng. Chưa kiểm trên điện thoại thật: chạy `web-lan` trong `.claude/launch.json` (cổng 5174, nghe mạng
LAN) rồi mở `http://<IP máy>:5174` trên điện thoại cùng Wi-Fi.

Lên server: chép code PHP mới (Upload, MeController, routes) cùng web; thư mục `uploads/avatar/` tự tạo khi
có người đổi ảnh đầu tiên (uploads phải cho PHP ghi).

### 04/11/2026 — Làm lại giao diện màn Lật thẻ: một khung học, thẻ phẳng

Người dùng thấy màn lật thẻ rối và xấu hơn trang mẫu. Đổi:
- Bỏ hai lá thẻ chồng phía sau (cả màn theo bài lẫn ôn tổng hợp, xoá `.stk` trong `app.css`);
  mặt thẻ phẳng, viền mảnh `--line-3`, bo 20px, bóng nhẹ.
- Màn theo bài gom vào **một khung trắng** "Chế độ học": hàng chế độ (nút có viền, nút đang chọn
  tô son nhạt), thẻ, dưới thẻ là "Thẻ x / n" + thanh tiến độ và ba nút ← Lật thẻ → canh giữa;
  câu ví dụ nằm trong khung, mỗi câu một ô nền nhạt. Bỏ hai nút tròn lơ lửng hai bên thẻ.
- Máy tính có đầu bài (đường về, "HSK · Bài · số từ", tên bài). Điện thoại bỏ thanh nút cố định
  ở đáy, điều hướng nằm ngay dưới thẻ; hàng chế độ đứng ngoài khung cho đủ bề ngang.
- 3 chế độ luyện và màn tổng kết bỏ khung riêng, nằm trong khung chung (không còn khung lồng khung).

Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 72/72 test web. Trên trình duyệt ở 375 / 768 / 1024 /
1280px, sáng và tối: không tràn ngang, 4 nút chế độ không cắt chữ ở 375px, nút điều hướng cao 50px
trên điện thoại. Màn ôn tổng hợp chỉ bỏ chồng thẻ, bố cục còn kiểu cũ. Chưa kiểm trên máy thật.

### 04/11/2026 — Lật thẻ có thêm 3 chế độ luyện: Ghép cặp, Trắc nghiệm, Gõ chữ

Theo mẫu "Chế độ học tập" của một trang học tiếng Trung khác: lấy cách bố trí, dựng lại bằng màu,
font, góc bo của Zìkǎ, và tránh các lỗi đã thấy ở trang mẫu (chữ Hán bẻ dòng "为什/么", nghĩa bị
cắt "…", nhãn phím chiếm chỗ trên điện thoại, máy tính bảng tràn ngang 942/768px, mỗi chế độ một màu).

- Đầu màn lật thẻ theo bài có hàng `.seg` chọn chế độ, mặc định Lật thẻ như cũ. Không đổi API.
- **Ghép cặp**: lượt ≤ 6 cặp chia đều; trùng nghĩa trong bài (12 chỗ) ghép cặp nào cũng đúng.
- **Trắc nghiệm**: 4 nghĩa lấy từ cùng bài, bỏ nghĩa trùng; trả lời sai thì hỏi lại một lần ở cuối.
- **Gõ chữ**: nhận chữ Hán hoặc pinyin không dấu, mọi cách đọc khi pinyin có "/" (`shéi/shuí`).
- Hết chế độ: tổng kết + "Luyện lại từ sai"; xong chế độ nào cũng ghi xong phần Lật thẻ.
- Mã: `web/src/lib/learn/che-do/` (hàm chấm `cham.ts`, trạng thái `state.svelte.ts` dùng chung hai
  bố cục, component từng chế độ). `media.coBanPhim` quyết định có bày nhãn phím tắt hay không.

Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 72/72 test web (thêm 18 test cho chấm và trạng thái).
Trên trình duyệt ở 375 / 767 / 768 / 1024 / 1280px, sáng và tối: không tràn ngang, chơi hết Ghép cặp
HSK 1 Bài 2 (ghi `lt/complete` 200), Trắc nghiệm hỏi lại câu sai, Gõ chữ nhận `nimen`, đổi bố cục giữa
chừng không mất tiến độ. Chưa kiểm trên máy thật; màn ôn tổng hợp cả cấp chưa có các chế độ này.

### 04/11/2026 — Giáo trình chỉ cho quản trị; lật thẻ nền trắng lật dọc; ẩn lời giải câu ví dụ

- **Giáo trình có bản quyền, chỉ quản trị đọc.** Học viên và biên tập không thấy mục trong Chuyển
  nhanh, vào thẳng `/learn/books` bị đưa về trang nhà (`canView` trong `routes.ts`). Ảnh không còn
  phát thẳng ở `/uploads/giao-trinh/` (`.htaccess` gốc và `dev-router.php` trả 403) mà đi qua
  `GET /api/admin/books/{ma}/{trang}` đòi quyền quản trị, `Cache-Control: private`, không tính vào
  giới hạn 300 request/phút. Service worker bỏ kho `zika-book`, lần kích hoạt tới tự xoá ảnh đã lưu.
- **Lật thẻ:** mặt trước, mặt sau và chồng thẻ phía sau đều nền trắng; lật dọc từ trên xuống
  (`rotateX`), 0,6 giây, đường cong vào–ra đều.
- **Câu ví dụ ở lật thẻ và ngữ pháp:** mặc định chỉ hiện chữ Hán; cặp nút **Pinyin | Nghĩa** (kiểu
  `.seg` giống màn hội thoại) ở đầu khối ví dụ bật/tắt riêng từng thứ. Lật thẻ giữ lựa chọn khi sang
  thẻ khác, mở bài khác thì tắt lại; ngữ pháp đổi điểm thì tắt lại. Đã thử icon mắt từng câu rồi bỏ
  vì nhìn rối (bộ icon không có mắt gạch chéo) và không bật riêng pinyin được.
- **Hội thoại mặc định ẩn pinyin + nghĩa** cho đồng bộ: mặc định `dialogue.showPinyin/showVi` đổi
  sang false ở `UserService.php` và `settings.svelte.ts`; migration **006** đặt lại false cho cài đặt
  đã lưu (giá trị true cũ chỉ là mặc định trước đây). Hai dòng trong Cài đặt vẫn dùng được.

Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 54/54 test web, 53/53 test API. Trên trình duyệt:
học viên bị 403 ở cả hai đường ảnh, quản trị lấy được ảnh (200 `image/webp`); thẻ trắng, lật
`rotateX`; nút Pinyin | Nghĩa ở HSK 1 Bài 2 (lật thẻ) và Bài 4 (ngữ pháp). Chưa kiểm trên máy thật.
Ngữ pháp HSK 1 Bài 1–2 không có điểm nào nên màn trắng trơn — lỗi có từ trước, chưa sửa.

Lên server: chạy **migration 006** (`php tools/install.php --migrate` hoặc import file SQL), chép **`.htaccess` gốc mới** (có luật chặn `uploads/giao-trinh/`) cùng code PHP,
rồi mở thử ảnh trực tiếp phải ra 403.

### 03/11/2026 — Đọc giáo trình bằng cuộn dọc liên tục như PDF

Mở sách hiển thị các trang nối tiếp, vuốt/cuộn lên xuống để đọc trên điện thoại và máy tính.
Bỏ hiệu ứng lật, vuốt ngang và vùng bấm hai nửa trang. Giữ ô đi tới số trang và nhớ trang đang đọc
riêng từng quyển; theo dõi trang qua IntersectionObserver, khôi phục dấu trang khi mở lại.
Ảnh tải dần, khai kích thước đúng của đủ 469 ảnh hiện có để không làm nhảy vị trí đọc.
Một ảnh lỗi chỉ hiện nút Thử lại ở trang đó. Hai bố cục dùng cùng khung nội dung.

Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 53/53 test web đạt; build production thành công,
web local trả HTTP 200. Đã cập nhật README và kịch bản kiểm giáo trình. Chưa cập nhật hosting.

Kiểm trên trình duyệt (04/11, khung 1024px và giả lập điện thoại 375px) theo GT1–GT5, sửa thêm 2 lỗi:
- Đổi chiều rộng (kéo qua 768px, xoay máy) làm trang đang đọc nhảy từ 95 sang 142 và ghi đè dấu
  trang, vì các trang thấp lại mà `scrollY` giữ nguyên. Nay neo lại đúng chỗ đọc theo tỉ lệ trong trang.
- Đi tới trang trên máy tính lộ chú thích "Trang n−1" phía trên, vì khoảng chừa cố định 128px chỉ
  đúng cho điện thoại. Nay chừa theo chiều cao thanh dính của từng bố cục.
Đã đối chiếu kích thước thật của cả 469 ảnh trang với số khai trong `sach.ts`: khớp hết. Chưa kiểm
trên máy thật.

### 03/11/2026 — Bỏ lớp nền lóe khi chạm trên điện thoại

Tắt lớp tô mặc định của trình duyệt khi chạm bằng `-webkit-tap-highlight-color: transparent`
trong CSS nền tảng. Vùng bấm, hành vi nút/thẻ, bóng đổ thiết kế và viền focus bàn phím giữ nguyên.
Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 53/53 test web đạt. Thay đổi ở local, chưa đóng gói lên server.

### 03/11/2026 — Bỏ vuốt chuyển từ ở lật thẻ trên điện thoại

Theo yêu cầu người dùng, bỏ nhận diện vuốt trái/phải ở cả màn lật thẻ theo bài và tổng hợp.
Chạm thẻ để lật; bấm nút ← / → để chuyển từ. Đổi gợi ý dưới thẻ thành
“Bấm ← / → để chuyển từ”, cập nhật kịch bản A8.

Kiểm tra: Svelte 0 lỗi/0 cảnh báo, lint sạch, 53/53 test web đạt; web local trả HTTP 200.

### 03/11/2026 — Làm lại toàn bộ giọng đọc bằng giọng Microsoft, rà UI/UX cả web

Người dùng thấy giọng các phần lệch nhau (Yunxi ở lật thẻ, Kokoro ở câu, băng thật ở bài khoá) và
hội thoại HSK 1 đọc khác phiên âm. Đã thay Kokoro bằng giọng Microsoft (edge-tts) cho **mọi** file đọc
mẫu, cùng một chuẩn; quy trình mới trong [HUONG-DAN-GIONG-DOC.md](data/nap-sach/HUONG-DAN-GIONG-DOC.md).

- **Sinh + tự kiểm** (`doc_edge.py`, theo đợt 200 file): 10.343 file → **10.077 đạt**, 266 cần người nghe
  (phần lớn là chữ đơn đứng riêng, máy nhận dạng thiếu ngữ cảnh), **0 lỗi**. Độ to trung vị -16,4 LUFS.
  Từ đơn không kéo dài âm (bỏ atempo), giọng tự đọc chậm 10%: trung vị 0,29 giây/âm tiết, 99% dưới 0,56.
  Tốc độ câu (bỏ khoảng nghỉ) nữ 5,39, nam 5,74 âm tiết/giây — chậm hơn băng giáo trình thật (6,22);
  432 câu nhanh hơn 5,8 đã sinh lại chậm thêm 15% (`doc_cham_lai.py`).
- **Câu thoại** đọc giọng riêng từng nhân vật (bảng `VAI`), phân theo cả bài: nữ chính Xiaoxiao, nữ thứ
  hai/bé gái Xiaoyi, người nữ lớn tuổi khi trùng Xiaoxiao hạ cao độ, nam trẻ Yunxi, nam trưởng thành
  Yunjian, người lớn tuổi/chức vụ Yunyang, bé trai Yunxia. Không khoá nào hai người trùng giọng, không ai
  đổi giọng trong bài (khớp database 822/822 câu).
- **Bài khoá:** băng thật HSK 1–2 đọc lệch sách Hanu ở **13 khoá** (越南人/法国人, ngày, số điện thoại,
  酒店/饭店, 首都/大兴…, `bang_lech_sach.json`) → thay bằng bản ghép; 68 khoá giữ băng thật, cân về
  -16 LUFS (trước -19,8); 63 khoá vốn không có băng ghép từ giọng mới.
- **Đọc đúng:** chữ đa âm ép bằng chữ đồng âm thông dụng (GB2312 cấp 1); "A/B" đọc cả hai cách; năm đọc
  từng số; khoảng lặng giữa câu > 0,6 giây rút còn 0,45.
- `TTS_VERSION = 6`; câu thoại (đường dẫn lưu trong database) cũng mang `?v=`.
- Nạp local bằng `tai_doc.py --nhanh` (PHP dòng lệnh `api/app/tools/nhap_tts.php`, cùng hàm đặt tên với
  API: 10.343 file trong vài phút; qua HTTP mất ~2 giờ vì `php -S` xử lý một yêu cầu một lúc).
- **Quét qua web** (`quet.py`, mỗi file phải trùng sha256 bản đã kiểm): 8.581 âm thanh + 697 nét chữ, **0 lỗi**.
- **Agent kiểm độc lập** (whisper-base + paraformer, đo độ to/khoảng lặng, đối chiếu database) tìm ra
  và đã sửa: 2 câu đọc vô nghĩa do ghép pinyin lệch khi có chữ số + nhi hoá (`am_sach` giờ bỏ qua câu có
  số và kiểm từng âm tiết là cách đọc của chữ đó), 2 khoá trùng giọng, chữ hiếm trong chữ gửi đi, lời giải
  thích trong ngoặc bị đọc. Kiểm lại lần 2: đạt (2 câu khớp 100% cả hai mô hình, 150 câu ngẫu nhiên không
  câu nào cả hai mô hình cùng lệch).
- **Gói server:** `release/cap-nhat-giong-moi-20261103-0355.zip` (198.905.056 byte, 164 file web + 10.487 mp3
  + `cap-nhat-am-thanh.sql` sửa `audio_path` 144 khoá + 822 câu), CRC/SHA-256 khớp — thay gói lật thẻ nam
  ngày 02/11. **Chưa đưa lên web thật.**
- **Cần người nghe:** `data/nap-sach/ra/nghe-tay-20261103.zip` — 266 file máy chưa chắc, 15 câu 得 děi /
  不得了, 306 file ngẫu nhiên kiểm chéo (`xuat_nghe_tay.py`).

**UI/UX** — bộ dò tự động (tràn ngang, chữ cắt, nút < 32px, nút đè, ảnh hỏng, tương phản WCAG AA) trên
45 màn × 375/768/1366 px × sáng/tối, cộng một agent kiểm độc lập theo kịch bản:
- Tương phản: `--text-3` #8A909C → #626873, nút đỏ chính #D23B41, `--son-text` riêng cho nền tối,
  `--bad-text` #C81E1E; avatar nhân vật, khối công thức ngữ pháp, cặp nối từ giữ chữ đậm trên nền pastel ở
  nền tối; trang đăng nhập nền tối; `.pick` và nút chọn `.seg` nền tối.
- Cỡ chạm: `.seg` 36px (điện thoại 40px), `.btn-text` 32px, logo 40px, ô màu và đường dẫn quản trị trên điện thoại.
- **Lỗi thật:** câu "Nối từ với nghĩa" ở bài tập không xáo cột nghĩa (lộ đáp án) → xáo cố định theo nội dung,
  chấm vẫn đúng; bảng `/admin/lessons` đẩy nút Sửa ra ngoài ở 1366px; lật thẻ làm trang nhảy 7px
  (`scrollbar-gutter`) và tràn ngang lúc thẻ trượt vào; soạn hội thoại: nút Lên dùng nhầm mũi tên, nút
  Lên/Xuống ở đầu/cuối vẫn bấm được, ô nhân vật thiếu nhãn; `/admin/words` biểu tượng mắt ngược nghĩa;
  Cài đặt ghi rõ lật thẻ luôn giọng nam.

### 03/11/2026 — Làm lại chuông thông báo, sửa bảng tràn mép trên điện thoại

Người dùng chê bảng thông báo xấu và vỡ trên điện thoại.
- Bảng thông báo: bỏ nền hồng, vạch đỏ, nhãn "Mới" và link đỏ chồng nhau. Có tiêu đề "Thông báo" + "Đọc hết",
  tab dạng nút gạt, mỗi dòng có ô chuông; chưa đọc = ô màu son + tiêu đề đậm + chấm đỏ, giờ dạng "hôm qua".
  Trang `/notifications` cùng kiểu.
- Điện thoại: chuông và nút 9 chấm không sát mép phải, bảng neo `right: 0` theo nút tràn mép trái.
  Nay ghim dưới thanh trên, chừa 12px với đường kẻ của thanh và hai mép màn (`lib/utils/neo.ts`);
  cả hai bảng có nền mờ giống nhau, chỉ phủ dưới thanh trên;
  lúc mở nâng `.mtop` lên trên `.mbar`/`.msheet` để thanh dưới không đè.
- Lưới thẻ HSK ở 5 trang tổng (Lật thẻ, Bài tập, Hội thoại, Ngữ pháp, Kiểm tra) tràn ngang 7px ở 375px:
  `1fr 1fr` → `minmax(0, 1fr) minmax(0, 1fr)`.

Kiểm: 13 màn học ở 375px không tràn ngang, hai bảng nằm trong màn và không bị phần nào đè; 768/1366px đạt;
chế độ tối đạt. Web 0 lỗi/0 cảnh báo, lint sạch, 52/52 test.

### 02/11/2026 23:48 — Kiểm web thật: gói lật thẻ nam chưa lên server

Đối chiếu từng file của `cap-nhat-lat-the-nam-20261102.zip` với https://tiengtrunghanu.io.vn
(HEAD/GET, xét `content-type` vì file không có vẫn trả `index.html` mã 200):

| Nhóm | Kết quả |
|---|---|
| Web build | vẫn bản 07:06 (`version 1793577989636`, `start.CTCPoi23.js`); 61 file `_app` mới không có |
| 1.788 mp3 mới (894 nam + 894 nữ theo chữ + pinyin) | thiếu cả 1.788 |
| 2.648 mp3 đọc chậm (gói 14:14) | còn bản cũ, khác kích thước |
| 5.838 mp3 còn lại | khớp |

Mọi mp3 trên server có `Last-Modified` 07:19 sáng 02/11, tức chưa chép gói nào sau lần nạp đầu.
Thử `还 hái/huán`, `头 tóu`, `道 dào` (giọng nam, `?v=5`): đều trả trang HTML, không phải `audio/mpeg`.
Không thấy gói bị giải nén nhầm thư mục (`/cap-nhat-lat-the-nam-20261102/`, `/public/`, `/uploads/uploads/`).

### 02/11/2026 — Kiểm tra lại phát âm, chuyển từ lật thẻ sang giọng nam Yunxi

Người dùng báo cả nhóm từ đơn đọc không đúng và yêu cầu lật thẻ giọng nam. Lượt quét trước chỉ
kiểm file thiếu/hỏng/câm/tốc độ, không đủ để kết luận phát âm đúng. Đối chiếu pinyin phát hiện
`头 tóu` dùng file `tou`, `道 dào` dùng file `dao`, `还 huán` dùng file `hái` do chỉ băm chữ Hán.

Đã đổi từ ở cả màn học theo bài và tổng hợp sang file theo **chữ + pinyin**, cố định giọng nam;
nút nghe câu ví dụ cũng dùng nam. Backend nhận `reading` tuỳ chọn khi tải TTS; web/PHP/Python dùng
cùng quy tắc băm, chuẩn hoá NFC, bỏ khoảng trắng và viết thường pinyin. Giữ nguyên đường dẫn cũ
cho những nơi không truyền pinyin.

Đã tạo 894 file nam Yunxi cho 932 thẻ (các từ trùng dùng chung), trong tổng 10.274 file TTS.
Dịch vụ tạo từng nhóm 10 từ, chỉ tách khi mốc WordBoundary khớp đúng chữ. Các chữ dễ đọc sai
được chọn âm trong `doc_tu_nam.py::chu_doc()` (có phân biệt `还 hái/huán`, `只 zhī`, `长 cháng`,
`地 de`, `粘人 niánrén`). File gốc/mốc cắt lưu tại `ra/yunxi-batch/`; tạo mới cần mạng và
`pip install edge-tts av`, phát trên web chỉ dùng mp3 đã có.

Giọng Yunxi sinh ở -20%, sau đó dùng atempo giữ cao độ để đọc chậm. Đo đoạn có tiếng trên đủ
894 file: trung vị 1 chữ **0,491**, 2 chữ **0,486**, 3 chữ **0,435**, 4 chữ **0,434**, 5 chữ **0,433**
giây/chữ (`ra/do_toc_do_yunxi.json`). `TTS_VERSION = 5` để bỏ cache file nam cũ.

Đã rà nhận dạng độc lập 40 mục đầu; kết quả mới rõ hơn bộ Kokoro chậm. Nhận dạng chỉ dùng để
sàng lọc, không phải chứng nhận toàn bộ thanh điệu đúng. Báo cáo trong `ra/kiem_nhan_dang_nam.json`
và `ra/kiem_nhan_dang_yunxi.json`.

Kiểm tra: web 0 lỗi/0 cảnh báo, lint sạch, 52/52 test; API 49/49 test; 3 test xử lý âm thanh
(giữ cao độ khi kéo dài, không tăng tốc file đã chậm, từ chối file câm) đều đạt.
Đã tải đủ bộ nam mới. Quét qua web lúc 14:46: **8.537 file âm thanh + 697 file nét chữ, 0 lỗi**;
pinyin thực tế của đủ 932 thẻ đều có file tương ứng. Chưa cập nhật hosting.
Đối chiếu SHA-256 đủ 894 file nam sau tải: khớp nguồn. Build production thành công,
`release/zika-release-20261102-1447.zip`. Gói cập nhật riêng:
`release/cap-nhat-lat-the-nam-20261102.zip`, 145.368.873 byte, 166 file web + 10.274 mp3;
CRC và SHA-256 từng file trong zip đều khớp nguồn. SHA-256 của zip:
`4dff521b2b4f3945e0587e8011e65b90a245370f8c58bc1cd62cb96176985ea3`.
Đã xuất lại `dong-goi-server`, bật lại Vite để người dùng thử local. Không import lại SQL khi cập nhật server.

### 02/11/2026 — Từ và chữ đơn đọc chậm lại

Giữ `TOC_TU = 0.58`, `TOC_TU_DAI = 0.52` và `TTS_VERSION = 2` từ commit `4286d0d`.
Đã kiểm đủ 8.486 mp3 trên đĩa: không file thiếu, hỏng hoặc câm. Đo 2.648 file từ/chữ 1–4 chữ
(cả nữ và nam), lấy đoạn từ mẫu đầu đến mẫu cuối có `abs(a) > 0.02`, chia số chữ Hán:

| Số chữ | File | Trung vị giây/âm tiết | Phân vị 10–90% |
|---|---|---|---|
| 1 | 1.394 | 0,585 | 0,511–0,657 |
| 2 | 1.084 | 0,454 | 0,413–0,486 |
| 3 | 138 | 0,519 | 0,385–0,693 |
| 4 | 32 | 0,619 | 0,542–0,684 |

Chi tiết phép đo: `data/nap-sach/ra/do_toc_do.json` (chỉ lưu tại máy).
Bộ quét `quet.py` bỏ áp ngưỡng tốc độ câu cho từ 1–4 chữ vì nhóm này được sinh chậm riêng;
trước đó 30 file 4 chữ bị báo chậm do tính cả khoảng lặng đầu/cuối. Vẫn kiểm thiếu/hỏng/câm cho
mọi file và kiểm tốc độ câu từ 5 chữ. Đã thử 7 trường hợp âm thanh: bình thường, nhanh, chậm,
từ ngắn đọc chậm, câm, hỏng, thiếu — kết quả đúng.
Bộ quét dùng `127.0.0.1:5173` khớp Vite chỉ nghe IPv4. Đo 3 lượt tải cùng một mp3 trên Windows:
`localhost` mất 6,188 giây, `127.0.0.1` mất 0,008 giây; tránh thử kết nối IPv6 thất bại cho từng file.

Kiểm tra code: Svelte 0 lỗi/0 cảnh báo, lint sạch, web 47/47 test, API 46/46 test (611 assertions).
`python kiem_vi_du.py 1 2 3`: 320 + 172 + 440 từ, 0 lỗi.

Đã tải lại 2.648 file vào local, tổng đủ 8.486; đối chiếu SHA-256 với nguồn: 0 sai lệch.
Gắn âm thanh cho 40 bài, ghép 63 bài khoá, 0 câu thiếu file. Quét qua web lúc 14:14:
7.949 file âm thanh + 697 file nét chữ, **0 lỗi** (chi tiết ở cuối nhật ký).

**Đã đóng gói:** build thành công `release/zika-release-20261102-1414.zip`; chạy lại `xuat_sql.py`
ra `D:/zika-devtools/Project/dong-goi-server/`. Gói riêng `release/cap-nhat-giong-doc-20261102.zip`
gồm 166 file web + 8.486 mp3, 129.462.282 byte (123,46 MiB), đường dẫn `/`, không có API/PHP/config.
Đã kiểm CRC và đối chiếu SHA-256 từng file trong zip với nguồn. Manifest và file `.zip.sha256`
nằm cạnh zip; SHA-256 của zip: `fa14050e602f7b378541c2cb2482ec3e04a45235ace987d75b61bcce4718fc36`.

**Còn tiếp:** triển khai và xác minh web thật; chưa có kết nối hosting trong phiên này.
Từ môi trường hiện tại, tải thử mp3 trên web thật trả HTTP 403; chưa xác minh được bản mới trên server.

### 02/11/2026 — Ví dụ lật thẻ, giọng đọc Kokoro, bộ thủ, chuông thông báo

**Nội dung**
- 1.864 câu ví dụ mới cho 932 từ HSK 1–3 (2 câu/từ, ngắn theo cấp, pinyin và nghĩa đầy đủ),
  thay ví dụ cũ lấy từ đoạn hội thoại dài. Kiểm tự động 0 lỗi.
- Phân tích 697 chữ ở màn Luyện viết: bộ thủ (tên, nghĩa, số bộ), câu giải thích cấu tạo
  (314 chữ có giải thích soạn tay kèm mẹo nhớ, còn lại sinh từ dữ liệu hình thanh/hội ý),
  3–4 chữ khác cùng bộ ưu tiên chữ đã học.
- Giọng đọc mẫu Kokoro (giọng người, chạy trên máy) cho mọi từ, câu ví dụ, chữ luyện viết,
  ví dụ ngữ pháp, câu hỏi nghe — đủ giọng nữ và nam theo Cài đặt; câu thoại đọc đúng giọng
  nhân vật. Ép đọc đúng pinyin của sách cho chữ đa âm (还 huán/hái, 得 děi/de…).
- Bài khoá chưa có file thu thật (HSK 3, HSK 1 bài 2 khoá 1–3) có file ghép từ giọng Kokoro.
  HSK 1–2 còn lại giữ file thu thật của giáo trình.

**Tính năng / sửa lỗi**
- Chuông thông báo cho học viên (cạnh ảnh đại diện, mọi màn học): admin đăng ở Quản trị →
  Thông báo là học viên thấy số đỏ trong ≤ 2 phút; bảng "Tất cả (n)", "Đánh dấu đã xem tất cả",
  nhãn Mới/Đã đọc, trang `/notifications` đọc đủ nội dung. Trước đây thông báo có tải về nhưng
  không chỗ nào hiển thị. Hai tab "Tất cả (n)" / "Chưa đọc (m)"; chưa đọc = nền hồng, vạch và chấm đỏ,
  tiêu đề đậm, nhãn "Mới"; đã đọc = nền trắng, tiêu đề xám, "✓ Đã đọc"; đọc hết thì nút đổi thành
  "✓ Đã xem tất cả" (trước đó nút bị ẩn nên không biết đã đọc hết hay chưa).
- Lật thẻ: thẻ cao theo màn hình điện thoại, chữ "Chạm để lật", ví dụ có pinyin tô màu thanh,
  nút nghe to cho ngón tay.
- Sửa lỗi trắng trang ở lật thẻ khi pinyin có âm tiết lặp (常常, 不…不): trùng khoá vòng lặp Svelte.
- Luyện viết trên điện thoại: dải chữ cuộn ngang, ô viết to (≤ 340px) nằm ngay màn đầu; đã thử
  viết bằng chạm ngón tay, trang không bị cuộn theo.
- Quản trị → Từ vựng: chỉ hiện số từ thật ("320 từ"), bỏ dạng "320/150".
- Service worker không còn chặn file mp3 (thẻ audio xin từng đoạn, phản hồi 206 không cất được
  vào cache nên file câm, nhất là Safari/iPhone).
- Lưu hội thoại ở trang quản trị không còn xoá âm thanh của từng câu thoại.

**Quét lỗi và test (02/11/2026)** — `quet.py` tải thử từng file qua web như học viên:
- Lần 1: thiếu âm thanh chữ 条 (chỉ nằm trong từ nhi hoá 面条儿, bị bỏ sót khi tách chữ) → đã sinh.
- Đo tốc độ toàn bộ 5.706 câu ≥ 4 chữ: trung vị 2,9 âm tiết/giây (vừa cho người học); 79 câu
  nhanh hơn 3,8 (câu thoại dài) → sinh lại chậm hơn, về ~3,4. Đoạn nhiều câu đọc từng câu, nghỉ 0,3 giây.
- Lần cuối: còn 2 câu số điện thoại (HSK 1 bài 6) — Kokoro đọc 0932463321 thành "九亿三千…" →
  sửa đọc từng số (1 = yāo); cùng lúc sửa 13 ví dụ dạng "A：… B：…" (bỏ nhãn, A/B hai giọng) và
  "HSK" (đọc 汉语水平考试 vì giọng tiếng Trung không đọc chữ cái). 54 file sinh lại, kiểm riêng: 0 lỗi.
- Không file âm thanh nào thiếu/hỏng/câm; 697 file nét chữ đủ, số nét khớp; 697 chữ đủ bộ thủ + phân tích.
- Test giao diện theo [KICH-BAN-TEST.md](KICH-BAN-TEST.md) ở 375px / 768px / 1366px: lật thẻ (tự đọc
  khi lật, 2 ví dụ, nghe từng câu), luyện viết (viết bằng chạm ngón tay, bộ thủ, chữ cùng bộ),
  hội thoại (file thật HSK 1–2, file ghép HSK 3, từng câu đúng giọng nhân vật), ngữ pháp, chuông
  thông báo (admin đăng → số đỏ → đọc → đánh dấu đã xem tất cả). Lỗi tìm ra khi test và đã sửa:
  bấm nghe liên tiếp làm câu trước bị đánh dấu "thiếu file" và rơi sang giọng khác; bảng chuông tự
  đóng khi bấm "Đánh dấu đã xem tất cả".
- Kiểm tra code: web 514 file 0 lỗi 0 cảnh báo, lint sạch, 47/47 test; API 46/46.

**Gói đưa lên server:** `python xuat_sql.py` → `Project/dong-goi-server/` (ngoài repo): 2 file SQL +
`uploads/` (tts, audio, hanzi, giao-trinh) + HUONG-DAN.md (bản gốc: `data/nap-sach/HUONG-DAN-SERVER.md`).
Đã nạp thử vào MariaDB trống: số dòng khớp 100% local, chạy lại 2 lần không lỗi, người dùng giữ nguyên,
9.327 file database trỏ tới đều có trong gói.

**Đã đưa lên web thật (02/11/2026):** xoá nội dung cũ, import 2 file SQL, chép code (`af65846`) và
`uploads`, xoá `storage/cache/content`. Kiểm tra: dữ liệu web thật **giống hệt local** (dấu vân tay
40 bài × 5 phần trùng), 8.551 file âm thanh + 697 nét chữ tải được, kịch bản giao diện đạt —
chi tiết cuối [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

**Database:** migration `004_dialogue_audio.sql` (file nghe cả bài khoá),
`005_character_analysis.sql` (bộ thủ, phân tích chữ). Server thật cần chạy
`php app/tools/install.php --migrate`.

## Lần quét gần nhất — 03/11/2026 03:50

Sinh tự động bởi `data/nap-sach/quet.py`, đừng sửa tay mục này.

| Hạng mục | Số lượng |
|---|---|
| từ | 932 |
| câu ví dụ | 1.864 |
| chữ luyện viết | 1.567 |
| bài khoá | 144 |
| câu thoại | 822 |
| câu hỏi nghe | 149 |
| ví dụ ngữ pháp | 653 |
| file âm thanh | 8.581 |
| file nét chữ | 697 |

| Lỗi | Số chỗ | Ví dụ |
|---|---|---|
| Không có lỗi | 0 | |
