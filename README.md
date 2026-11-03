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

## Giáo trình (3 quyển HSK 1–3)

Ngoài 6 phần học, người học còn đọc được nguyên ba quyển giáo trình của Đại học Hà Nội: bấm
**Giáo trình** ở bảng Chuyển nhanh → kệ 3 quyển → cuộn lên/xuống để đọc các trang nối tiếp như PDF.
Thanh số trang cho phép đi thẳng tới trang cần đọc. Chỗ đọc dở nhớ riêng từng quyển bằng `localStorage`.
Ảnh tải dần khi cuộn; giữ chỗ theo kích thước trang để nội dung không nhảy khi ảnh tải xong.

Nội dung là **ảnh từng trang**, không phải chữ — vì HSK 2 và HSK 3 là bản scan, không tách chữ
ra được. Sinh ảnh bằng:

```bash
cd data/giao-trinh && python tach-giao-trinh.py "<thư mục uploads>"
```

Máy dev thì thư mục uploads là `api/dev-uploads`, hosting là `public_html/uploads`. Script cần
`pymupdf` và đường dẫn 3 file PDF khai trong chính file đó. Kết quả: 469 trang WebP rộng 1200px
+ 3 ảnh bìa, tổng ~44MB.

> **Ảnh không nằm trong git.** Đẩy code lên hosting là chưa đủ — phải tải thư mục `giao-trinh/`
> lên `public_html/uploads/` (giải nén thành `uploads/giao-trinh/hsk1/001.webp`…), nếu không màn
> đọc sẽ báo "Chưa có ảnh trang sách".

Kế hoạch nạp nội dung 3 quyển này vào 6 phần học ghi ở
[data/giao-trinh/TIEN-DO.md](data/giao-trinh/TIEN-DO.md).

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
