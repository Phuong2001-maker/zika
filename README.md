# Zìkǎ (字卡)

Web học tiếng Trung miễn phí theo cấp **HSK 1 → 6** bằng thẻ lật, có 6 phần học (Lật thẻ, Bài tập, Kiểm tra, Luyện viết, Đọc hội thoại, Cấu trúc ngữ pháp) và trang quản trị.

- **Frontend:** SvelteKit 2 + Svelte 5 (SPA tĩnh) · TypeScript · CSS tự viết theo design system “pastel khối”.
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

## Kiểm thử

```bash
cd web && npm test && npm run check && npm run lint
cd api && php app/vendor/bin/phpunit -c phpunit.xml   # cần database zika_test
```

## Đưa lên hosting

`bash deploy/build-release.sh` → `release/zika-release-*.zip`, rồi làm theo `zika-spec/docs/02-kien-truc.md` §8 và checklist `09` §5. Cron:

```
*/5 * * * *  php <APP_DIR>/cron/every5min.php
10 0 * * *   php <APP_DIR>/cron/daily.php
0 3 * * *    php <APP_DIR>/cron/backup.php
```
