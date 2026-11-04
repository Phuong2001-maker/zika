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
