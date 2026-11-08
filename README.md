# Zìkǎ (字卡)

Web học tiếng Trung miễn phí theo cấp **HSK 1 → 6** bằng thẻ lật, có 6 phần học (Lật thẻ, Bài tập, Kiểm tra, Luyện viết, Đọc hội thoại, Cấu trúc ngữ pháp) và trang quản trị.

- **Frontend:** SvelteKit 2 + Svelte 5 (SPA tĩnh, cài được như app nhờ PWA) · TypeScript · CSS tự viết theo bộ thiết kế “giấy sáng · viền mảnh” (`mockup/update/_ds.css`).
- **Backend:** PHP 8.4 thuần (API JSON, không framework) · MySQL 8 / MariaDB 10.6+.
- **Chạy trên:** hosting dùng chung LiteSpeed (không cần root), có Cron.
- **Đặc tả:** thư mục `zika-spec/` nằm cạnh repo này (README + docs/01–09 + mockup). Quyết định ngoài đặc tả: [DECISIONS.md](DECISIONS.md).

## Cấu trúc

```
web/          SvelteKit (src/routes = màn hình, src/lib = component, store, tiện ích)
api/public/    api/index.php — cửa vào duy nhất của API (đặt trong public_html/api)
api/app/       mã PHP (đặt NGOÀI public_html): src/, cron/, tools/, migrations/
api/tests/     PHPUnit
deploy/            .htaccess + build-release.sh (đóng gói để upload)
dev/               file .cmd bật MariaDB / API / web trên máy Windows
```

## Chạy trên máy dev

1. Tạo `api/app/config.php` từ `config.example.php` (`env = 'local'`, `cookie.secure = false`, `app_url = 'http://localhost:5173'`, `paths.uploads_dir` trỏ tới `api/dev-uploads`).
2. Cài thư viện: `cd web && npm install` · `cd api/app && composer install`.
3. Tạo bảng + dữ liệu mẫu + admin đầu tiên: `php api/app/tools/install.php` (khi `env = local` tự nạp dữ liệu mẫu HSK 2 · Bài 6).
4. Bật: `dev\CHAY-TAT-CA.cmd` (Windows), hoặc tự chạy
   - API: `php -S 127.0.0.1:8787 api/dev-router.php`
   - Web: `cd web && npm run dev` → http://localhost:5173

Email ở máy dev không gửi thật mà ghi vào `api/app/storage/logs/mail-YYYY-MM-DD.log`.

### Ba tài khoản mẫu (chỉ dùng ở máy dev)

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Quản trị (admin) | `admin@zika.local` | `Zika@2026` |
| Biên tập (editor) | `bientap@zika.local` | `Zika@2026` |
| Người học (learner) | `hocvien@zika.local` | `Zika@2026` |

Admin vào được cả `/admin` lẫn phần học `/learn` (link “Về trang học” ở chân menu quản trị, nút
“Vào trang quản trị” ở trang Tài khoản); biên tập chỉ thấy phần soạn bài trong `/admin`; người học
chỉ thấy phần `/learn`. Đổi mật khẩu trong
`Tài khoản → Bảo mật`, hoặc đặt lại bằng:

```bash
php api/app/tools/install.php --reset-demo-password
```

## Nhập nội dung từ Excel

Sáu trang soạn bài (Từ vựng, Bài tập, Kiểm tra, Luyện viết, Hội thoại, Ngữ pháp) đều có nút
**Nhập Excel**. Trong hộp thoại: bấm **Tải file mẫu** để lấy đúng khuôn cột kèm ví dụ, điền vào
rồi chọn file — hoặc bôi đen vùng bảng trong Excel và dán thẳng vào ô.

Nhận `.xlsx`, `.csv` và nội dung dán. File được đọc ngay trong trình duyệt nên máy chủ không cần
thư viện Excel. Trước khi ghi, hộp thoại hiện bảng xem trước và liệt kê dòng nào sai, sai chỗ nào;
các dòng còn lại vẫn nhập được. Chọn **Thêm vào cuối** hoặc **Xoá hết rồi nhập mới**.

## Giáo trình (đọc sách, nghe file ghi âm, xem đáp án)

Bấm **Giáo trình** ở bảng Chuyển nhanh → kệ sách. **Học viên chỉ thấy bộ sách của giáo trình mình học**: giáo trình gọi
cấp là "Quyển" (Hán ngữ 6 tập) thấy 6 quyển Hán ngữ, giáo trình gọi "HSK" thấy 3 quyển HSK 1–3 (Đại học Hà Nội).
Biên tập và quản trị thấy cả hai bộ. Mở quyển bộ khác bằng URL thì ra "Không có quyển giáo trình này" (API trả 404).

| Bộ | Quyển | File nghe | Đáp án |
|---|---|---|---|
| Hán ngữ 6 tập | Hán ngữ 1–6 (1.558 trang) | cả 6 quyển (181 file, ~9 giờ) | Hán ngữ 3–6 (bản YUEXIN) |
| HSK 3.0 | HSK 1–3 (469 trang) | HSK 1, HSK 2 (143 file) | không có |

Màn đọc: cuộn dọc như PDF, thanh số trang đi thẳng tới trang, **Mục lục** (bài nào có file nghe, có đáp án đều đánh dấu),
đầu mỗi bài có dải tên bài với nút **Nghe bài này** và **Đáp án**. Thanh phát dính đáy màn hình: phát/tạm dừng, lùi/tới 5 giây,
tốc độ 0,5–1,25×, lặp một file, sang file trước/sau, hết file tự nghe phần sau của cùng bài; điều khiển được cả ở màn hình khoá
điện thoại. Nút **Sách / Đáp án** đổi sang ảnh trang đáp án, đúng bài đang đọc. Quyển nào không có file nghe hay đáp án thì
các nút đó tự ẩn. Chỗ đọc dở (sách và đáp án riêng) và tốc độ nghe nhớ bằng `localStorage`.

Dữ liệu dựng bằng một script, đọc PDF sách, file nghe và file Word đáp án ở `D:\zika-devtools\Project\` (`HanNgu\`, `hsk1\`,
`hsk2\`, 3 PDF HSK):

```bash
cd data/giao-trinh && python dung-sach.py                 # cả 9 quyển, vào api/dev-uploads
cd data/giao-trinh && python dung-sach.py --chi hn3,hn4   # chỉ vài quyển
```

Mỗi quyển ra `uploads/giao-trinh/<mã>/`: `bia.webp` + `001.webp…` (rộng 1200px), `nghe/01.mp3…`, `dap-an/001.webp…`
và `sach.json` (tên, chiều cao từng trang, mục lục, danh sách file nghe kèm thời lượng, đáp án, `phien`). API chỉ đọc
`sach.json` — thêm quyển là chạy script, không sửa code. Đáp án Word phải đổi sang PDF trước bằng Word
(`data/giao-trinh/ra/dap-an/hn3.pdf`…, xem đầu script). File MP3 bitrate thay đổi mà thiếu header Xing (bộ Hán ngữ 6) được
ffmpeg đóng gói lại, không nén lại; thiếu bước này trình duyệt đoán sai thời lượng 15% và không tua quá mốc đoán sai.
Cần `pymupdf`, `Pillow`, `imageio-ffmpeg`. Cả 9 quyển ~740MB.

> **Không nằm trong git.** Đẩy code lên hosting là chưa đủ — phải chép cả thư mục `giao-trinh/` vào `uploads/` của server (zip ở `../dong-goi-giao-trinh/giao-trinh-*.zip`, giải nén tại `/home/buwsofujhosting`)
> (`uploads/giao-trinh/hn1/sach.json`…). Thiếu `sach.json` thì kệ sách trống.

> **Không phát thẳng.** `.htaccess` gốc trả 403 cho mọi đường `uploads/giao-trinh/`; trình duyệt lấy qua
> `GET /api/learn/books/{mã}/{trang|bia}`, `/dap-an/{trang}`, `/nghe/{số}` (đòi đăng nhập, `Cache-Control: private`,
> file nghe trả từng đoạn 206 để tua được). Đường dẫn gắn `?v=<phien>` nên dựng lại sách thì trình duyệt tự bỏ bản cũ.
> Service worker không cache gì dưới `/api/learn/books`. Sau khi deploy, mở thử
> `https://<tên miền>/uploads/giao-trinh/hn1/001.webp` — phải ra 403, không được ra ảnh.

### Điểm mở bài (migration 019)

Học viên **không đọc được bài chưa mở**: trang sách, trang đáp án và file nghe của bài đó server trả 403, kể cả khi gõ
thẳng đường dẫn hay dùng công cụ tải. Biên tập và quản trị đọc tự do.

- **Điểm:** lần đầu bấm nút **Nhận điểm** được **3 điểm**, từ hôm sau mỗi ngày bấm được **1 điểm**
  (ngày tính 0h–24h giờ Việt Nam). Bấm nhiều lần trong ngày vẫn chỉ 1; điểm chưa dùng giữ lại, ngày không bấm thì không bù.
  Điểm chỉ cộng ở `POST /learn/points/daily`, không phụ thuộc link Shopee: bé túi và link Quỹ tháng (`/go/{id}`) chỉ dẫn
  sang Shopee và ghi lượt ủng hộ, không cộng điểm (tách ngày 08/11/2026).
- **Xem điểm ở đâu:** mọi màn học đều có ô điểm đứng đầu cụm nút ở thanh đầu trang — máy tính: "2 điểm mở bài · Nhận +1
  hôm nay" (`Topbar`), điện thoại: ô số (`MTopbar`); hôm nay chưa nhận thì có nhãn vàng "+1". Bấm là về kệ Giáo trình; đầu kệ
  có ví điểm (số điểm, đã mở bao nhiêu bài, nút Nhận điểm); thẻ khoá trong màn đọc có nút dùng điểm và nhận điểm.
- **Mở bài:** 1 điểm mở 1 bài, **lần lượt theo thứ tự** trong bộ sách (Quyển 1 bài 1 → … → Quyển 2 bài 16…). Bài đã mở là
  mở mãi. Trang trước bài đầu (bìa trong, lời nói đầu, mục lục) đọc tự do, nhưng chỉ ở quyển đã học tới — quyển sau còn khoá
  cả trang đầu, kệ sách hiện xám kèm "Mở hết <quyển trước>".
- **Bé túi:** không liên quan đến điểm. Tải lại trang là bé hiện để người dùng luôn có lối ghé Shopee; bấm vào bé thì mở
  link ở tab mới và bé ẩn đến lần tải trang sau.
- **Không bán sách:** đã làm phần bán gói (trọn bộ, chuyển khoản, quản trị duyệt) rồi bỏ ngày 08/11/2026 — bán bản scan sách
  có bản quyền rủi ro pháp lý cao. Học viên chỉ mở bài bằng điểm.
- **Chế độ test điểm** (Quản trị → Điểm mở bài): bật thì mỗi lần bấm Nhận điểm đều +1 điểm, không chờ sang
  ngày, để thử nhanh. Đang bật thì đầu màn quản trị có dải vàng nhắc. **Phải tắt trước khi chạy thật.**
- **Mã ký đường dẫn:** mọi đường dẫn ảnh trang và file nghe phải kèm `?t=<hết hạn>.<chữ ký>` do API cấp, gắn với tài khoản
  và quyển, sống 6 giờ (ký bằng `app_key`). Link dán sang tài khoản khác, sửa một ký tự, hay để quá 6 giờ đều 403. Màn đọc
  tự lấy mã mới trước khi hết hạn; ảnh hay file nghe lỗi giữa chừng thì lấy mã mới rồi tải lại.
- **Chặn lưu ở trình duyệt:** màn đọc tắt chuột phải, giữ ngón tay (menu "Lưu hình ảnh" của iOS), kéo ảnh ra ngoài, Ctrl+S,
  Ctrl+P và không in trang sách. Lớp này chỉ chặn người dùng bình thường — ai chụp màn hình vẫn chụp được; chặn thật là ở server.

Quản trị → **Điểm mở bài**: bảng mọi học viên (giáo trình, số điểm, số bài đã mở, lần nhận điểm gần nhất; tìm theo tên/email,
xếp theo nhiều điểm nhất hoặc nhận gần đây), nút −1/+1 ngay trên dòng, **Chi tiết** để cộng/trừ số tuỳ ý kèm lý do và xem
sổ điểm; công tắc Chế độ test. Mọi lần chỉnh tay đều ghi sổ (`book_points.kind = admin`) để tra lại khi hệ thống tính sai.

## Quán trà (phòng trò chuyện chung) và Góp ý

- **Quán trà** (`/learn/chat`, mở từ Chuyển nhanh): phòng trò chuyện chia **bàn** theo chủ đề — Bàn chung, Hỏi bài, Mẹo học,
  Tìm bạn học, Góc tiếng Trung (quản trị thêm/sửa/đóng bàn; đóng bàn là chỉ đọc). Danh sách bàn hiện tin mới nhất, số tin 24
  giờ, chấm "Tin mới" (tin cuối đã xem lưu ở máy). Trong bàn: gửi tin, trả lời một tin, xoá tin của mình, báo cáo tin xấu
  (tin của biên tập/quản trị không báo cáo được — có ý kiến thì gửi Góp ý). Chỉ hiện tên hiển thị, không lộ email. Hosting không có WebSocket nên web hỏi tin
  mới mỗi 4 giây khi tab đang mở. Chống phá: cách nhau ≥ 2 giây, tối đa 20 tin/5 phút, tin ≤ 1.000 ký tự.
  Quản trị → **Quán trà**: tin bị báo cáo, tin gần đây (kèm tên bàn), cấm chat (1 giờ… vĩnh viễn), bỏ cấm, các bàn, đóng/mở
  cả quán. Bảng `chat_messages`, `chat_reports`, `chat_mutes` (migration 020), `chat_rooms` (021); cài đặt `chat.enabled`.
- **Góp ý · báo lỗi** (Chuyển nhanh → "Góp ý · báo lỗi màn này"): tự kèm phần/cấp/bài/trang sách đang mở; quản trị đọc và
  trả lời ở **Góp ý**, học viên đọc trả lời ngay trong hộp Góp ý (SMTP chưa chạy thì đây là nơi duy nhất).

## Nhiều bộ giáo trình

Mỗi học viên học một giáo trình do quản trị gán ở **Giáo trình → Phân quyền học**; chưa gán thì học giáo trình mặc định,
hiện là **Hán ngữ 6 tập**, cho cả tài khoản cũ lẫn mới đăng ký. Ai học HSK thì gán **Giáo trình HSK 3.0** (giáo trình số 1).
Đổi mặc định bằng công tắc **Giáo trình mặc định** trong form giáo trình; luôn đúng một giáo trình bật.
Mọi giáo trình dùng chung 6 cấp, form giáo trình có mục **Học viên thấy cấp là** để chọn cách gọi: `HSK 1, HSK 2…` hoặc
`Quyển 1, Quyển 2…`. Màn học lấy chữ này qua `web/src/lib/stores/cap.svelte.ts`, nên đừng viết cứng "HSK n" ở màn học.
"Thi thử HSK n" là ngoại lệ vì đó là đề HSK dùng chung mọi giáo trình. Đường dẫn vẫn là `/hsk/n` ở mọi giáo trình.

Giáo trình **Hán ngữ 6 tập** có 76 bài, chỉ gồm Lật thẻ, Luyện viết, Hội thoại và Ngữ pháp. Cách nạp và cách đưa lên server
ở [data/han-ngu/README.md](data/han-ngu/README.md).

## Kiểm thử

```bash
cd web && npm test && npm run check && npm run lint
cd api && php app/vendor/bin/phpunit -c phpunit.xml   # cần database zika_test
```

## Đưa lên hosting

Chi tiết trong `zika-spec/docs/02-kien-truc.md` §8 và checklist `09` §5. Tóm tắt:

### 1. Đóng gói

```bash
bash deploy/build-release.sh
```

Trên Windows không có `php`/`composer` trong PATH thì chỉ đường dẫn thẳng (đặt tên biến là `ZIKA_COMPOSER`, **không** phải `COMPOSER` — Composer đọc biến đó làm đường dẫn `composer.json`):

```bash
ZIKA_PHP="D:/zika-devtools/php/php.exe" ZIKA_COMPOSER="D:/zika-devtools/php/php.exe D:/zika-devtools/composer.phar" bash deploy/build-release.sh
```

Kết quả `release/zika-release-<ngày giờ>.zip` gồm hai thư mục:

| Trong zip | Giải nén vào | Gồm |
|---|---|---|
| `public/` | `public_html` (DOCROOT) | `index.html`, `_app/`, `manifest.webmanifest`, `service-worker.js`, icon, `api/index.php`, `.htaccess`, `uploads/.htaccess` |
| `app/` | `<APP_DIR>` — **ngoài** `public_html` | `bootstrap.php`, `src/`, `cron/`, `tools/`, `migrations/`, `vendor/`, `storage/` |

Thư mục `uploads/` phải cho PHP ghi được (755 hoặc 775), `app/storage/` cũng vậy.

### 1b. Bốn cái bẫy khi giải nén bằng File Manager — đã sa vào thật 23/10/2026

Nếu đẩy lên bằng File Manager của panel (1Panel, cPanel…) thay vì SSH thì đọc kỹ bốn mục này.
Cả bốn đều đã làm sập web thật một lần.

1. **Paste của File Manager THAY CẢ THƯ MỤC, không trộn vào.** Dán `app/` đè lên `<APP_DIR>` là
   **xoá mất `config.php`** (file này cố ý không nằm trong zip) và xoá sạch `storage/logs`,
   `storage/cache`. Triệu chứng: API trả **500 với thân rỗng và không ghi log nào** — vì log cũng
   vừa bị xoá. Chép `config.php` ra chỗ khác trước khi dán, hoặc dán từng file thay vì cả thư mục.

2. **`api/index.php` trong repo trỏ tới `<DOCROOT>/../../../../zika_app`.** Đó là đường dẫn mặc
   định theo tài liệu, gần như chắc chắn **không trùng** chỗ bạn đặt `<APP_DIR>` thật. Mỗi lần
   deploy đè file này đều phải sửa lại dòng `$appDir`, hoặc đặt biến môi trường `ZIKA_APP_DIR`.
   Triệu chứng giống hệt mục 1: 500 thân rỗng.

3. **Đừng copy thư mục `uploads` từ zip.** Trong zip nó chỉ có `.htaccess`, mà trên server
   `uploads/` đang chứa ảnh giáo trình (hàng chục MB) và toàn bộ file nét chữ.

4. **Dò file bằng mã HTTP 200 là sai.** `.htaccess` gốc có `RewriteCond %{REQUEST_FILENAME} !-f`
   nên **mọi** đường dẫn không tồn tại đều trả `index.html` kèm mã **200**. Phải xem
   `content-type`. Thêm nữa service worker cache cả những đường dẫn 404 cũ, nên sau khi đẩy file
   lên trình duyệt vẫn trả bản cũ — kiểm bằng `?v=<ngẫu nhiên>` và bảo người dùng Ctrl+Shift+R.

Sau khi deploy xong, nếu có đổi nội dung bằng SQL thẳng thì **xoá sạch `<APP_DIR>/storage/cache/content/`**,
không thì API vẫn trả số đếm cũ.

### 2. Tạo `config.php`

Chép `app/config.example.php` thành `app/config.php` rồi điền. Ba giá trị bắt buộc:

- `app_url` — địa chỉ thật, không có `/` cuối. Dùng cho link trong email và redirect Google.
- `db` — thông tin database tạo trong panel hosting.
- `app_key` — 64 ký tự ngẫu nhiên: `php -r "echo bin2hex(random_bytes(32));"`

Nên điền thêm `admin_emails` — danh sách email được làm quản trị, ví dụ
`'admin_emails' => ['a@gmail.com', 'b@gmail.com'],`. Có danh sách thì tài khoản ghi `role = admin` trong DB mà email không
nằm trong đó vẫn chỉ là người học: ai sửa được DB cũng không tự lên quyền quản trị. Để trống là không giới hạn.
Thêm người quản trị mới: thêm email vào đây trước, rồi mới đổi quyền ở Quản trị → Người dùng (ngoài danh sách thì bị chặn).

`config.php` **không** được commit (đã có trong `.gitignore`) và nằm ngoài `public_html`.

### 3. Tạo bảng + tài khoản quản trị

```bash
php <APP_DIR>/tools/install.php
```

Script hỏi email / tên / mật khẩu cho tài khoản quản trị đầu tiên. Muốn chạy không cần hỏi thì đặt biến môi trường `ZIKA_ADMIN_EMAIL`, `ZIKA_ADMIN_NAME`, `ZIKA_ADMIN_PASSWORD`. Trên web thật script **không** nạp dữ liệu mẫu.

### 4. Cron (panel hosting → Cron)

```
*/5 * * * *  /usr/local/bin/php <APP_DIR>/cron/every5min.php  >/dev/null 2>&1
10 0 * * *   /usr/local/bin/php <APP_DIR>/cron/daily.php      >/dev/null 2>&1
0 3 * * *    /usr/local/bin/php <APP_DIR>/cron/backup.php     >/dev/null 2>&1
```

Đường dẫn PHP CLI thật xem trong panel (thường `/usr/local/bin/php` hoặc `/opt/alt/php84/usr/bin/php`). Chạy tay để kiểm tra, thêm `-v` để xem kết quả từng bước:

```bash
php <APP_DIR>/cron/daily.php -v
php <APP_DIR>/cron/backup.php --force -v
```

- `every5min.php` — đóng phiên treo, ghi mẫu online, chấm bài kiểm tra quá giờ, gửi tối đa 50 email đang chờ, bật/tắt thông báo theo lịch.
- `daily.php` — chốt số liệu hôm qua, gộp sự kiện Shopee, xoá dữ liệu quá hạn.
- `backup.php` — xuất cả DB ra `storage/backups/zika-*.sql.gz`, giữ 7 bản. Chỉ chạy khi Cài đặt → “Tự sao lưu mỗi ngày” đang bật (`--force` để bỏ qua). Tải bản sao lưu về máy ở **Quản trị → Cài đặt → Sao lưu dữ liệu**.

### 5. Gửi email thật (SMTP)

Không điền `mail.host` thì email **không gửi đi** mà chỉ ghi vào `app/storage/logs/mail-YYYY-MM-DD.log` — tiện khi dev, nhưng trên web thật người học sẽ không nhận được thư xác nhận hay đặt lại mật khẩu.

```php
'mail' => [
    'host' => 'smtp.gmail.com',   // hoặc SMTP của hosting
    'port' => 587,
    'secure' => 'tls',            // 'ssl' nếu dùng cổng 465
    'user' => 'no-reply@zika.vn',
    'pass' => 'mật khẩu ứng dụng',
    'from' => 'no-reply@zika.vn', // nên trùng domain để thư không vào spam
    'from_name' => 'Zìkǎ',
],
```

Dùng Gmail thì phải bật xác minh 2 bước rồi tạo **mật khẩu ứng dụng** (App password) — mật khẩu Gmail thường không đăng nhập SMTP được. Email xếp hàng trong bảng `mail_queue`, cron 5 phút gửi 50 thư mỗi lượt; thư quan trọng (xác nhận email, đặt lại mật khẩu) thử gửi ngay. Thư lỗi được thử lại tối đa 5 lần rồi chuyển `failed`, lý do nằm ở cột `last_error`.

### 6. Đăng nhập bằng Google (không bắt buộc)

Để trống `google.client_id` thì nút Google **tự ẩn**, web vẫn đăng nhập bằng email bình thường. Muốn bật:

1. Vào [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → tạo project.
2. **OAuth consent screen**: kiểu *External*, điền tên app, email hỗ trợ, domain. Thêm scope `openid`, `email`, `profile`.
3. **Credentials → Create credentials → OAuth client ID**, kiểu *Web application*.
4. **Authorized redirect URIs** điền đúng một dòng — sai một ký tự là Google báo `redirect_uri_mismatch`:

   ```
   <app_url>/api/auth/google/callback
   ```

   Ví dụ `app_url = 'https://zika.vn'` thì điền `https://zika.vn/api/auth/google/callback`.
5. Chép Client ID và Client secret vào `config.php`:

   ```php
   'google' => [
       'client_id'     => '....apps.googleusercontent.com',
       'client_secret' => '....',
   ],
   ```
6. Bật công tắc trong **Quản trị → Cài đặt → Đăng nhập → Cho đăng nhập bằng Google**.

### 7. Cài như app trên điện thoại (PWA)

`manifest.webmanifest` + `service-worker.js` đã nằm sẵn trong bản build. Người dùng mở web bằng **https** sẽ thấy “Thêm vào màn hình chính”. Service worker giữ sẵn mã nguồn và nội dung bài đã học (`/api/learn/**`, 30 ngày) nên mở lại bài cũ không cần mạng; các API riêng tư (đăng nhập, tiến độ, kiểm tra, quản trị) không bao giờ được cache. Có bản mới thì web hiện thông báo “Đã có bản cập nhật · Tải lại”.

> Service worker chỉ chạy ở **https** (hoặc `http://localhost`). Mở bằng IP hoặc http thường thì phần cài app và chạy offline không hoạt động — web vẫn dùng bình thường.
